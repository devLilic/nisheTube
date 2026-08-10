<?php

namespace Tests\Feature\History;

use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Collection\Enums\CollectionRunKind;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\History\ReadModels\BuildResearchRunComparison;
use App\Domain\History\ReadModels\ListComparableResearchRuns;
use App\Domain\Research\Enums\ResearchRunKind;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\Market;
use App\Models\OpportunityScore;
use App\Models\ResearchQuery;
use App\Models\ResearchRun;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Database\Seeders\MarketSeeder;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HistoryComparisonServicesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
    }

    public function test_comparable_run_selection_is_owner_scoped_completed_and_same_query_market_and_kind(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $anchor = $this->researchRun($owner, 'Compact Homes', 'global_en', ResearchRunStatus::Completed, '2026-08-08 10:00:00');
        $exact = $this->researchRun($owner, 'compact homes', 'global_en', ResearchRunStatus::Completed, '2026-08-07 10:00:00');
        $parameterMismatch = $this->researchRun(
            $owner,
            'Compact Homes',
            'global_en',
            ResearchRunStatus::Completed,
            '2026-08-06 10:00:00',
            parameters: ['search_order' => 'date'],
            requestedCount: 50,
        );
        $this->score($anchor, 60, 'niche-opportunity-v1');
        $this->score($exact, 55, 'niche-opportunity-v1');
        $this->score($parameterMismatch, 70, 'future-formula-v2');

        $this->researchRun($owner, 'Different query', 'global_en', ResearchRunStatus::Completed, '2026-08-05 10:00:00');
        $this->researchRun($owner, 'Compact Homes', 'ro_ro', ResearchRunStatus::Completed, '2026-08-04 10:00:00');
        $this->researchRun($owner, 'Compact Homes', 'global_en', ResearchRunStatus::Searching, '2026-08-03 10:00:00');
        $this->researchRun(
            $owner,
            'Compact Homes',
            'global_en',
            ResearchRunStatus::Completed,
            '2026-08-02 10:00:00',
            kind: ResearchRunKind::DiscoveryValidation,
        );
        $this->researchRun($otherUser, 'Compact Homes', 'global_en', ResearchRunStatus::Completed, '2026-08-01 10:00:00');

        DB::enableQueryLog();
        DB::flushQueryLog();
        $candidates = app(ListComparableResearchRuns::class)->handle($owner, $anchor);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(3, $queries, 'Candidate selection queries must remain bounded.');
        $this->assertSame([$exact->public_id, $parameterMismatch->public_id], array_column($candidates, 'public_id'));
        $this->assertTrue($candidates[0]['score_comparable']);
        $this->assertSame([], $candidates[0]['warning_codes']);
        $this->assertFalse($candidates[1]['score_comparable']);
        $this->assertSame(
            ['collection_parameters_differ', 'formula_version_differs'],
            $candidates[1]['warning_codes'],
        );
    }

    public function test_comparison_returns_score_metric_and_entity_deltas_from_stored_snapshots(): void
    {
        $owner = User::factory()->create();
        $before = $this->researchRun($owner, 'Compact Homes', 'global_en', ResearchRunStatus::Completed, '2026-08-01 10:00:00');
        $after = $this->researchRun($owner, 'compact homes', 'global_en', ResearchRunStatus::Completed, '2026-08-08 10:00:00');
        $this->score($before, 50, 'niche-opportunity-v1', [40, 45, 50, 55, 60], 70);
        $this->score($after, 65, 'niche-opportunity-v1', [55, 60, 65, 70, 75], 80);

        $channelA = $this->channel('channel-a', 'Channel A');
        $channelB = $this->channel('channel-b', 'Channel B');
        $channelC = $this->channel('channel-c', 'Channel C');
        $videoA = $this->video($channelA, 'video-a', 'Video A');
        $videoB = $this->video($channelB, 'video-b', 'Video B');
        $videoC = $this->video($channelC, 'video-c', 'Video C');

        $this->snapshot($before, $videoA, 1, 100, 10, 1000);
        $this->snapshot($before, $videoB, 2, 300, 30, 2000);
        $this->snapshot($after, $videoB, 1, 500, 50, 2500);
        $this->snapshot($after, $videoC, 2, 700, 70, 500);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $comparison = app(BuildResearchRunComparison::class)->handle($owner, $before, $after);
        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertCount(3, $queries, 'Comparison queries must remain constant as result depth grows.');
        $this->assertTrue($comparison['compatibility']['comparable']);
        $this->assertTrue($comparison['compatibility']['score_comparable']);
        $this->assertSame([], $comparison['compatibility']['warnings']);
        $this->assertSame(15.0, $comparison['score_deltas']['overall_score']['delta']);
        $this->assertSame(10.0, $comparison['score_deltas']['confidence_score']['delta']);
        $this->assertSame(15.0, $comparison['score_deltas']['components']['demand_momentum']['delta']);
        $this->assertSame(200.0, $comparison['metric_deltas']['median_views']['before']);
        $this->assertSame(600.0, $comparison['metric_deltas']['median_views']['after']);
        $this->assertSame(400.0, $comparison['metric_deltas']['median_views']['delta']);
        $this->assertSame(40.0, $comparison['metric_deltas']['median_views_per_day']['delta']);
        $this->assertSame(0.0, $comparison['metric_deltas']['median_subscribers']['delta']);
        $this->assertSame(['video-c'], array_column($comparison['videos']['new'], 'provider_video_id'));
        $this->assertSame(['video-a'], array_column($comparison['videos']['lost'], 'provider_video_id'));
        $this->assertSame(['video-b'], array_column($comparison['videos']['retained'], 'provider_video_id'));
        $this->assertSame(20.0, $comparison['videos']['retained'][0]['views_per_day']['delta']);
        $this->assertSame(['channel-c'], array_column($comparison['channels']['new'], 'provider_channel_id'));
        $this->assertSame(['channel-a'], array_column($comparison['channels']['lost'], 'provider_channel_id'));
        $this->assertSame(['channel-b'], array_column($comparison['channels']['retained'], 'provider_channel_id'));
        $this->assertSame(500.0, $comparison['channels']['retained'][0]['subscriber_count']['delta']);
        $this->assertSame('video-c', $comparison['videos']['leading_after'][0]['provider_video_id']);
    }

    public function test_parameter_formula_partial_and_missing_value_boundaries_are_explicit(): void
    {
        $owner = User::factory()->create();
        $before = $this->researchRun(
            $owner,
            'Compact Homes',
            'global_en',
            ResearchRunStatus::Completed,
            '2026-08-01 10:00:00',
            parameters: ['search_order' => 'relevance', 'video_duration' => null],
        );
        $after = $this->researchRun(
            $owner,
            'compact homes',
            'global_en',
            ResearchRunStatus::Completed,
            '2026-08-08 10:00:00',
            parameters: ['search_order' => 'date', 'video_duration' => 'short'],
            requestedCount: 50,
            warnings: ['youtube_partial_data'],
        );
        $this->score($before, 50, 'niche-opportunity-v1');
        $this->score($after, 75, 'future-formula-v2');

        $channel = $this->channel('hidden-channel', 'Hidden channel');
        $video = $this->video($channel, 'missing-video', 'Missing metrics');
        $this->snapshot($before, $video, 1, null, null, null, true);
        $this->snapshot($after, $video, 1, 100, 10, null, true);

        $comparison = app(BuildResearchRunComparison::class)->handle($owner, $before, $after);

        $this->assertFalse($comparison['compatibility']['score_comparable']);
        $this->assertSame(
            ['collection_parameters_differ', 'formula_version_differs', 'partial_collection'],
            array_column($comparison['compatibility']['warnings'], 'code'),
        );
        $this->assertSame(
            ['requested_result_count', 'search_order', 'video_duration'],
            array_column($comparison['compatibility']['parameter_changes'], 'field'),
        );
        $this->assertNull($comparison['score_deltas']['overall_score']['delta']);
        $this->assertNull($comparison['score_deltas']['components']['demand_momentum']['delta']);
        $this->assertNull($comparison['metric_deltas']['median_views']['delta']);
        $this->assertNull($comparison['metric_deltas']['median_subscribers']['before']);
        $this->assertNull($comparison['metric_deltas']['median_subscribers']['after']);
    }

    public function test_missing_scores_and_snapshot_values_remain_null_without_blocking_metric_comparison(): void
    {
        $owner = User::factory()->create();
        $before = $this->researchRun($owner, 'Compact Homes', 'global_en', ResearchRunStatus::Completed, '2026-08-01 10:00:00');
        $after = $this->researchRun($owner, 'compact homes', 'global_en', ResearchRunStatus::Completed, '2026-08-08 10:00:00');
        $this->score($after, 65, 'niche-opportunity-v1');

        $channel = $this->channel('missing-channel', 'Missing channel');
        $video = $this->video($channel, 'missing-video', 'Missing video');
        $this->snapshot($before, $video, 1, null, null, null, true);
        $this->snapshot($after, $video, 1, 100, 10, 50);

        $comparison = app(BuildResearchRunComparison::class)->handle($owner, $before, $after);

        $this->assertTrue($comparison['compatibility']['comparable']);
        $this->assertFalse($comparison['compatibility']['score_comparable']);
        $this->assertSame(['score_missing'], array_column($comparison['compatibility']['warnings'], 'code'));
        $this->assertNull($comparison['score_deltas']['overall_score']['before']);
        $this->assertNull($comparison['score_deltas']['overall_score']['after']);
        $this->assertNull($comparison['score_deltas']['overall_score']['delta']);
        $this->assertNull($comparison['score_deltas']['components']['creator_viability']['delta']);
        $this->assertSame(0.0, $comparison['metric_deltas']['video_count']['delta']);
        $this->assertNull($comparison['metric_deltas']['median_views']['before']);
        $this->assertSame(100.0, $comparison['metric_deltas']['median_views']['after']);
        $this->assertNull($comparison['metric_deltas']['median_views']['delta']);
        $this->assertNull($comparison['metric_deltas']['median_subscribers']['delta']);
        $this->assertNull($comparison['videos']['retained'][0]['views_per_day']['delta']);
        $this->assertNull($comparison['channels']['retained'][0]['subscriber_count']['delta']);
    }

    public function test_delta_math_is_after_minus_before_with_exact_percentages_and_zero_baseline_protection(): void
    {
        $owner = User::factory()->create();
        $before = $this->researchRun($owner, 'Compact Homes', 'global_en', ResearchRunStatus::Completed, '2026-08-01 10:00:00');
        $after = $this->researchRun($owner, 'compact homes', 'global_en', ResearchRunStatus::Completed, '2026-08-08 10:00:00');
        $this->score($before, 0, 'niche-opportunity-v1', [10, 20, 30, 40, 50], 80);
        $this->score($after, 25, 'niche-opportunity-v1', [20, 10, 45, 40, 25], 60);

        $channel = $this->channel('retained-channel', 'Retained channel');
        $video = $this->video($channel, 'retained-video', 'Retained video');
        $this->snapshot($before, $video, 5, 100, 10, 1000);
        $this->snapshot($after, $video, 2, 50, 15, 500);

        $comparison = app(BuildResearchRunComparison::class)->handle($owner, $before, $after);

        $this->assertSame(25.0, $comparison['score_deltas']['overall_score']['delta']);
        $this->assertNull($comparison['score_deltas']['overall_score']['percent_change']);
        $this->assertSame(-20.0, $comparison['score_deltas']['confidence_score']['delta']);
        $this->assertSame(-25.0, $comparison['score_deltas']['confidence_score']['percent_change']);
        $this->assertSame(10.0, $comparison['score_deltas']['components']['demand_momentum']['delta']);
        $this->assertSame(-10.0, $comparison['score_deltas']['components']['competition_opportunity']['delta']);
        $this->assertSame(15.0, $comparison['score_deltas']['components']['audience_reachability']['delta']);
        $this->assertSame(0.0, $comparison['score_deltas']['components']['content_freshness_gap']['delta']);
        $this->assertSame(-25.0, $comparison['score_deltas']['components']['creator_viability']['delta']);
        $this->assertSame(-50.0, $comparison['metric_deltas']['median_views']['delta']);
        $this->assertSame(-50.0, $comparison['metric_deltas']['median_views']['percent_change']);
        $this->assertSame(5.0, $comparison['metric_deltas']['median_views_per_day']['delta']);
        $this->assertSame(50.0, $comparison['metric_deltas']['median_views_per_day']['percent_change']);
        $this->assertSame(-500.0, $comparison['metric_deltas']['median_subscribers']['delta']);
        $this->assertSame(5, $comparison['videos']['retained'][0]['before_rank']);
        $this->assertSame(2, $comparison['videos']['retained'][0]['after_rank']);
        $this->assertSame(5.0, $comparison['videos']['retained'][0]['views_per_day']['delta']);
        $this->assertSame(-500.0, $comparison['channels']['retained'][0]['subscriber_count']['delta']);
    }

    public function test_comparison_rejects_foreign_incomplete_query_and_market_pairs(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $valid = $this->researchRun($owner, 'Compact Homes', 'global_en', ResearchRunStatus::Completed, '2026-08-08 10:00:00');
        $foreign = $this->researchRun($otherUser, 'Compact Homes', 'global_en', ResearchRunStatus::Completed, '2026-08-07 10:00:00');

        try {
            app(BuildResearchRunComparison::class)->handle($owner, $valid, $foreign);
            $this->fail('A foreign run was compared.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        try {
            app(ListComparableResearchRuns::class)->handle($owner, $foreign);
            $this->fail('A foreign run was used as a comparison anchor.');
        } catch (AuthorizationException) {
            $this->addToAssertionCount(1);
        }

        foreach ([
            $valid,
            $this->researchRun($owner, 'Compact Homes', 'global_en', ResearchRunStatus::Searching, '2026-08-06 10:00:00'),
            $this->researchRun($owner, 'Different query', 'global_en', ResearchRunStatus::Completed, '2026-08-05 10:00:00'),
            $this->researchRun($owner, 'Compact Homes', 'ro_ro', ResearchRunStatus::Completed, '2026-08-04 10:00:00'),
            $this->researchRun(
                $owner,
                'Compact Homes',
                'global_en',
                ResearchRunStatus::Completed,
                '2026-08-03 10:00:00',
                kind: ResearchRunKind::DiscoveryValidation,
            ),
        ] as $incompatible) {
            try {
                app(BuildResearchRunComparison::class)->handle($owner, $valid, $incompatible);
                $this->fail('An incompatible run was compared.');
            } catch (DomainException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @param  list<string>  $warnings
     */
    private function researchRun(
        User $user,
        string $queryText,
        string $marketKey,
        ResearchRunStatus $status,
        string $completedAt,
        array $parameters = ['search_order' => 'relevance'],
        int $requestedCount = 25,
        array $warnings = [],
        ResearchRunKind $kind = ResearchRunKind::Search,
    ): ResearchRun {
        $market = Market::query()->where('key', $marketKey)->firstOrFail();
        $query = ResearchQuery::query()->create([
            'user_id' => $user->id,
            'market_id' => $market->id,
            'query_text' => $queryText,
        ]);
        $collectionRun = $user->collectionRuns()->create([
            'provider' => 'youtube',
            'kind' => CollectionRunKind::SearchEnrichment,
            'status' => $status === ResearchRunStatus::Completed
                ? CollectionRunStatus::Completed
                : CollectionRunStatus::Queued,
            'attempt_number' => 1,
            'frozen_request' => [
                'query_text' => $queryText,
                'market_key' => $marketKey,
                'parameters' => $parameters,
            ],
            'cache_policy' => CollectionCachePolicy::FreshOnly,
            'requested_count' => $requestedCount,
            'processed_count' => $status === ResearchRunStatus::Completed ? $requestedCount : 0,
            'progress_percent' => $status === ResearchRunStatus::Completed ? 100 : 0,
            'completed_at' => $status === ResearchRunStatus::Completed ? $completedAt : null,
        ]);

        return ResearchRun::query()->create([
            'user_id' => $user->id,
            'research_query_id' => $query->id,
            'collection_run_id' => $collectionRun->id,
            'kind' => $kind,
            'status' => $status,
            'attempt_number' => 1,
            'query_text' => $query->query_text,
            'market_key' => $market->key,
            'region_code' => $market->region_code,
            'relevance_language' => $market->relevance_language,
            'parameters' => $parameters,
            'requested_result_count' => $requestedCount,
            'collected_result_count' => $status === ResearchRunStatus::Completed ? $requestedCount : 5,
            'enriched_result_count' => $status === ResearchRunStatus::Completed ? $requestedCount : 0,
            'progress_percent' => $status === ResearchRunStatus::Completed ? 100 : 25,
            'collection_warnings' => $warnings,
            'completed_at' => $status === ResearchRunStatus::Completed ? $completedAt : null,
        ]);
    }

    /** @param list<float> $components */
    private function score(
        ResearchRun $run,
        float $overall,
        string $formula,
        array $components = [50, 50, 50, 50, 50],
        float $confidence = 75,
    ): OpportunityScore {
        return OpportunityScore::query()->create([
            'research_run_id' => $run->id,
            'formula_version' => $formula,
            'overall_score' => $overall,
            'demand_momentum_score' => $components[0],
            'competition_opportunity_score' => $components[1],
            'audience_reachability_score' => $components[2],
            'content_freshness_gap_score' => $components[3],
            'creator_viability_score' => $components[4],
            'confidence_score' => $confidence,
            'sample_size' => 25,
            'input_summary' => [],
            'explanations' => [],
            'warnings' => [],
            'calculated_at' => $run->completed_at,
        ]);
    }

    private function channel(string $providerId, string $title): Channel
    {
        return Channel::query()->create([
            'provider' => 'youtube',
            'provider_channel_id' => $providerId,
            'title' => $title,
        ]);
    }

    private function video(Channel $channel, string $providerId, string $title): Video
    {
        return Video::query()->create([
            'provider' => 'youtube',
            'provider_video_id' => $providerId,
            'channel_id' => $channel->id,
            'title' => $title,
            'published_at' => '2026-07-01 00:00:00',
        ]);
    }

    private function snapshot(
        ResearchRun $run,
        Video $video,
        int $rank,
        ?int $views,
        ?float $viewsPerDay,
        ?int $subscribers,
        bool $subscribersHidden = false,
    ): void {
        $run->videos()->attach($video->id, [
            'result_rank' => $rank,
            'page_number' => 1,
            'provider_order' => $rank,
        ]);
        $videoSnapshot = VideoSnapshot::query()->create([
            'research_run_id' => $run->id,
            'collection_run_id' => $run->collection_run_id,
            'video_id' => $video->id,
            'view_count' => $views,
            'like_count' => $views === null ? null : (int) ($views * 0.1),
            'comment_count' => $views === null ? null : (int) ($views * 0.01),
            'age_seconds' => 864000,
            'views_per_day' => $viewsPerDay,
            'views_to_subscribers_ratio' => $views !== null && $subscribers !== null && $subscribers > 0
                ? $views / $subscribers
                : null,
            'collected_at' => $run->completed_at,
        ]);
        $channelSnapshot = ChannelSnapshot::query()->firstOrCreate([
            'research_run_id' => $run->id,
            'channel_id' => $video->channel_id,
        ], [
            'collection_run_id' => $run->collection_run_id,
            'subscriber_count' => $subscribers,
            'view_count' => $views === null ? null : $views * 100,
            'video_count' => 20,
            'subscriber_count_hidden' => $subscribersHidden,
            'collected_at' => $run->completed_at,
        ]);
        $run->videoMemberships()
            ->where('video_id', $video->id)
            ->firstOrFail()
            ->pinSources($videoSnapshot, $channelSnapshot);
    }
}
