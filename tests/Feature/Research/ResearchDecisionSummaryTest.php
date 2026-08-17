<?php

namespace Tests\Feature\Research;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Data\RunFailure;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Research\ReadModels\BuildResearchDecisionSummary;
use App\Models\ApiUsageEvent;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResearchDecisionSummaryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(MarketSeeder::class);
        CarbonImmutable::setTestNow('2026-08-15 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_active_summary_exposes_real_stage_counts_warnings_and_an_honest_eta(): void
    {
        $run = $this->transitionTo($this->newRun(), ResearchRunStatus::Searching);
        $run->update([
            'progress_percent' => 25,
            'collected_result_count' => 5,
            'collection_warnings' => ['One saved page was incomplete.'],
        ]);

        $summary = app(BuildResearchDecisionSummary::class)->handle($run->fresh(), null, $this->analysis());

        $this->assertSame('active', $summary['lifecycle']['key']);
        $this->assertSame('Research in progress', $summary['verdict']);
        $this->assertSame('Collecting candidates', $summary['active_progress']['stage']);
        $this->assertSame(25, $summary['active_progress']['percent']);
        $this->assertSame(5, $summary['active_progress']['collected_count']);
        $this->assertSame(1, $summary['active_progress']['warning_count']);
        $this->assertSame('Estimated completion: not available yet', $summary['active_progress']['eta_label']);
        $this->assertStringContainsString('does not have enough timing evidence', $summary['active_progress']['eta_explanation']);
    }

    public function test_terminal_rules_distinguish_complete_partial_reduced_confidence_and_failed_partial_results(): void
    {
        $complete = $this->completedRun($this->newRun(), 2, 2);
        $completeSummary = app(BuildResearchDecisionSummary::class)->handle(
            $complete,
            $this->score(72, 75),
            $this->analysis(),
        );

        $this->assertSame('complete_data', $completeSummary['lifecycle']['key']);
        $this->assertSame('Promising', $completeSummary['verdict']);
        $this->assertCount(5, $completeSummary['principal_evidence']);
        $this->assertSame('2 videos · 2 channels', $completeSummary['principal_evidence'][3]['value']);
        $this->assertSame('Observed within 24 hours', $completeSummary['observation']['freshness_label']);
        $this->assertSame('review_evidence', $completeSummary['next_action']['kind']);

        $reduced = $this->completedRun($this->newRun(), 2, 2);
        $reducedSummary = app(BuildResearchDecisionSummary::class)->handle(
            $reduced,
            $this->score(72, 55),
            $this->analysis(),
        );
        $this->assertSame('reduced_confidence', $reducedSummary['lifecycle']['key']);
        $this->assertSame('new_search', $reducedSummary['next_action']['kind']);

        $partial = $this->completedRun($this->newRun(), 2, 2);
        $partialAnalysis = $this->analysis();
        $partialAnalysis['videos'][1]['engagement_rate'] = null;
        $partialSummary = app(BuildResearchDecisionSummary::class)->handle(
            $partial,
            $this->score(72, 75),
            $partialAnalysis,
        );
        $this->assertSame('partial_data', $partialSummary['lifecycle']['key']);
        $this->assertSame(1, $partialSummary['completeness'][4]['available']);
        $this->assertSame(2, $partialSummary['completeness'][4]['total']);
        $this->assertSame(50.0, $partialSummary['completeness'][4]['percent']);

        $failed = $this->transitionTo($this->newRun(), ResearchRunStatus::Searching);
        $failed->update(['collected_result_count' => 1]);
        $failed = app(TransitionResearchRun::class)->handle(
            $failed,
            ResearchRunStatus::Failed,
            new RunFailure('youtube_unavailable', 'YouTube was temporarily unavailable.'),
        );
        $failedSummary = app(BuildResearchDecisionSummary::class)->handle($failed, null, $this->emptyAnalysis());

        $this->assertSame('failed_with_partial', $failedSummary['lifecycle']['key']);
        $this->assertSame('Failed — partial results saved', $failedSummary['lifecycle']['label']);
        $this->assertSame('retry', $failedSummary['next_action']['kind']);
    }

    public function test_viewing_the_summary_is_owner_scoped_and_performs_no_provider_work(): void
    {
        Http::preventStrayRequests();
        $owner = User::factory()->create();
        $run = $this->transitionTo($this->newRun($owner), ResearchRunStatus::Searching);
        $usageBefore = ApiUsageEvent::query()->count();

        $this->get(route('research.runs.show', $run))
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('research.runs.show', $run))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('research.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->component('research/show')
                ->where('run.decision_summary.lifecycle.key', 'active')
                ->where('run.decision_summary.active_progress.stage', 'Collecting candidates')
                ->where('run.decision_summary.stability.key', 'not_measured')
                ->has('run.decision_summary.completeness', 6)
                ->where('run.decision_summary.next_action.kind', 'wait')
            );

        $this->assertSame($usageBefore, ApiUsageEvent::query()->count());
    }

    private function newRun(?User $user = null): ResearchRun
    {
        $user ??= User::factory()->create();
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: Market::query()->where('key', 'global_en')->firstOrFail(),
            queryText: 'decision summary research',
        );

        return app(CreateResearchRun::class)->handle($user, $query, 2);
    }

    private function transitionTo(ResearchRun $run, ResearchRunStatus $target): ResearchRun
    {
        $transition = app(TransitionResearchRun::class);
        $run = $transition->handle($run, ResearchRunStatus::Queued);

        if ($target === ResearchRunStatus::Queued) {
            return $run;
        }

        return $transition->handle($run, ResearchRunStatus::Searching);
    }

    private function completedRun(ResearchRun $run, int $collected, int $enriched): ResearchRun
    {
        $transition = app(TransitionResearchRun::class);
        $run = $transition->handle($run, ResearchRunStatus::Queued);
        $run = $transition->handle($run, ResearchRunStatus::Searching);
        $run->update([
            'collected_result_count' => $collected,
            'enriched_result_count' => $enriched,
        ]);
        $run = $transition->handle($run, ResearchRunStatus::Enriching);
        $run = $transition->handle($run, ResearchRunStatus::Scoring);

        return $transition->handle($run, ResearchRunStatus::Completed);
    }

    /** @return array<string, mixed> */
    private function score(float $overall, float $confidence): array
    {
        return [
            'overall_score' => $overall,
            'overall_label' => 'Promising',
            'confidence_score' => $confidence,
            'confidence_label' => $confidence >= 60 ? 'Moderate confidence' : 'Limited confidence',
            'sample_size' => 2,
            'warnings' => [],
            'components' => [
                ['label' => 'Demand momentum', 'score' => 80, 'explanation' => 'Demand evidence.'],
                ['label' => 'Competition opportunity', 'score' => 70, 'explanation' => 'Competition evidence.'],
                ['label' => 'Audience reachability', 'score' => 75, 'explanation' => 'Reach evidence.'],
                ['label' => 'Content freshness gap', 'score' => 60, 'explanation' => 'Freshness evidence.'],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function analysis(): array
    {
        return [
            'summary' => [
                'latest_collected_at' => '2026-08-15T10:00:00+00:00',
                'median_views_per_day' => 123.4,
            ],
            'videos' => [
                ['view_count' => 1000, 'views_per_day' => 100.0, 'engagement_rate' => 2.5],
                ['view_count' => 2000, 'views_per_day' => 200.0, 'engagement_rate' => 3.5],
            ],
            'channels' => [
                ['subscriber_count' => 100],
                ['subscriber_count' => 200],
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function emptyAnalysis(): array
    {
        return [
            'summary' => ['latest_collected_at' => null],
            'videos' => [],
            'channels' => [],
        ];
    }
}
