<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Actions\CreateResearchCollectionRun;
use App\Domain\Collection\Actions\PersistCollectionObservationBatch;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\YouTube\Contracts\ChannelUploadsProvider;
use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\ChannelDetails;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\ChannelUploadsPage;
use App\Domain\YouTube\Data\ChannelUploadsRequest;
use App\Domain\YouTube\Data\VideoDetails;
use App\Domain\YouTube\Data\VideoDetailsBatch;
use App\Domain\YouTube\Data\VideoSearchPage;
use App\Domain\YouTube\Data\VideoSearchRequest;
use App\Domain\YouTube\Data\YouTubeIdBatchRequest;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use App\Domain\YouTube\Exceptions\YouTubeProviderException;
use App\Jobs\Analyzer\CollectAnalyzerRun;
use App\Models\AnalyzerRun;
use App\Models\CollectionRun;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AnalyzerCollectionProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Date::setTestNow('2026-08-09 12:00:00 UTC');
    }

    protected function tearDown(): void
    {
        Date::setTestNow();
        parent::tearDown();
    }

    public function test_fresh_collection_pins_anchor_channel_category_metrics_and_first_seen_state(): void
    {
        $provider = new AnalyzerFakeProvider;
        $this->app->instance(VideoResearchProvider::class, $provider);
        $owner = User::factory()->create();
        $run = $this->newRun($owner, CollectionCachePolicy::AllowFreshCache);

        $this->runJob($run);

        $run->refresh();
        $this->assertSame(AnalyzerRunStatus::Completed, $run->status);
        $this->assertSame(100, $run->progress_percent);
        $this->assertSame(1, $provider->videoCalls);
        $this->assertSame(1, $provider->channelCalls);
        $this->assertDatabaseHas('analyzer_run_videos', ['analyzer_run_id' => $run->id, 'role' => 'anchor']);
        $this->assertDatabaseHas('video_categories', ['category_id' => '26', 'name' => 'Howto & Style']);
        $this->assertDatabaseHas('video_analysis_metrics', [
            'analyzer_run_id' => $run->id,
            'lifetime_views_per_day' => '1000.000000',
            'views_to_subscribers_ratio' => '10.00000000',
            'public_engagement_rate_percent' => '5.500000',
        ]);
        $this->assertDatabaseHas('user_entity_observations', [
            'user_id' => $owner->id,
            'subject_type' => 'video',
            'first_observed_count' => 1000,
        ]);
        $this->assertDatabaseHas('user_entity_observations', [
            'user_id' => $owner->id,
            'subject_type' => 'channel',
            'first_observed_count' => 100,
        ]);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.video.title', 'Anchor video')
                ->where('run.video.category.name', 'Howto & Style')
                ->where('run.video.source_mode', 'fresh')
                ->where('run.channel.title', 'Author channel')
                ->where('run.metrics.lifetime_views_per_day', 1000)
                ->where('run.metrics.public_engagement_rate_percent', 5.5));
    }

    public function test_allow_cache_reuses_only_the_owners_original_observation_without_provider_calls(): void
    {
        $owner = User::factory()->create();
        $source = $this->completedSource($owner);
        $provider = new AnalyzerFakeProvider;
        $this->app->instance(VideoResearchProvider::class, $provider);
        $run = $this->newRun($owner, CollectionCachePolicy::AllowFreshCache);

        $this->runJob($run);

        $run->refresh();
        $membership = $run->videoMemberships()->firstOrFail();
        $this->assertSame(AnalyzerRunStatus::Completed, $run->status);
        $this->assertSame(0, $provider->videoCalls);
        $this->assertSame(0, $provider->channelCalls);
        $this->assertSame($source->id, $membership->videoSnapshot->collection_run_id);
        $this->assertNotSame($run->collection_run_id, $membership->videoSnapshot->collection_run_id);
        $this->assertStringContainsString('original observation time', implode(' ', $run->warnings ?? []));

        $this->actingAs($owner)->get(route('analyzer.runs.show', $run))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.video.source_mode', 'cached')
                ->where('run.video.observed_at', '2026-08-09T12:00:00+00:00'));
    }

    public function test_force_refresh_bypasses_cache_and_creates_new_immutable_observations(): void
    {
        $owner = User::factory()->create();
        $source = $this->completedSource($owner);
        $provider = new AnalyzerFakeProvider;
        $this->app->instance(VideoResearchProvider::class, $provider);
        Date::setTestNow('2026-08-09 13:00:00 UTC');
        $run = $this->newRun($owner, CollectionCachePolicy::ForceRefresh);

        $this->runJob($run);

        $membership = $run->fresh()->videoMemberships()->firstOrFail();
        $this->assertSame(1, $provider->videoCalls);
        $this->assertSame(1, $provider->channelCalls);
        $this->assertNotSame($source->id, $membership->videoSnapshot->collection_run_id);
        $this->assertSame($run->collection_run_id, $membership->videoSnapshot->collection_run_id);
        $this->assertDatabaseCount('video_snapshots', 2);
        $this->assertDatabaseCount('channel_snapshots', 2);
    }

    public function test_missing_anchor_quota_failure_and_missing_channel_have_safe_distinct_states(): void
    {
        $owner = User::factory()->create();
        $missing = $this->newRun($owner, CollectionCachePolicy::ForceRefresh, 'missing0001');
        $missingProvider = new AnalyzerFakeProvider(videoMode: 'missing');
        $this->app->instance(VideoResearchProvider::class, $missingProvider);
        $this->runJob($missing);
        $this->assertSame('youtube_video_not_found', $missing->fresh()->error_code);

        $quota = $this->newRun($owner, CollectionCachePolicy::ForceRefresh, 'quota000001');
        $quotaProvider = new AnalyzerFakeProvider(videoMode: 'quota');
        $this->app->instance(VideoResearchProvider::class, $quotaProvider);
        $this->runJob($quota);
        $this->assertSame('youtube_quota_exhausted', $quota->fresh()->error_code);

        $partial = $this->newRun($owner, CollectionCachePolicy::ForceRefresh, 'partial0001');
        $partialProvider = new AnalyzerFakeProvider(channelMissing: true, videoId: 'partial0001');
        $this->app->instance(VideoResearchProvider::class, $partialProvider);
        $this->runJob($partial);
        $partial->refresh();
        $this->assertSame(AnalyzerRunStatus::Completed, $partial->status);
        $this->assertNull($partial->videoMemberships()->firstOrFail()->channel_snapshot_id);
        $this->assertNotEmpty($partial->warnings);
    }

    public function test_duplicate_job_delivery_does_not_duplicate_sources_metrics_or_observation_state(): void
    {
        $provider = new AnalyzerFakeProvider;
        $this->app->instance(VideoResearchProvider::class, $provider);
        $owner = User::factory()->create();
        $run = $this->newRun($owner, CollectionCachePolicy::ForceRefresh);

        $this->runJob($run);
        $this->runJob($run);

        $this->assertDatabaseCount('analyzer_run_videos', 1);
        $this->assertDatabaseCount('video_analysis_metrics', 1);
        $this->assertDatabaseCount('user_entity_observations', 2);
        $this->assertSame(1, $provider->videoCalls);
        $this->assertSame(1, $provider->channelCalls);
    }

    private function newRun(
        User $user,
        CollectionCachePolicy $policy,
        string $videoId = 'dQw4w9WgXcQ',
    ): AnalyzerRun {
        return app(CreateAnalyzerRun::class)->handle($user, $videoId, $policy);
    }

    private function runJob(AnalyzerRun $run): void
    {
        $provider = $this->app->make(VideoResearchProvider::class);

        if ($provider instanceof ChannelUploadsProvider) {
            $this->app->instance(ChannelUploadsProvider::class, $provider);
        }

        $this->app->call([new CollectAnalyzerRun($run->id), 'handle']);
    }

    private function completedSource(User $owner): CollectionRun
    {
        $run = app(CreateResearchCollectionRun::class)->handle(
            user: $owner,
            attemptNumber: 1,
            requestedCount: 1,
            frozenRequest: ['target' => 'dQw4w9WgXcQ'],
        );
        $run->update(['status' => CollectionRunStatus::Collecting, 'started_at' => now()]);
        app(PersistCollectionObservationBatch::class)->handle(
            $run->fresh(),
            AnalyzerFakeProvider::videoBatch('dQw4w9WgXcQ'),
            AnalyzerFakeProvider::channelBatch(),
        );
        $run->update([
            'status' => CollectionRunStatus::Completed,
            'processed_count' => 1,
            'progress_percent' => 100,
            'completed_at' => now(),
        ]);

        return $run->fresh();
    }
}

