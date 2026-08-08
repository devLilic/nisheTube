<?php

namespace Tests\Feature\Scoring;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Research\Enums\SearchOrder;
use App\Domain\Scoring\Actions\CalculateOpportunityScore;
use App\Domain\Scoring\Services\NicheOpportunityV1;
use App\Jobs\Research\ScoreResearchRun;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\Market;
use App\Models\OpportunityScore;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class OpportunityScoringTest extends TestCase
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

    public function test_schema_and_model_preserve_versioned_immutable_scores(): void
    {
        $this->assertTrue(Schema::hasColumns('opportunity_scores', [
            'research_run_id',
            'formula_version',
            'overall_score',
            'demand_momentum_score',
            'competition_opportunity_score',
            'audience_reachability_score',
            'content_freshness_gap_score',
            'creator_viability_score',
            'confidence_score',
            'sample_size',
            'input_summary',
            'explanations',
            'warnings',
            'calculated_at',
        ]));
        $this->assertTrue(Schema::hasIndex(
            'opportunity_scores',
            ['research_run_id', 'formula_version'],
            'unique',
        ));

        $run = $this->scoringRun('schema test', 12, false);
        $score = app(CalculateOpportunityScore::class)->handle($run);

        $this->assertTrue($score->researchRun->is($run));
        $this->assertTrue($run->fresh()->opportunityScores->sole()->is($score));

        try {
            $score->update(['overall_score' => '99.0000']);
            $this->fail('A persisted formula result must be immutable.');
        } catch (DomainException) {
            $this->assertNotSame('99.0000', $score->fresh()->overall_score);
        }

        $run->delete();

        $this->assertDatabaseCount('opportunity_scores', 0);
    }

    public function test_v1_calculates_bounded_explainable_components_from_stored_snapshots(): void
    {
        $run = $this->scoringRun('component calculation', 12, false);

        $score = app(CalculateOpportunityScore::class)->handle($run);
        $run->refresh();

        $this->assertSame(ResearchRunStatus::Completed, $run->status);
        $this->assertSame(100, $run->progress_percent);
        $this->assertSame(NicheOpportunityV1::VERSION, $score->formula_version);
        $this->assertSame(12, $score->sample_size);
        $this->assertSame('2026-08-08 12:00:00', $score->calculated_at->format('Y-m-d H:i:s'));

        foreach ([
            $score->overall_score,
            $score->demand_momentum_score,
            $score->competition_opportunity_score,
            $score->audience_reachability_score,
            $score->content_freshness_gap_score,
            $score->creator_viability_score,
            $score->confidence_score,
        ] as $value) {
            $this->assertGreaterThanOrEqual(0, (float) $value);
            $this->assertLessThanOrEqual(100, (float) $value);
        }

        $this->assertSame(
            [
                'demand_momentum' => 0.25,
                'competition_opportunity' => 0.20,
                'audience_reachability' => 0.20,
                'content_freshness_gap' => 0.15,
                'creator_viability' => 0.20,
            ],
            $score->input_summary['configuration']['weights'],
        );
        $this->assertSame(12, $score->input_summary['sample']['scored_video_count']);
        $this->assertArrayHasKey('median_views_per_day', $score->input_summary['statistics']);
        $this->assertSame([
            'demand_momentum',
            'competition_opportunity',
            'audience_reachability',
            'content_freshness_gap',
            'creator_viability',
        ], array_keys($score->explanations));
        $this->assertContains('no_comparable_history', array_column($score->warnings, 'code'));
        $this->assertContains('mixed_content_formats', array_column($score->warnings, 'code'));
    }

    public function test_identical_job_delivery_reuses_the_same_persisted_result(): void
    {
        $run = $this->scoringRun('idempotent scoring', 12, false);
        $job = new ScoreResearchRun($run->id);

        app()->call([$job, 'handle']);
        $first = OpportunityScore::query()->sole();
        app()->call([$job, 'handle']);
        $second = OpportunityScore::query()->sole();

        $this->assertSame($first->id, $second->id);
        $this->assertSame($first->overall_score, $second->overall_score);
        $this->assertSame($first->input_summary, $second->input_summary);
        $this->assertDatabaseCount('opportunity_scores', 1);
        $this->assertSame(ResearchRunStatus::Completed, $run->fresh()->status);
    }

    public function test_missing_metrics_reduce_confidence_and_create_specific_warnings(): void
    {
        $completeRun = $this->scoringRun('complete metrics', 12, false);
        $completeScore = app(CalculateOpportunityScore::class)->handle($completeRun);
        $sparseRun = $this->scoringRun('sparse metrics', 12, true);
        $sparseScore = app(CalculateOpportunityScore::class)->handle($sparseRun);
        $warningCodes = array_column($sparseScore->warnings, 'code');

        $this->assertLessThan((float) $completeScore->confidence_score, (float) $sparseScore->confidence_score);
        $this->assertContains('missing_view_velocity', $warningCodes);
        $this->assertContains('missing_subscriber_counts', $warningCodes);
        $this->assertContains('missing_engagement_metrics', $warningCodes);
        $this->assertContains('unknown_content_formats', $warningCodes);
        $this->assertSame(0, $sparseScore->input_summary['availability']['subscriber_percent']);
    }

    public function test_a_terminal_scoring_failure_uses_safe_retryable_guidance(): void
    {
        $run = $this->scoringRun('failed scoring', 1, true);
        $job = new ScoreResearchRun($run->id);

        $job->failed(new RuntimeException('Unsafe internal scoring detail'));
        $run->refresh();

        $this->assertSame(ResearchRunStatus::Failed, $run->status);
        $this->assertSame('research_scoring_failed', $run->error_code);
        $this->assertSame(
            'The saved research metrics could not be scored. Retry the run or review the local logs.',
            $run->error_message,
        );
        $this->assertStringNotContainsString('Unsafe internal scoring detail', $run->toJson());
    }

    private function scoringRun(string $queryText, int $sampleSize, bool $sparse): ResearchRun
    {
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: $market,
            queryText: $queryText,
            searchOrder: SearchOrder::ViewCount,
        );
        $run = app(CreateResearchRun::class)->handle($user, $query, $sampleSize);
        $transition = app(TransitionResearchRun::class);
        $run = $transition->handle($run, ResearchRunStatus::Queued);
        $run = $transition->handle($run, ResearchRunStatus::Searching);
        $run = $transition->handle($run, ResearchRunStatus::Enriching);

        for ($number = 1; $number <= $sampleSize; $number++) {
            $channelNumber = (int) ceil($number / 3);
            $channel = Channel::query()->firstOrCreate(
                [
                    'provider' => 'youtube',
                    'provider_channel_id' => "{$queryText}-channel-{$channelNumber}",
                ],
                ['title' => "Channel {$channelNumber}"],
            );
            $video = Video::query()->create([
                'provider' => 'youtube',
                'provider_video_id' => "{$queryText}-video-{$number}",
                'channel_id' => $channel->id,
                'title' => "Video {$number}",
                'published_at' => now()->subDays($number * 35),
                'duration_seconds' => $sparse ? null : ($number % 2 === 0 ? 45 : 600),
                'category_id' => $sparse ? null : (string) (($number % 4) + 1),
                'is_short' => $sparse ? null : $number % 2 === 0,
            ]);
            $run->videos()->attach($video->id, [
                'result_rank' => $number,
                'page_number' => 1,
                'provider_order' => $number,
            ]);

            ChannelSnapshot::query()->firstOrCreate([
                'research_run_id' => $run->id,
                'channel_id' => $channel->id,
            ], [
                'subscriber_count' => $sparse ? null : $channelNumber * 1000,
                'view_count' => $channelNumber * 100000,
                'video_count' => $sparse ? null : $channelNumber * 50,
                'subscriber_count_hidden' => $sparse,
                'metadata' => $sparse ? null : ['published_at' => '2020-01-01T00:00:00Z'],
                'collected_at' => now(),
            ]);

            VideoSnapshot::query()->create([
                'research_run_id' => $run->id,
                'video_id' => $video->id,
                'view_count' => $number * 10000,
                'like_count' => $sparse ? null : $number * 500,
                'comment_count' => $sparse ? null : $number * 50,
                'age_seconds' => $number * 35 * 86400,
                'views_per_day' => $sparse && $number > 2 ? null : $number * 200,
                'views_to_subscribers_ratio' => $sparse ? null : ($number * 10000) / ($channelNumber * 1000),
                'collected_at' => now(),
            ]);
        }

        $run->update([
            'collected_result_count' => $sampleSize,
            'enriched_result_count' => $sampleSize,
            'progress_percent' => 89,
        ]);

        return $transition->handle($run, ResearchRunStatus::Scoring);
    }
}
