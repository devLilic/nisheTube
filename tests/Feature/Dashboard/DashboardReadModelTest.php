<?php

namespace Tests\Feature\Dashboard;

use App\Domain\Dashboard\ReadModels\BuildDashboard;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\YouTube\Enums\QuotaUsageOutcome;
use App\Models\ApiUsageEvent;
use App\Models\Market;
use App\Models\OpportunityScore;
use App\Models\ResearchProject;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardReadModelTest extends TestCase
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

    public function test_dashboard_returns_user_scoped_counts_runs_scores_trend_quota_and_cleanup_status(): void
    {
        $owner = User::factory()->create(['timezone' => 'Europe/Chisinau']);
        $otherUser = User::factory()->create();
        $ownerQuery = $this->researchQuery($owner, 'Owner niche');
        $foreignQuery = $this->researchQuery($otherUser, 'Foreign private niche');

        ResearchProject::query()->create(['user_id' => $owner->id, 'name' => 'Active project']);
        ResearchProject::query()->create([
            'user_id' => $owner->id,
            'name' => 'Archived project',
            'archived_at' => '2026-08-01 00:00:00',
        ]);
        ResearchProject::query()->create(['user_id' => $otherUser->id, 'name' => 'Foreign project']);

        $lower = $this->researchRun(
            $owner,
            $ownerQuery,
            ResearchRunStatus::Completed,
            'Romanian home studios',
            '2026-07-31 22:30:00',
            completedAt: '2026-08-01 00:00:00',
        );
        $this->score($lower, 68.5, 74.25, '2026-08-01 00:05:00');

        $best = $this->researchRun(
            $owner,
            $ownerQuery,
            ResearchRunStatus::Completed,
            'Compact creator studios',
            '2026-08-07 08:00:00',
            completedAt: '2026-08-07 08:30:00',
            warnings: ['youtube_partial_data'],
        );
        $this->score($best, 84.75, 81.5, '2026-08-07 08:35:00');

        $active = $this->researchRun(
            $owner,
            $ownerQuery,
            ResearchRunStatus::Searching,
            'Active owner search',
            '2026-08-08 09:00:00',
        );
        $this->researchRun(
            $owner,
            $ownerQuery,
            ResearchRunStatus::Failed,
            'Old failed owner search',
            '2026-01-01 10:00:00',
            failedAt: '2026-01-01 10:05:00',
        );

        $foreign = $this->researchRun(
            $otherUser,
            $foreignQuery,
            ResearchRunStatus::Completed,
            'Foreign private niche',
            '2026-08-08 10:00:00',
            completedAt: '2026-08-08 10:30:00',
        );
        $this->score($foreign, 99, 99, '2026-08-08 10:35:00');

        $this->quotaEvent($owner, 2);
        $this->quotaEvent($otherUser, 1);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('dashboard')
                ->missing('dashboard')
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->where('dashboard.counts.research_runs_this_month', 3)
                    ->where('dashboard.counts.active_runs', 1)
                    ->where('dashboard.counts.failed_runs_this_month', 0)
                    ->where('dashboard.counts.saved_projects', 1)
                    ->where('dashboard.counts.saved_items', null)
                    ->where('dashboard.availability.saved_items', false)
                    ->where('dashboard.availability.discovery_candidates', false)
                    ->where('dashboard.best_opportunity.public_id', $best->public_id)
                    ->where('dashboard.best_opportunity.overall_score', 84.75)
                    ->has('dashboard.recent_runs', 4)
                    ->where('dashboard.recent_runs.0.public_id', $active->public_id)
                    ->where('dashboard.recent_runs.1.public_id', $best->public_id)
                    ->where('dashboard.recent_runs.1.has_partial_data', true)
                    ->has('dashboard.top_opportunities', 2)
                    ->where('dashboard.top_opportunities.0.public_id', $best->public_id)
                    ->where('dashboard.top_opportunities.1.public_id', $lower->public_id)
                    ->has('dashboard.score_trend', 2)
                    ->where('dashboard.score_trend.0.public_id', $lower->public_id)
                    ->where('dashboard.score_trend.1.public_id', $best->public_id)
                    ->where('dashboard.quota.label', 'NisheTube estimate')
                    ->where('dashboard.quota.buckets.0.bucket', 'search')
                    ->where('dashboard.quota.buckets.0.used', 3)
                    ->where('dashboard.cleanup.status', 'due')
                    ->where('dashboard.cleanup.candidate_run_count', 1)
                    ->where('dashboard.cleanup.oldest_candidate_at', '2026-01-01T10:05:00+00:00'))
            )
            ->assertDontSee('Foreign private niche');
    }

    public function test_empty_dashboard_has_explicit_empty_and_current_states(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->missing('dashboard')
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->where('dashboard.counts.research_runs_this_month', 0)
                    ->where('dashboard.counts.active_runs', 0)
                    ->where('dashboard.counts.saved_projects', 0)
                    ->where('dashboard.best_opportunity', null)
                    ->has('dashboard.recent_runs', 0)
                    ->has('dashboard.top_opportunities', 0)
                    ->has('dashboard.score_trend', 0)
                    ->where('dashboard.cleanup.status', 'current')
                    ->where('dashboard.cleanup.candidate_run_count', 0))
            );
    }

    public function test_dashboard_exposes_active_partial_and_failed_run_states_for_the_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $ownerQuery = $this->researchQuery($owner, 'Lifecycle coverage');
        $foreignQuery = $this->researchQuery($otherUser, 'Foreign lifecycle coverage');

        $partial = $this->researchRun(
            $owner,
            $ownerQuery,
            ResearchRunStatus::Completed,
            'Partial completed run',
            '2026-08-08 11:00:00',
            completedAt: '2026-08-08 11:10:00',
            warnings: ['youtube_partial_data'],
        );
        $failed = $this->researchRun(
            $owner,
            $ownerQuery,
            ResearchRunStatus::Failed,
            'Failed owner run',
            '2026-08-08 10:00:00',
            failedAt: '2026-08-08 10:05:00',
        );

        $activeRuns = [];
        foreach ([
            ResearchRunStatus::Scoring,
            ResearchRunStatus::Enriching,
            ResearchRunStatus::Searching,
            ResearchRunStatus::Queued,
            ResearchRunStatus::Draft,
        ] as $index => $status) {
            $activeRuns[] = $this->researchRun(
                $owner,
                $ownerQuery,
                $status,
                "{$status->value} owner run",
                sprintf('2026-08-08 %02d:00:00', 9 - $index),
            );
        }

        $this->researchRun(
            $otherUser,
            $foreignQuery,
            ResearchRunStatus::Failed,
            'Foreign failed run',
            '2026-08-08 11:30:00',
            failedAt: '2026-08-08 11:35:00',
        );

        $this->actingAs($owner)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->missing('dashboard')
                ->loadDeferredProps('default', fn (Assert $deferred): Assert => $deferred
                    ->where('dashboard.counts.research_runs_this_month', 7)
                    ->where('dashboard.counts.active_runs', 5)
                    ->where('dashboard.counts.failed_runs_this_month', 1)
                    ->has('dashboard.recent_runs', 7)
                    ->where('dashboard.recent_runs.0.public_id', $partial->public_id)
                    ->where('dashboard.recent_runs.0.status', ResearchRunStatus::Completed->value)
                    ->where('dashboard.recent_runs.0.has_partial_data', true)
                    ->where('dashboard.recent_runs.0.error_code', null)
                    ->where('dashboard.recent_runs.1.public_id', $failed->public_id)
                    ->where('dashboard.recent_runs.1.status', ResearchRunStatus::Failed->value)
                    ->where('dashboard.recent_runs.1.has_partial_data', false)
                    ->where('dashboard.recent_runs.1.error_code', 'youtube_unavailable')
                    ->where('dashboard.recent_runs.1.error_message', 'YouTube was temporarily unavailable.')
                    ->where('dashboard.recent_runs.1.failed_at', '2026-08-08T10:05:00+00:00')
                    ->where('dashboard.recent_runs.2.public_id', $activeRuns[0]->public_id)
                    ->where('dashboard.recent_runs.2.status', ResearchRunStatus::Scoring->value)
                    ->where('dashboard.recent_runs.6.public_id', $activeRuns[4]->public_id)
                    ->where('dashboard.recent_runs.6.status', ResearchRunStatus::Draft->value))
            )
            ->assertDontSee('Foreign failed run');
    }

    public function test_dashboard_aggregates_respect_timezone_score_window_status_and_formula_boundaries(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Los_Angeles']);
        $query = $this->researchQuery($user, 'Boundary coverage');

        $this->researchRun(
            $user,
            $query,
            ResearchRunStatus::Completed,
            'Previous local month',
            '2026-08-01 06:59:59',
            completedAt: '2026-08-01 06:59:59',
        );
        $this->researchRun(
            $user,
            $query,
            ResearchRunStatus::Completed,
            'Current local month',
            '2026-08-01 07:00:00',
            completedAt: '2026-08-01 07:00:00',
        );

        $atWindowBoundary = $this->researchRun(
            $user,
            $query,
            ResearchRunStatus::Completed,
            'At opportunity window boundary',
            '2026-05-10 11:00:00',
            completedAt: '2026-05-10 11:30:00',
        );
        $this->score($atWindowBoundary, 75, 70, '2026-05-10 12:00:00');

        $outsideWindow = $this->researchRun(
            $user,
            $query,
            ResearchRunStatus::Completed,
            'Outside opportunity window',
            '2026-05-10 10:00:00',
            completedAt: '2026-05-10 10:30:00',
        );
        $this->score($outsideWindow, 99, 99, '2026-05-10 11:59:59');

        $wrongFormula = $this->researchRun(
            $user,
            $query,
            ResearchRunStatus::Completed,
            'Wrong formula version',
            '2026-08-07 08:00:00',
            completedAt: '2026-08-07 08:30:00',
        );
        $this->score($wrongFormula, 100, 100, '2026-08-07 08:35:00', 'future-formula-v2');

        $failedWithScore = $this->researchRun(
            $user,
            $query,
            ResearchRunStatus::Failed,
            'Failed run with stale score',
            '2026-08-06 08:00:00',
            failedAt: '2026-08-06 08:30:00',
        );
        $this->score($failedWithScore, 98, 98, '2026-08-06 08:35:00');

        $dashboard = app(BuildDashboard::class)->handle($user);

        $this->assertSame(3, $dashboard['counts']['research_runs_this_month']);
        $this->assertSame(1, $dashboard['counts']['failed_runs_this_month']);
        $this->assertSame($atWindowBoundary->public_id, $dashboard['best_opportunity']['public_id']);
        $this->assertSame([$atWindowBoundary->public_id], array_column($dashboard['top_opportunities'], 'public_id'));
        $this->assertSame(
            [$outsideWindow->public_id, $atWindowBoundary->public_id],
            array_column($dashboard['score_trend'], 'public_id'),
        );
    }

    public function test_dashboard_lists_are_bounded_and_deterministically_ordered(): void
    {
        $user = User::factory()->create();
        $query = $this->researchQuery($user, 'Bounded lists');
        $runs = [];

        for ($number = 1; $number <= 14; $number++) {
            $timestamp = sprintf('2026-07-%02d 10:00:00', $number);
            $runs[$number] = $this->researchRun(
                $user,
                $query,
                ResearchRunStatus::Completed,
                "Bounded run {$number}",
                $timestamp,
                completedAt: $timestamp,
            );
            $this->score($runs[$number], (float) $number, 60, $timestamp);
        }

        $dashboard = app(BuildDashboard::class)->handle($user);

        $this->assertSame(
            array_map(fn (int $number): string => $runs[$number]->public_id, range(14, 7)),
            array_column($dashboard['recent_runs'], 'public_id'),
        );
        $this->assertSame(
            array_map(fn (int $number): string => $runs[$number]->public_id, range(14, 10)),
            array_column($dashboard['top_opportunities'], 'public_id'),
        );
        $this->assertSame(
            array_map(fn (int $number): string => $runs[$number]->public_id, range(3, 14)),
            array_column($dashboard['score_trend'], 'public_id'),
        );
    }

    public function test_dashboard_read_model_uses_a_constant_number_of_queries(): void
    {
        $user = User::factory()->create();
        $query = $this->researchQuery($user, 'Query count niche');

        for ($number = 1; $number <= 20; $number++) {
            $this->researchRun(
                $user,
                $query,
                ResearchRunStatus::Searching,
                "Run {$number}",
                '2026-08-08 10:'.str_pad((string) $number, 2, '0', STR_PAD_LEFT).':00',
            );
        }

        DB::enableQueryLog();
        DB::flushQueryLog();

        $dashboard = app(BuildDashboard::class)->handle($user);
        $queries = DB::getQueryLog();

        DB::disableQueryLog();

        $this->assertCount(7, $queries, 'Dashboard read queries must remain bounded as run volume grows.');
        $this->assertSame(20, $dashboard['counts']['active_runs']);
        $this->assertCount(8, $dashboard['recent_runs']);
    }

    private function researchQuery(User $user, string $queryText): ResearchQuery
    {
        return ResearchQuery::query()->create([
            'user_id' => $user->id,
            'market_id' => Market::query()->where('key', 'global_en')->value('id'),
            'query_text' => $queryText,
        ]);
    }

    /** @param list<string> $warnings */
    private function researchRun(
        User $user,
        ResearchQuery $query,
        ResearchRunStatus $status,
        string $queryText,
        string $createdAt,
        ?string $completedAt = null,
        ?string $failedAt = null,
        array $warnings = [],
    ): ResearchRun {
        $run = new ResearchRun([
            'user_id' => $user->id,
            'research_query_id' => $query->id,
            'kind' => ResearchRunKind::Search,
            'status' => $status,
            'attempt_number' => $query->runs()->count() + 1,
            'query_text' => $queryText,
            'market_key' => 'global_en',
            'region_code' => null,
            'relevance_language' => 'en',
            'parameters' => ['order' => 'relevance'],
            'requested_result_count' => 25,
            'collected_result_count' => $status === ResearchRunStatus::Searching ? 5 : 25,
            'enriched_result_count' => $status === ResearchRunStatus::Completed ? 25 : 0,
            'progress_percent' => $status === ResearchRunStatus::Completed ? 100 : 35,
            'collection_warnings' => $warnings,
            'started_at' => $createdAt,
            'completed_at' => $completedAt,
            'failed_at' => $failedAt,
            'error_code' => $status === ResearchRunStatus::Failed ? 'youtube_unavailable' : null,
            'error_message' => $status === ResearchRunStatus::Failed ? 'YouTube was temporarily unavailable.' : null,
        ]);
        $run->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt]);
        $run->save();

        return $run;
    }

    private function score(
        ResearchRun $run,
        float $overall,
        float $confidence,
        string $calculatedAt,
        string $formulaVersion = 'niche-opportunity-v1',
    ): OpportunityScore {
        return OpportunityScore::query()->create([
            'research_run_id' => $run->id,
            'formula_version' => $formulaVersion,
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
            'calculated_at' => $calculatedAt,
        ]);
    }

    private function quotaEvent(User $user, int $cost): void
    {
        ApiUsageEvent::query()->create([
            'user_id' => $user->id,
            'provider' => 'youtube',
            'quota_bucket' => 'search',
            'endpoint' => 'search.list',
            'request_count' => 1,
            'estimated_cost' => $cost,
            'outcome' => QuotaUsageOutcome::Succeeded,
            'occurred_at' => '2026-08-08 11:00:00',
        ]);
    }
}
