<?php

namespace Tests\Feature\History;

use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Jobs\Research\CollectResearchRunSearch;
use App\Models\Market;
use App\Models\OpportunityScore;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HistoryInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_history_index_is_deferred_owner_scoped_and_exposes_useful_pair_selection(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $anchor = $this->researchRun($owner, 'Compact homes', ResearchRunStatus::Completed, '2026-08-08 10:00:00');
        $candidate = $this->researchRun($owner, 'compact homes', ResearchRunStatus::Completed, '2026-08-01 10:00:00');
        $active = $this->researchRun($owner, 'Active owner run', ResearchRunStatus::Searching, null);
        $foreign = $this->researchRun($otherUser, 'Foreign private run', ResearchRunStatus::Completed, '2026-08-07 10:00:00');
        $this->score($anchor, 70, 75);
        $this->score($candidate, 60, 65);
        $this->score($foreign, 99, 99);

        $this->get(route('history.index'))->assertRedirect(route('login'));

        $this->actingAs($owner)
            ->get(route('history.index', ['anchor' => $anchor->public_id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('history/index')
                ->missing('history')
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->has('history.runs', 3)
                    ->where('history.runs.0.public_id', $active->public_id)
                    ->where('history.selected_anchor', $anchor->public_id)
                    ->has('history.candidates', 1)
                    ->where('history.candidates.0.public_id', $candidate->public_id)
                    ->where('history.candidates.0.score_comparable', true)
                    ->where('history.truncated', false))
            )
            ->assertDontSee('Foreign private run');
    }

    public function test_comparison_page_defers_real_deltas_and_rejects_foreign_or_incompatible_pairs(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $before = $this->researchRun($owner, 'Compact homes', ResearchRunStatus::Completed, '2026-08-01 10:00:00');
        $after = $this->researchRun($owner, 'compact homes', ResearchRunStatus::Completed, '2026-08-08 10:00:00');
        $foreign = $this->researchRun($otherUser, 'Compact homes', ResearchRunStatus::Completed, '2026-08-07 10:00:00');
        $different = $this->researchRun($owner, 'Different query', ResearchRunStatus::Completed, '2026-08-06 10:00:00');
        $this->score($before, 50, 70);
        $this->score($after, 65, 80);

        $this->get(route('history.compare', [$before, $after]))->assertRedirect(route('login'));

        $this->actingAs($owner)
            ->get(route('history.compare', [$before, $after]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('history/compare')
                ->where('pair.before.public_id', $before->public_id)
                ->where('pair.after.public_id', $after->public_id)
                ->where('pair.before.score.overall_score', 50)
                ->where('pair.after.score.confidence_score', 80)
                ->missing('comparison')
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->where('comparison.compatibility.comparable', true)
                    ->where('comparison.compatibility.score_comparable', true)
                    ->where('comparison.score_deltas.overall_score.delta', 15)
                    ->where('comparison.score_deltas.confidence_score.delta', 10)
                    ->has('comparison.videos.new', 0)
                    ->has('comparison.channels.new', 0))
            );

        $this->actingAs($owner)
            ->get(route('history.compare', [$before, $foreign]))
            ->assertForbidden();
        $this->actingAs($owner)
            ->get(route('history.compare', [$before, $different]))
            ->assertUnprocessable();
    }

    public function test_history_filters_are_bounded_deterministic_and_owner_scoped(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $matching = $this->researchRun($owner, 'Compact homes', ResearchRunStatus::Completed, '2026-08-08 10:00:00');
        $this->researchRun($owner, 'Compact homes', ResearchRunStatus::Searching, null);
        $this->researchRun($otherUser, 'Compact homes', ResearchRunStatus::Completed, '2026-08-09 10:00:00');
        $this->score($matching, 70, 80);

        $this->actingAs($owner)
            ->get(route('history.index', [
                'q' => 'compact',
                'status' => 'completed',
                'min_score' => 65,
                'per_page' => 10,
            ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->has('history.runs', 1)
                    ->where('history.runs.0.public_id', $matching->public_id)
                    ->where('history.filters.per_page', 10)
                    ->where('history.pagination.total', 1)));
    }

    public function test_repeat_requires_confirmation_and_is_idempotent_per_submission_token(): void
    {
        Bus::fake();
        $owner = User::factory()->create();
        $source = $this->researchRun($owner, 'Compact homes', ResearchRunStatus::Completed, '2026-08-08 10:00:00');
        $token = (string) Str::uuid();

        $this->actingAs($owner)
            ->post(route('history.runs.repeat', $source), ['submission_token' => $token])
            ->assertSessionHasErrors('confirmation');
        Bus::assertNothingDispatched();

        $this->actingAs($owner)
            ->post(route('history.runs.repeat', $source), ['confirmation' => true, 'submission_token' => $token])
            ->assertRedirect();
        $this->assertSame(2, $owner->researchRuns()->count());
        Bus::assertDispatched(CollectResearchRunSearch::class, 1);

        $this->actingAs($owner)
            ->post(route('history.runs.repeat', $source), ['confirmation' => true, 'submission_token' => $token])
            ->assertRedirect();
        $this->assertSame(2, $owner->researchRuns()->count());
        Bus::assertDispatched(CollectResearchRunSearch::class, 1);
    }

    private function researchRun(
        User $user,
        string $queryText,
        ResearchRunStatus $status,
        ?string $completedAt,
    ): ResearchRun {
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = ResearchQuery::query()->create([
            'user_id' => $user->id,
            'market_id' => $market->id,
            'query_text' => $queryText,
        ]);

        return ResearchRun::query()->create([
            'user_id' => $user->id,
            'research_query_id' => $query->id,
            'kind' => ResearchRunKind::Search,
            'status' => $status,
            'attempt_number' => 1,
            'query_text' => $query->query_text,
            'market_key' => $market->key,
            'region_code' => $market->region_code,
            'relevance_language' => $market->relevance_language,
            'parameters' => ['search_order' => 'relevance'],
            'requested_result_count' => 25,
            'collected_result_count' => $status === ResearchRunStatus::Completed ? 25 : 5,
            'enriched_result_count' => $status === ResearchRunStatus::Completed ? 25 : 0,
            'progress_percent' => $status === ResearchRunStatus::Completed ? 100 : 35,
            'completed_at' => $completedAt,
        ]);
    }

    private function score(ResearchRun $run, float $overall, float $confidence): OpportunityScore
    {
        return OpportunityScore::query()->create([
            'research_run_id' => $run->id,
            'formula_version' => 'niche-opportunity-v1',
            'overall_score' => $overall,
            'demand_momentum_score' => $overall,
            'competition_opportunity_score' => $overall,
            'audience_reachability_score' => $overall,
            'content_freshness_gap_score' => $overall,
            'creator_viability_score' => $overall,
            'confidence_score' => $confidence,
            'sample_size' => 25,
            'input_summary' => [],
            'explanations' => [],
            'warnings' => [],
            'calculated_at' => $run->completed_at,
        ]);
    }
}