final class AnalyzerFakeProvider implements ChannelUploadsProvider, VideoResearchProvider
{
    public int $videoCalls = 0;

    public int $channelCalls = 0;

    public function __construct(
        private readonly string $videoMode = 'success',
        private readonly bool $channelMissing = false,
        private readonly string $videoId = 'dQw4w9WgXcQ',
    ) {}

    public function search(VideoSearchRequest $request): VideoSearchPage
    {
        throw new \LogicException('Analyzer must not call search.list.');
    }

    public function fetchVideos(YouTubeIdBatchRequest $request): VideoDetailsBatch
    {
        $this->videoCalls++;

        if ($this->videoMode === 'quota') {
            throw new YouTubeProviderException(YouTubeErrorCode::QuotaExhausted);
        }

        return $this->videoMode === 'missing'
            ? new VideoDetailsBatch([], new DateTimeImmutable('2026-08-09 12:00:00 UTC'), ['YouTube omitted the requested video.'])
            : self::videoBatch($this->videoId);
    }

    public function fetchChannels(YouTubeIdBatchRequest $request): ChannelDetailsBatch
    {
        $this->channelCalls++;

        return $this->channelMissing
            ? new ChannelDetailsBatch([], new DateTimeImmutable('2026-08-09 12:00:00 UTC'), ['YouTube omitted details for 1 requested channel.'])
            : self::channelBatch();
    }

    public function listUploads(ChannelUploadsRequest $request): ChannelUploadsPage
    {
        return new ChannelUploadsPage([]);
    }

    public static function videoBatch(string $videoId): VideoDetailsBatch
    {
        return new VideoDetailsBatch([
            new VideoDetails(
                videoId: $videoId,
                channelId: 'channel000001',
                channelTitle: 'Author channel',
                title: 'Anchor video',
                thumbnailUrl: 'https://images.example.test/anchor.jpg',
                publishedAt: new DateTimeImmutable('2026-08-08 12:00:00 UTC'),
                durationSeconds: 600,
                categoryId: '26',
                isShort: false,
                viewCount: 1000,
                likeCount: 50,
                commentCount: 5,
            ),
        ], new DateTimeImmutable('2026-08-09 12:00:00 UTC'));
    }

    public static function channelBatch(): ChannelDetailsBatch
    {
        return new ChannelDetailsBatch([
            new ChannelDetails(
                channelId: 'channel000001',
                title: 'Author channel',
                customUrl: '@author',
                thumbnailUrl: 'https://images.example.test/channel.jpg',
                country: 'RO',
                subscriberCount: 100,
                viewCount: 10_000,
                videoCount: 20,
                subscriberCountHidden: false,
                metadata: ['uploads_playlist_id' => 'UUchannel000001'],
            ),
        ], new DateTimeImmutable('2026-08-09 12:00:00 UTC'));
    }
}
