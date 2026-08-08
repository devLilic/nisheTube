<?php

namespace Tests\Feature\Scoring;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Data\RunFailure;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ScoreExplanationInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        CarbonImmutable::setTestNow('2026-08-08 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_owner_receives_the_persisted_score_confidence_components_formula_and_warnings(): void
    {
        $owner = User::factory()->create();
        $run = $this->completedRunWithScore($owner, 'owner scoring interface');

        $this->actingAs($owner)
            ->get(route('research.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('research/show')
                ->where('run.score.overall_score', 72.125)
                ->where('run.score.overall_label', 'Promising')
                ->where('run.score.confidence_score', 58.5)
                ->where('run.score.confidence_label', 'Limited confidence')
                ->where('run.score.formula_version', 'niche-opportunity-v1')
                ->where('run.score.sample_size', 18)
                ->where('run.score.calculated_at', '2026-08-08T12:00:00+00:00')
                ->has('run.score.components', 5)
                ->where('run.score.components.0.key', 'demand_momentum')
                ->where('run.score.components.0.label', 'Demand momentum')
                ->where('run.score.components.0.score', 81.25)
                ->where('run.score.components.0.weight_percent', 25)
                ->where('run.score.components.0.explanation', 'Demand explanation from stored inputs.')
                ->where('run.score.components.4.key', 'creator_viability')
                ->where('run.score.components.4.weight_percent', 20)
                ->has('run.score.warnings', 2)
                ->where('run.score.warnings.0.code', 'missing_subscriber_counts')
                ->where('run.score.warnings.1.code', 'no_comparable_history')
            );
    }

    public function test_score_props_preserve_owner_isolation_and_missing_score_states(): void
    {
        $owner = User::factory()->create();
        $foreignUser = User::factory()->create();
        $ownerRun = $this->newRun($owner, 'owner active scoring');
        $foreignRun = $this->completedRunWithScore($foreignUser, 'foreign scoring interface');

        $this->get(route('research.runs.show', $ownerRun))
            ->assertRedirect(route('login'));

        $this->actingAs($foreignUser)
            ->get(route('research.runs.show', $ownerRun))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('research.runs.show', $ownerRun))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.status', 'draft')
                ->where('run.score', null)
            )
            ->assertDontSee('Foreign scoring warning')
            ->assertDontSee($foreignRun->public_id);
    }

    public function test_scoring_failure_exposes_specific_safe_retry_guidance_without_a_score(): void
    {
        $owner = User::factory()->create();
        $run = $this->newRun($owner, 'scoring failure interface');
        $transition = app(TransitionResearchRun::class);
        $run = $transition->handle($run, ResearchRunStatus::Queued);
        $run = $transition->handle($run, ResearchRunStatus::Searching);
        $run = $transition->handle($run, ResearchRunStatus::Enriching);
        $run = $transition->handle($run, ResearchRunStatus::Scoring);
        $run = $transition->handle(
            $run,
            ResearchRunStatus::Failed,
            new RunFailure(
                'research_scoring_failed',
                'The saved research metrics could not be scored.',
            ),
        );

        $this->actingAs($owner)
            ->get(route('research.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.status', 'failed')
                ->where('run.score', null)
                ->where('run.error.code', 'research_scoring_failed')
                ->where('run.error.title', 'Opportunity scoring could not finish')
                ->where('run.error.action', 'retry')
            );
    }

    private function completedRunWithScore(User $user, string $queryText): ResearchRun
    {
        $run = $this->newRun($user, $queryText);
        $transition = app(TransitionResearchRun::class);
        $run = $transition->handle($run, ResearchRunStatus::Queued);
        $run = $transition->handle($run, ResearchRunStatus::Searching);
        $run = $transition->handle($run, ResearchRunStatus::Enriching);
        $run = $transition->handle($run, ResearchRunStatus::Scoring);

        $run->opportunityScores()->create($this->scoreAttributes(
            str_contains($queryText, 'foreign')
                ? 'Foreign scoring warning'
                : 'Some subscriber counts were unavailable.',
        ));

        return $transition->handle($run, ResearchRunStatus::Completed);
    }

    private function newRun(User $user, string $queryText): ResearchRun
    {
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: Market::query()->where('key', 'global_en')->firstOrFail(),
            queryText: $queryText,
        );

        return app(CreateResearchRun::class)->handle($user, $query, 25);
    }

    /** @return array<string, mixed> */
    private function scoreAttributes(string $firstWarning): array
    {
        return [
            'formula_version' => 'niche-opportunity-v1',
            'overall_score' => '72.1250',
            'demand_momentum_score' => '81.2500',
            'competition_opportunity_score' => '66.5000',
            'audience_reachability_score' => '74.7500',
            'content_freshness_gap_score' => '58.1250',
            'creator_viability_score' => '70.5000',
            'confidence_score' => '58.5000',
            'sample_size' => 18,
            'input_summary' => [
                'configuration' => [
                    'weights' => [
                        'demand_momentum' => 0.25,
                        'competition_opportunity' => 0.20,
                        'audience_reachability' => 0.20,
                        'content_freshness_gap' => 0.15,
                        'creator_viability' => 0.20,
                    ],
                ],
            ],
            'explanations' => [
                'demand_momentum' => 'Demand explanation from stored inputs.',
                'competition_opportunity' => 'Competition explanation from stored inputs.',
                'audience_reachability' => 'Reachability explanation from stored inputs.',
                'content_freshness_gap' => 'Freshness explanation from stored inputs.',
                'creator_viability' => 'Viability explanation from stored inputs.',
            ],
            'warnings' => [
                ['code' => 'missing_subscriber_counts', 'message' => $firstWarning],
                ['code' => 'no_comparable_history', 'message' => 'No comparable history was available.'],
            ],
            'calculated_at' => now(),
        ];
    }
}
