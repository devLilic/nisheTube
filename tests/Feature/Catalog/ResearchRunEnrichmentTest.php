<?php

namespace Tests\Feature\Catalog;

use App\Domain\Research\Actions\CreateResearchQuery;
use App\Domain\Research\Actions\CreateResearchRun;
use App\Domain\Research\Actions\MarkResearchRunSearchComplete;
use App\Domain\Research\Actions\TransitionResearchRun;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Research\Enums\SearchOrder;
use App\Domain\YouTube\Enums\QuotaUsageOutcome;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Jobs\Research\EnrichResearchRun;
use App\Jobs\Research\ScoreResearchRun;
use App\Models\ApiUsageEvent;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\Market;
use App\Models\ResearchRun;
use App\Models\ResearchRunSearchResult;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use Carbon\CarbonImmutable;
use Database\Seeders\MarketSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ResearchRunEnrichmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(MarketSeeder::class);
        config()->set('youtube.api_key', 'enrichment-test-secret');
        config()->set('youtube.max_attempts', 1);
        config()->set('youtube.retry_delay_milliseconds', 0);
        Queue::fake([ScoreResearchRun::class]);
        CarbonImmutable::setTestNow('2026-08-08 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_job_batches_video_and_channel_enrichment_and_persists_derived_metrics(): void
    {
        $run = $this->searchingRunWithResults(75);
        $requests = [];

        Http::fake(function (Request $request) use (&$requests) {
            $endpoint = str_contains($request->url(), '/videos') ? 'videos.list' : 'channels.list';
            $ids = explode(',', (string) $request['id']);
            $requests[] = [
                'endpoint' => $endpoint,
                'ids' => $ids,
                'part' => $request['part'],
                'max_results' => $request->data()['maxResults'] ?? null,
            ];

            return $endpoint === 'videos.list'
                ? Http::response(['items' => $this->videoItems($ids)])
                : Http::response(['items' => $this->channelItems($ids)]);
        });

        app()->call([new EnrichResearchRun($run->id), 'handle']);

        $run->refresh();
        $firstVideo = Video::query()->where('provider_video_id', 'video-1')->firstOrFail();
        $firstVideoSnapshot = VideoSnapshot::query()->where('video_id', $firstVideo->id)->firstOrFail();
        $firstChannel = Channel::query()->where('provider_channel_id', 'channel-1')->firstOrFail();
        $firstChannelSnapshot = ChannelSnapshot::query()->where('channel_id', $firstChannel->id)->firstOrFail();

        $this->assertSame(ResearchRunStatus::Scoring, $run->status);
        $this->assertSame(75, $run->enriched_result_count);
        $this->assertSame(90, $run->progress_percent);
        $this->assertDatabaseCount('videos', 75);
        $this->assertDatabaseCount('channels', 75);
        $this->assertDatabaseCount('research_run_videos', 75);
        $this->assertDatabaseCount('video_snapshots', 75);
        $this->assertDatabaseCount('channel_snapshots', 75);
        $this->assertSame('Enriched video 1', $firstVideo->title);
        $this->assertSame(625, $firstVideo->duration_seconds);
        $this->assertNull($firstVideo->is_short);
        $this->assertSame(86400, $firstVideoSnapshot->age_seconds);
        $this->assertSame('1000.000000', $firstVideoSnapshot->views_per_day);
        $this->assertSame('10.00000000', $firstVideoSnapshot->views_to_subscribers_ratio);
        $this->assertSame('2026-08-08 12:00:00', $firstVideoSnapshot->collected_at->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-08 12:00:00', $firstChannelSnapshot->collected_at->format('Y-m-d H:i:s'));
        $this->assertSame(['uploads_playlist_id' => 'uploads-1', 'published_at' => '2020-01-01T00:00:00Z'], $firstChannelSnapshot->metadata);
        $this->assertCount(4, $requests);
        $this->assertSame([50, 50, 25, 25], array_map(fn (array $request): int => count($request['ids']), $requests));
        $this->assertSame([
            'videos.list',
            'channels.list',
            'videos.list',
            'channels.list',
        ], array_column($requests, 'endpoint'));
        $this->assertSame([
            'snippet,contentDetails,statistics',
            'snippet,contentDetails,statistics',
            'snippet,contentDetails,statistics',
            'snippet,contentDetails,statistics',
        ], array_column($requests, 'part'));
        $this->assertSame([null, 50, null, 25], array_column($requests, 'max_results'));
        $this->assertDatabaseCount('api_usage_events', 4);
        $this->assertSame(4, ApiUsageEvent::query()->where('research_run_id', $run->id)->count());
        $this->assertSame(4, ApiUsageEvent::query()->where('user_id', $run->user_id)->count());
        $this->assertSame(['channels.list', 'videos.list'], ApiUsageEvent::query()->distinct()->orderBy('endpoint')->pluck('endpoint')->all());
        Queue::assertPushed(ScoreResearchRun::class, fn (ScoreResearchRun $job): bool => $job->researchRunId === $run->id);
    }

    public function test_missing_and_hidden_provider_metrics_remain_null_with_safe_warnings(): void
    {
        $run = $this->searchingRunWithResults(1);

        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/videos')) {
                return Http::response(['items' => [[
                    'id' => 'video-1',
                    'snippet' => [
                        'channelId' => 'channel-1',
                        'channelTitle' => 'Hidden Metrics Channel',
                        'title' => 'Sparse video',
                        'publishedAt' => '2026-08-07T12:00:00Z',
                    ],
                    'contentDetails' => [],
                    'statistics' => ['viewCount' => '100'],
                ]]]);
            }

            return Http::response(['items' => [[
                'id' => 'channel-1',
                'snippet' => ['title' => 'Hidden Metrics Channel'],
                'contentDetails' => [],
                'statistics' => [
                    'hiddenSubscriberCount' => true,
                    'viewCount' => '5000',
                    'videoCount' => '12',
                ],
            ]]]);
        });

        app()->call([new EnrichResearchRun($run->id), 'handle']);

        $run->refresh();
        $video = Video::query()->sole();
        $videoSnapshot = VideoSnapshot::query()->sole();
        $channelSnapshot = ChannelSnapshot::query()->sole();

        $this->assertSame(ResearchRunStatus::Scoring, $run->status);
        $this->assertNull($video->duration_seconds);
        $this->assertNull($videoSnapshot->like_count);
        $this->assertNull($videoSnapshot->comment_count);
        $this->assertNull($videoSnapshot->views_to_subscribers_ratio);
        $this->assertNull($channelSnapshot->subscriber_count);
        $this->assertTrue($channelSnapshot->subscriber_count_hidden);
        $this->assertContains(
            'Some video statistics were unavailable and were stored as missing.',
            $run->collection_warnings,
        );
        $this->assertContains(
            'Some channels hide subscriber counts; those values were stored as missing.',
            $run->collection_warnings,
        );
        $this->assertStringNotContainsString('enrichment-test-secret', $run->toJson());
    }

    public function test_retry_resumes_from_persisted_batches_without_duplicate_snapshots(): void
    {
        $run = $this->searchingRunWithResults(51);
        $failVideo51 = true;
        $retriedVideoIds = [];

        Http::fake(function (Request $request) use (&$failVideo51, &$retriedVideoIds) {
            $ids = explode(',', (string) $request['id']);

            if (str_contains($request->url(), '/videos')) {
                if ($failVideo51 && in_array('video-51', $ids, true)) {
                    return Http::response(['error' => ['errors' => [['reason' => 'backendError']]]], 500);
                }

                if (! $failVideo51) {
                    $retriedVideoIds = [...$retriedVideoIds, ...$ids];
                }

                return Http::response(['items' => $this->videoItems($ids)]);
            }

            return Http::response(['items' => $this->channelItems($ids)]);
        });

        try {
            app()->call([new EnrichResearchRun($run->id), 'handle']);
            $this->fail('The transient second-batch failure should return the job to the queue.');
        } catch (YouTubeProviderException $exception) {
            $this->assertSame(YouTubeErrorCode::Unavailable, $exception->providerCode);
        }

        $this->assertSame(ResearchRunStatus::Enriching, $run->fresh()->status);
        $this->assertDatabaseCount('video_snapshots', 50);
        $this->assertDatabaseCount('channel_snapshots', 50);
        $failVideo51 = false;

        app()->call([new EnrichResearchRun($run->id), 'handle']);

        $this->assertSame(['video-51'], $retriedVideoIds);
        $this->assertSame(ResearchRunStatus::Scoring, $run->fresh()->status);
        $this->assertDatabaseCount('videos', 51);
        $this->assertDatabaseCount('research_run_videos', 51);
        $this->assertDatabaseCount('video_snapshots', 51);
        $this->assertDatabaseCount('channel_snapshots', 51);
        $this->assertSame(5, ApiUsageEvent::query()->count());

        app()->call([new EnrichResearchRun($run->id), 'handle']);

        $this->assertDatabaseCount('video_snapshots', 51);
        $this->assertDatabaseCount('channel_snapshots', 51);
        $this->assertSame(5, ApiUsageEvent::query()->count());
    }

    public function test_a_later_enrichment_refresh_reuses_catalog_entities_and_preserves_both_run_snapshots(): void
    {
        $firstRun = $this->searchingRunWithResults(1);
        $viewCount = 1000;
        $subscriberCount = 100;

        Http::fake(function (Request $request) use (&$viewCount, &$subscriberCount) {
            if (str_contains($request->url(), '/videos')) {
                $item = $this->videoItems(['video-1'])[0];
                $item['statistics']['viewCount'] = (string) $viewCount;

                return Http::response(['items' => [$item]]);
            }

            $item = $this->channelItems(['channel-1'])[0];
            $item['statistics']['subscriberCount'] = (string) $subscriberCount;

            return Http::response(['items' => [$item]]);
        });

        app()->call([new EnrichResearchRun($firstRun->id), 'handle']);

        $firstVideoSnapshot = VideoSnapshot::query()
            ->where('research_run_id', $firstRun->id)
            ->sole();
        $firstChannelSnapshot = ChannelSnapshot::query()
            ->where('research_run_id', $firstRun->id)
            ->sole();

        CarbonImmutable::setTestNow('2026-08-09 12:00:00 UTC');
        $viewCount = 3000;
        $subscriberCount = 200;
        $secondRun = $this->searchingRunWithResults(1);

        app()->call([new EnrichResearchRun($secondRun->id), 'handle']);

        $secondVideoSnapshot = VideoSnapshot::query()
            ->where('research_run_id', $secondRun->id)
            ->sole();
        $secondChannelSnapshot = ChannelSnapshot::query()
            ->where('research_run_id', $secondRun->id)
            ->sole();

        $this->assertDatabaseCount('videos', 1);
        $this->assertDatabaseCount('channels', 1);
        $this->assertDatabaseCount('video_snapshots', 2);
        $this->assertDatabaseCount('channel_snapshots', 2);
        $this->assertSame(1000, $firstVideoSnapshot->fresh()->view_count);
        $this->assertSame('1000.000000', $firstVideoSnapshot->fresh()->views_per_day);
        $this->assertSame('10.00000000', $firstVideoSnapshot->fresh()->views_to_subscribers_ratio);
        $this->assertSame(100, $firstChannelSnapshot->fresh()->subscriber_count);
        $this->assertSame('2026-08-08 12:00:00', $firstVideoSnapshot->fresh()->collected_at->format('Y-m-d H:i:s'));
        $this->assertSame(3000, $secondVideoSnapshot->view_count);
        $this->assertSame('1500.000000', $secondVideoSnapshot->views_per_day);
        $this->assertSame('15.00000000', $secondVideoSnapshot->views_to_subscribers_ratio);
        $this->assertSame(200, $secondChannelSnapshot->subscriber_count);
        $this->assertSame('2026-08-09 12:00:00', $secondVideoSnapshot->collected_at->format('Y-m-d H:i:s'));
    }

    public function test_non_retryable_enrichment_failure_preserves_the_run_with_safe_guidance(): void
    {
        $run = $this->searchingRunWithResults(1);

        Http::fake([
            'https://www.googleapis.com/youtube/v3/videos*' => Http::response([
                'error' => [
                    'errors' => [['reason' => 'quotaExceeded']],
                    'message' => 'Unsafe enrichment-test-secret detail',
                ],
            ], 403),
        ]);

        app()->call([new EnrichResearchRun($run->id), 'handle']);

        $run->refresh();
        $event = ApiUsageEvent::query()->sole();

        $this->assertSame(ResearchRunStatus::Failed, $run->status);
        $this->assertSame(YouTubeErrorCode::QuotaExhausted->value, $run->error_code);
        $this->assertSame(YouTubeErrorCode::QuotaExhausted->safeMessage(), $run->error_message);
        $this->assertStringNotContainsString('enrichment-test-secret', $run->toJson());
        $this->assertSame('videos.list', $event->endpoint);
        $this->assertSame('general', $event->quota_bucket);
        $this->assertSame(QuotaUsageOutcome::Failed, $event->outcome);
        $this->assertDatabaseCount('video_snapshots', 0);
        $this->assertDatabaseCount('channel_snapshots', 0);
    }

    private function searchingRunWithResults(int $count): ResearchRun
    {
        $user = User::factory()->create();
        $market = Market::query()->where('key', 'global_en')->firstOrFail();
        $query = app(CreateResearchQuery::class)->handle(
            user: $user,
            market: $market,
            queryText: 'camera research',
            searchOrder: SearchOrder::ViewCount,
        );
        $run = app(CreateResearchRun::class)->handle($user, $query, $count);
        $transition = app(TransitionResearchRun::class);
        $run = $transition->handle($run, ResearchRunStatus::Queued);
        $run = $transition->handle($run, ResearchRunStatus::Searching);

        for ($number = 1; $number <= $count; $number++) {
            ResearchRunSearchResult::query()->create([
                'research_run_id' => $run->id,
                'provider_video_id' => "video-{$number}",
                'provider_channel_id' => "channel-{$number}",
                'title' => "Search video {$number}",
                'published_at' => '2026-08-07 12:00:00',
                'result_rank' => $number,
                'page_number' => (int) ceil($number / 50),
                'provider_order' => (($number - 1) % 50) + 1,
            ]);
        }

        return app(MarkResearchRunSearchComplete::class)->handle($run);
    }

    /**
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    private function videoItems(array $ids): array
    {
        return array_map(function (string $id): array {
            $number = (int) str_replace('video-', '', $id);

            return [
                'id' => $id,
                'snippet' => [
                    'channelId' => "channel-{$number}",
                    'channelTitle' => "Channel {$number}",
                    'title' => "Enriched video {$number}",
                    'publishedAt' => '2026-08-07T12:00:00Z',
                    'categoryId' => '26',
                    'thumbnails' => ['high' => ['url' => "https://example.test/video-{$number}.jpg"]],
                ],
                'contentDetails' => ['duration' => 'PT10M25S'],
                'statistics' => [
                    'viewCount' => (string) ($number * 1000),
                    'likeCount' => (string) ($number * 50),
                    'commentCount' => (string) ($number * 5),
                ],
            ];
        }, $ids);
    }

    /**
     * @param  list<string>  $ids
     * @return list<array<string, mixed>>
     */
    private function channelItems(array $ids): array
    {
        return array_map(function (string $id): array {
            $number = (int) str_replace('channel-', '', $id);

            return [
                'id' => $id,
                'snippet' => [
                    'title' => "Enriched channel {$number}",
                    'customUrl' => "@channel{$number}",
                    'country' => 'ro',
                    'publishedAt' => '2020-01-01T00:00:00Z',
                    'thumbnails' => ['high' => ['url' => "https://example.test/channel-{$number}.jpg"]],
                ],
                'contentDetails' => ['relatedPlaylists' => ['uploads' => "uploads-{$number}"]],
                'statistics' => [
                    'subscriberCount' => (string) ($number * 100),
                    'viewCount' => (string) ($number * 10000),
                    'videoCount' => (string) ($number * 10),
                    'hiddenSubscriberCount' => false,
                ],
            ];
        }, $ids);
    }
}
