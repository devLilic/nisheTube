<?php

namespace Tests\Feature\Analyzer;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Retention\Actions\CreateCleanupRun;
use App\Domain\Retention\Actions\ExecuteCleanup;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Enums\DeletionOutcome;
use App\Domain\Retention\Services\BuildRetentionPlan;
use App\Domain\YouTube\Contracts\ChannelUploadsProvider;
use App\Domain\YouTube\Contracts\VideoResearchProvider;
use App\Domain\YouTube\Data\ChannelDetails;
use App\Domain\YouTube\Data\ChannelDetailsBatch;
use App\Domain\YouTube\Data\ChannelUpload;
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
use App\Jobs\Retention\ExecuteCleanupRun;
use App\Models\AnalyzerRun;
use App\Models\User;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class AnalyzerRecentCohortTest extends TestCase
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

    public function test_paginated_frozen_cohort_is_batched_pinned_and_calculated_with_exact_baselines(): void
    {
        config(['analyzer.recent_video_limit' => 55]);
        $provider = new RecentCohortFakeProvider;
        $owner = User::factory()->create();
        $run = app(CreateAnalyzerRun::class)->handle($owner, RecentCohortFakeProvider::ANCHOR, CollectionCachePolicy::ForceRefresh);
        config(['analyzer.recent_video_limit' => 10]);
        config([
            'analyzer.channel_behavior.momentum_block_size' => 2,
            'analyzer.channel_behavior.momentum_thresholds.stable_max_inclusive' => 0.5,
        ]);
        $this->bind($provider);
        $this->runJob($run);
        $run->refresh();

        $this->assertSame(AnalyzerRunStatus::Completed, $run->status);
        $this->assertSame(55, $run->recent_video_limit);
        $this->assertSame(55, $run->collectionRun->frozen_request['recent_video_limit']);
        $this->assertSame(5, $run->collectionRun->configuration_context['channel_behavior']['momentum_block_size']);
        $this->assertSame([50, 5], $provider->playlistPageSizes);
        $this->assertSame([1, 50, 5], $provider->videoBatchSizes);
        $this->assertDatabaseCount('analyzer_run_cohort_items', 55);
        $this->assertSame(55, $run->videoMemberships()->where('role', 'channel_recent_upload')->count());
        $this->assertDatabaseHas('analyzer_run_videos', [
            'analyzer_run_id' => $run->id,
            'role' => 'channel_recent_upload',
            'source_position' => 55,
        ]);
        $this->assertDatabaseHas('semantic_topic_profiles', [
            'analyzer_run_id' => $run->id,
            'user_id' => $owner->id,
            'provenance' => 'inferred',
            'provider' => 'deterministic_title_terms',
            'algorithm_version' => 'semantic-title-terms-v1',
        ]);
        $this->assertDatabaseHas('channel_analysis_metrics', [
            'analyzer_run_id' => $run->id,
            'recent_valid_count' => 55,
            'recent_requested_count' => 55,
            'coverage_percent' => '100.0000',
            'median_views' => '2800.0000',
            'average_views' => '2800.0000',
            'minimum_views' => 100,
            'maximum_views' => 5500,
            'median_upload_gap_days' => '1.000000',
            'longest_upload_gap_days' => '1.000000',
            'strong_count' => 0,
            'breakout_count' => 0,
            'threshold_version' => 'video-relative-performance-v1',
            'momentum_class' => 'stable',
            'momentum_ratio' => '1.00000000',
            'consistency_class' => 'consistent',
            'consistency_score' => '100.0000',
            'behavior_version' => 'channel-behavior-v1',
            'momentum_recent_count' => 5,
            'momentum_previous_count' => 5,
        ]);
        $this->assertDatabaseHas('video_analysis_metrics', [
            'analyzer_run_id' => $run->id,
            'channel_median_ratio' => '0.03571429',
            'channel_average_ratio' => '0.03571429',
            'recent_rank' => 55,
            'recent_percentile' => '1.7857',
            'recent_comparison_count' => 56,
            'breakout_class' => 'underperformer',
            'threshold_version' => 'video-relative-performance-v1',
        ]);
        $this->assertDatabaseHas('analyzer_run_videos', [
            'analyzer_run_id' => $run->id,
            'source_position' => 55,
            'breakout_class' => 'above_average',
        ]);

        $this->actingAs($run->user)->get(route('analyzer.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.channel_metrics.recent_valid_count', 55)
                ->where('run.channel_metrics.median_views', 2800)
                ->where('run.channel_metrics.strong_share_percent', 0)
                ->where('run.metrics.breakout_class.label', 'Underperformer')
                ->where('run.metrics.recent_rank', 55)
                ->where('run.metrics.recent_comparison_count', 56)
                ->where('run.cohort.collection_complete', true)
                ->where('run.recent_videos.0.source_position', 1)
                ->where('run.recent_videos.54.source_position', 55)
                ->where('run.recent_videos.54.view_count', 5500)
                ->where('run.recent_videos.54.breakout_class.label', 'Above Average')
                ->where('run.relative_context.threshold_version', 'video-relative-performance-v1')
                ->where('run.behavior_context.momentum_block_size', 5)
                ->where('run.behavior_context.growing_above', 1.2)
                ->where('run.topic_profile.provenance', 'inferred')
                ->where('run.topic_profile.algorithm_version', 'semantic-title-terms-v1'));
    }

    public function test_recent_video_window_is_inclusive_at_ninety_days_and_immutable_to_the_attempt(): void
    {
        config(['analyzer.recent_video_limit' => 91]);
        $run = $this->executeRun(User::factory()->create(), new RecentCohortFakeProvider);

        $this->actingAs($run->user)->get(route('analyzer.runs.show', $run))
            ->assertOk()
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.recent_video_window.days', 90)
                ->where('run.recent_video_window.reference_at', '2026-08-09T12:00:00+00:00')
                ->where('run.recent_video_window.starts_at', '2026-05-11T12:00:00+00:00')
                ->where('run.recent_videos.89.source_position', 90)
                ->where('run.recent_videos.89.published_within_recent_window', true)
                ->where('run.recent_videos.90.source_position', 91)
                ->where('run.recent_videos.90.published_within_recent_window', false));
    }

    public function test_refresh_persists_owner_scoped_observed_growth_without_inventing_pre_first_seen_history(): void
    {
        config(['analyzer.recent_video_limit' => 10]);
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $first = $this->executeRun($owner, new RecentCohortFakeProvider(
            viewOverrides: [0 => 100],
            observedAt: new DateTimeImmutable('2026-08-09 12:00:00 UTC'),
            channelViewCount: 100_000,
            channelSubscriberCount: 1_000,
            channelVideoCount: 100,
        ));
        $firstSnapshotId = $first->videoMemberships()->where('role', 'anchor')->sole()->video_snapshot_id;

        Date::setTestNow('2026-08-10 12:00:00 UTC');
        $this->executeRun($other, new RecentCohortFakeProvider(
            viewOverrides: [0 => 999],
            observedAt: new DateTimeImmutable('2026-08-10 12:00:00 UTC'),
        ));

        Date::setTestNow('2026-08-11 12:00:00 UTC');
        $second = $this->executeRun($owner, new RecentCohortFakeProvider(
            viewOverrides: [0 => 300],
            observedAt: new DateTimeImmutable('2026-08-11 12:00:00 UTC'),
            channelViewCount: 103_000,
            channelSubscriberCount: 1_010,
            channelVideoCount: 101,
        ));

        $this->assertDatabaseHas('video_analysis_metrics', [
            'analyzer_run_id' => $second->id,
            'previous_video_snapshot_id' => $firstSnapshotId,
            'observed_elapsed_seconds' => 172800,
            'observed_view_delta' => 200,
            'observed_recent_views_per_day' => '100.000000',
            'observed_view_growth_percent' => '200.000000',
            'behavior_version' => 'channel-behavior-v1',
        ]);
        $this->assertDatabaseHas('channel_analysis_metrics', [
            'analyzer_run_id' => $second->id,
            'observed_elapsed_seconds' => 172800,
            'observed_view_delta' => 3000,
            'observed_subscriber_delta' => 10,
            'observed_video_delta' => 1,
            'observed_view_growth_percent' => '3.000000',
            'behavior_version' => 'channel-behavior-v1',
        ]);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $second))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.video.first_seen_at', '2026-08-09T12:00:00+00:00')
                ->where('run.metrics.observed_recent_views_per_day', 100)
                ->where('run.channel_metrics.momentum_class', 'declining')
                ->where('run.channel_metrics.consistency_class', 'consistent')
                ->where('run.behavior_context.version', 'channel-behavior-v1')
                ->has('run.growth_history.points', 2)
                ->where('run.growth_history.points.0.view_count', 100)
                ->where('run.growth_history.points.1.view_count', 300)
                ->where('run.growth_history.points.1.view_delta', 200)
                ->where('run.growth_history.points.1.observed_recent_views_per_day', 100));

        $cached = app(CreateAnalyzerRun::class)->handle(
            $owner,
            RecentCohortFakeProvider::ANCHOR,
            CollectionCachePolicy::AllowFreshCache,
        );
        $this->bind(new RecentCohortFakeProvider(
            viewOverrides: [0 => 9_999],
            observedAt: new DateTimeImmutable('2026-08-11 13:00:00 UTC'),
        ));
        $this->runJob($cached);
        $cached->refresh();

        $this->assertSame(
            $second->videoMemberships()->where('role', 'anchor')->sole()->video_snapshot_id,
            $cached->videoMemberships()->where('role', 'anchor')->sole()->video_snapshot_id,
        );
        $this->actingAs($owner)->get(route('analyzer.runs.show', $cached))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->has('run.growth_history.points', 2)
                ->where('run.growth_history.points.1.view_count', 300));
    }

    public function test_retry_resumes_after_a_saved_batch_without_duplicate_memberships_or_snapshots(): void
    {
        config(['analyzer.recent_video_limit' => 55]);
        $provider = new RecentCohortFakeProvider(failSecondBatchOnce: true);
        $owner = User::factory()->create();
        $run = app(CreateAnalyzerRun::class)->handle($owner, RecentCohortFakeProvider::ANCHOR, CollectionCachePolicy::ForceRefresh);
        $this->bind($provider);

        try {
            $this->runJob($run);
            $this->fail('The transient cohort failure should be retried by the queue.');
        } catch (YouTubeProviderException $exception) {
            $this->assertSame(YouTubeErrorCode::Unavailable, $exception->providerCode);
        }

        $this->assertSame(AnalyzerRunStatus::LoadingRecentVideos, $run->fresh()->status);
        $this->assertSame(50, $run->videoMemberships()->where('role', 'channel_recent_upload')->count());

        $this->runJob($run);
        $run->refresh();

        $this->assertSame(AnalyzerRunStatus::Completed, $run->status);
        $this->assertSame([1, 50, 5, 5], $provider->videoBatchSizes);
        $this->assertSame(55, $run->videoMemberships()->where('role', 'channel_recent_upload')->count());
        $this->assertDatabaseCount('video_snapshots', 56);
        $this->assertDatabaseCount('channel_analysis_metrics', 1);

        $this->runJob($run);
        $this->assertDatabaseCount('analyzer_run_videos', 56);
        $this->assertDatabaseCount('video_snapshots', 56);
    }

    public function test_deleted_items_and_nullable_statistics_are_partial_without_becoming_zero(): void
    {
        config(['analyzer.recent_video_limit' => 10]);
        $provider = new RecentCohortFakeProvider(missingVideoId: 'upload000007', nullableVideoId: 'upload000003');
        $run = $this->executeRun(User::factory()->create(), $provider);
        $metrics = $run->channelMetrics()->firstOrFail();

        $this->assertSame(9, $metrics->recent_valid_count);
        $this->assertSame('90.0000', $metrics->coverage_percent);
        $this->assertSame('550.0000', $metrics->median_views);
        $this->assertSame(1, $run->cohortItems()->whereNotNull('unavailable_at')->count());
        $this->assertDatabaseHas('video_snapshots', [
            'collection_run_id' => $run->collection_run_id,
            'view_count' => null,
            'like_count' => null,
            'comment_count' => null,
        ]);
        $this->assertStringContainsString('deleted, private, or otherwise unavailable', implode(' ', $run->warnings ?? []));
        $this->assertNotEmpty($metrics->warnings);
        $this->assertNull($metrics->momentum_ratio);
        $this->assertStringContainsString('Momentum needs', implode(' ', $metrics->warnings ?? []));
    }

    public function test_unavailable_uploads_playlist_preserves_a_completed_partial_profile(): void
    {
        $provider = new RecentCohortFakeProvider(playlistUnavailable: true);
        $run = $this->executeRun(User::factory()->create(), $provider);

        $this->assertSame(AnalyzerRunStatus::Completed, $run->status);
        $this->assertSame(0, $run->videoMemberships()->where('role', 'channel_recent_upload')->count());
        $this->assertSame(0, $run->channelMetrics()->firstOrFail()->recent_valid_count);
        $this->assertNull($run->channelMetrics()->firstOrFail()->consistency_score);
        $this->assertStringContainsString('uploads playlist is unavailable', implode(' ', $run->warnings ?? []));
    }

    public function test_hidden_subscribers_and_missing_engagement_do_not_block_view_relative_evidence(): void
    {
        config(['analyzer.recent_video_limit' => 3]);
        $provider = new RecentCohortFakeProvider(hiddenSubscribers: true, anchorEngagementMissing: true);
        $run = $this->executeRun(User::factory()->create(), $provider);
        $metrics = $run->videoMetrics()->firstOrFail();

        $this->assertNull($metrics->views_to_subscribers_ratio);
        $this->assertNull($metrics->public_engagement_rate_percent);
        $this->assertSame('0.50000000', $metrics->channel_median_ratio);
        $this->assertSame('normal', $metrics->breakout_class);
        $this->assertSame(3, $metrics->recent_rank);
        $this->assertStringContainsString('hides subscriber count', implode(' ', $metrics->warnings ?? []));
    }

    public function test_six_month_retention_audits_and_removes_unshared_analyzer_observations_only(): void
    {
        Date::setTestNow('2026-01-01 12:00:00 UTC');
        $owner = User::factory()->create();
        $run = $this->executeRun($owner, new RecentCohortFakeProvider(
            observedAt: new DateTimeImmutable('2026-01-01 12:00:00 UTC'),
        ));
        $videoId = $run->video_id;
        $channelId = $run->channel_id;
        $collectionRunId = $run->collection_run_id;

        Date::setTestNow('2026-08-09 12:00:00 UTC');
        Queue::fake([ExecuteCleanupRun::class]);
        $plan = app(BuildRetentionPlan::class)->handle($owner);

        $this->assertSame(1, $plan->counts['analyzer_runs']);
        $this->assertGreaterThan(0, $plan->counts['video_snapshots']);
        $this->assertSame(DeletionOutcome::Eligible, collect($plan->items)
            ->firstWhere('target_reference', $run->public_id)['outcome']);

        $cleanup = app(CreateCleanupRun::class)->handle(
            $owner,
            CleanupMode::ManualRetention,
            false,
            initiator: $owner,
        );
        (new ExecuteCleanupRun($cleanup->id))->handle(app(ExecuteCleanup::class));

        $this->assertDatabaseMissing('analyzer_runs', ['id' => $run->id]);
        $this->assertDatabaseMissing('collection_runs', ['id' => $collectionRunId]);
        $this->assertDatabaseMissing('video_snapshots', ['collection_run_id' => $collectionRunId]);
        $this->assertDatabaseMissing('channel_snapshots', ['collection_run_id' => $collectionRunId]);
        $this->assertDatabaseHas('videos', ['id' => $videoId]);
        $this->assertDatabaseHas('channels', ['id' => $channelId]);
        $this->assertDatabaseHas('user_entity_observations', [
            'user_id' => $owner->id,
            'subject_type' => 'video',
            'subject_id' => $videoId,
            'first_video_snapshot_id' => null,
            'latest_video_snapshot_id' => null,
        ]);
        $this->assertDatabaseHas('snapshot_deletion_items', [
            'cleanup_run_id' => $cleanup->id,
            'target_type' => 'analyzer_run',
            'target_reference' => $run->public_id,
            'outcome' => DeletionOutcome::Deleted->value,
        ]);
        $this->assertSame(1, $cleanup->fresh()->deleted_counts['analyzer_runs']);
    }

    public function test_retention_preserves_analyzer_sources_pinned_by_a_cached_attempt(): void
    {
        Date::setTestNow('2026-01-01 12:00:00 UTC');
        $owner = User::factory()->create();
        $source = $this->executeRun($owner, new RecentCohortFakeProvider(
            observedAt: new DateTimeImmutable('2026-01-01 12:00:00 UTC'),
        ));
        $sourceSnapshotId = $source->videoMemberships()->where('role', 'anchor')->sole()->video_snapshot_id;

        Date::setTestNow('2026-01-01 13:00:00 UTC');
        $cached = app(CreateAnalyzerRun::class)->handle(
            $owner,
            RecentCohortFakeProvider::ANCHOR,
            CollectionCachePolicy::AllowFreshCache,
        );
        $this->bind(new RecentCohortFakeProvider(
            observedAt: new DateTimeImmutable('2026-01-01 13:00:00 UTC'),
        ));
        $this->runJob($cached);

        Date::setTestNow('2026-08-09 12:00:00 UTC');
        $plan = app(BuildRetentionPlan::class)->handle($owner);
        $sourceItem = collect($plan->items)->firstWhere('target_reference', $source->public_id);

        $this->assertSame(DeletionOutcome::PreservedSharedSource, $sourceItem['outcome']);
        $this->assertSame(1, $plan->counts['preserved_shared_sources']);
        $this->assertDatabaseHas('video_snapshots', ['id' => $sourceSnapshotId]);
        $this->assertSame(
            $sourceSnapshotId,
            $cached->videoMemberships()->where('role', 'anchor')->sole()->video_snapshot_id,
        );
    }

    public function test_strong_and_breakout_memberships_are_persisted_and_exposed_as_exact_evidence(): void
    {
        config(['analyzer.recent_video_limit' => 5]);
        $provider = new RecentCohortFakeProvider(viewOverrides: [
            1 => 100,
            2 => 100,
            3 => 100,
            4 => 300,
            5 => 600,
        ]);
        $run = $this->executeRun(User::factory()->create(), $provider);

        $this->assertDatabaseHas('channel_analysis_metrics', [
            'analyzer_run_id' => $run->id,
            'strong_count' => 2,
            'strong_share_percent' => '40.0000',
            'breakout_count' => 1,
            'breakout_share_percent' => '20.0000',
        ]);
        $this->assertDatabaseHas('analyzer_run_videos', [
            'analyzer_run_id' => $run->id,
            'source_position' => 4,
            'channel_median_ratio' => '3.00000000',
            'breakout_class' => 'strong',
        ]);
        $this->assertDatabaseHas('analyzer_run_videos', [
            'analyzer_run_id' => $run->id,
            'source_position' => 5,
            'channel_median_ratio' => '6.00000000',
            'breakout_class' => 'breakout',
        ]);

        $this->actingAs($run->user)->get(route('analyzer.runs.show', $run))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.channel_metrics.strong_share_percent', 40)
                ->where('run.channel_metrics.breakout_share_percent', 20)
                ->where('run.recent_videos.3.breakout_class.label', 'Strong')
                ->where('run.recent_videos.4.breakout_class.label', 'Breakout')
                ->where('run.recent_videos.4.channel_median_ratio', 6));
    }

    public function test_recent_video_links_to_latest_owned_completed_local_analysis_only(): void
    {
        config(['analyzer.recent_video_limit' => 2]);
        Queue::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $channelRun = $this->executeRun($owner, new RecentCohortFakeProvider);
        $older = app(CreateAnalyzerRun::class)->handleTarget($owner, 'video', 'upload000001', CollectionCachePolicy::AllowFreshCache);
        $latest = app(CreateAnalyzerRun::class)->handleTarget($owner, 'video', 'upload000001', CollectionCachePolicy::ForceRefresh);
        $foreign = app(CreateAnalyzerRun::class)->handleTarget($other, 'video', 'upload000002', CollectionCachePolicy::AllowFreshCache);

        DB::table('analyzer_runs')->where('id', $older->id)->update([
            'status' => AnalyzerRunStatus::Completed->value,
            'completed_at' => now()->subHour(),
        ]);
        DB::table('analyzer_runs')->where('id', $latest->id)->update([
            'status' => AnalyzerRunStatus::Completed->value,
            'completed_at' => now(),
        ]);
        DB::table('analyzer_runs')->where('id', $foreign->id)->update([
            'status' => AnalyzerRunStatus::Completed->value,
            'completed_at' => now()->addMinute(),
        ]);

        $this->actingAs($owner)->get(route('analyzer.runs.show', $channelRun))
            ->assertInertia(fn (Assert $page): Assert => $page
                ->where('run.recent_videos.0.local_analysis.public_id', $latest->public_id)
                ->where('run.recent_videos.0.local_analysis.url', "/analyzer/runs/{$latest->public_id}")
                ->where('run.recent_videos.1.local_analysis', null));

        $this->assertDatabaseCount('api_usage_events', 0);
    }

    private function executeRun(User $owner, RecentCohortFakeProvider $provider): AnalyzerRun
    {
        $run = app(CreateAnalyzerRun::class)->handle($owner, RecentCohortFakeProvider::ANCHOR, CollectionCachePolicy::ForceRefresh);
        $this->bind($provider);
        $this->runJob($run);

        return $run->fresh();
    }

    private function bind(RecentCohortFakeProvider $provider): void
    {
        $this->app->instance(VideoResearchProvider::class, $provider);
        $this->app->instance(ChannelUploadsProvider::class, $provider);
    }

    private function runJob(AnalyzerRun $run): void
    {
        $this->app->call([new CollectAnalyzerRun($run->id), 'handle']);
    }
}

final class RecentCohortFakeProvider implements ChannelUploadsProvider, VideoResearchProvider
{
    public const ANCHOR = 'anchor00001';

    /** @var list<int> */
    public array $playlistPageSizes = [];

    /** @var list<int> */
    public array $videoBatchSizes = [];

    private bool $secondBatchFailed = false;

    public function __construct(
        private readonly bool $failSecondBatchOnce = false,
        private readonly bool $playlistUnavailable = false,
        private readonly ?string $missingVideoId = null,
        private readonly ?string $nullableVideoId = null,
        private readonly bool $hiddenSubscribers = false,
        private readonly bool $anchorEngagementMissing = false,
        /** @var array<int, int> */
        private readonly array $viewOverrides = [],
        private readonly ?DateTimeImmutable $observedAt = null,
        private readonly int $channelViewCount = 100_000,
        private readonly int $channelSubscriberCount = 1_000,
        private readonly int $channelVideoCount = 100,
    ) {}

    public function search(VideoSearchRequest $request): VideoSearchPage
    {
        throw new \LogicException('Analyzer must not call search.list.');
    }

    public function listUploads(ChannelUploadsRequest $request): ChannelUploadsPage
    {
        $this->playlistPageSizes[] = $request->maxResults;

        if ($this->playlistUnavailable) {
            throw new YouTubeProviderException(YouTubeErrorCode::PlaylistUnavailable);
        }

        $start = $request->pageToken === null ? 1 : 51;
        $uploads = [];

        for ($index = 0; $index < $request->maxResults; $index++) {
            $position = $start + $index;
            $uploads[] = new ChannelUpload(sprintf('upload%06d', $position), $position);
        }

        return new ChannelUploadsPage(
            uploads: $uploads,
            nextPageToken: $start === 1 && $request->maxResults === 50 ? 'page-2' : null,
        );
    }

    public function fetchVideos(YouTubeIdBatchRequest $request): VideoDetailsBatch
    {
        $this->videoBatchSizes[] = count($request->ids);

        if (
            $this->failSecondBatchOnce
            && count($request->ids) === 5
            && ! $this->secondBatchFailed
        ) {
            $this->secondBatchFailed = true;
            throw new YouTubeProviderException(YouTubeErrorCode::Unavailable);
        }

        $videos = [];

        foreach ($request->ids as $videoId) {
            if ($videoId === $this->missingVideoId) {
                continue;
            }

            $position = $videoId === self::ANCHOR ? 0 : (int) substr($videoId, -6);
            $nullable = $videoId === $this->nullableVideoId;
            $videos[] = new VideoDetails(
                videoId: $videoId,
                channelId: 'channel000001',
                channelTitle: 'Cohort author',
                title: $videoId === self::ANCHOR ? 'Anchor video' : "Recent upload {$position}",
                thumbnailUrl: null,
                publishedAt: new DateTimeImmutable("2026-08-09 12:00:00 UTC -{$position} days"),
                durationSeconds: $nullable ? null : $position * 60,
                categoryId: $position % 2 === 0 ? '26' : '22',
                isShort: false,
                viewCount: $nullable ? null : ($this->viewOverrides[$position] ?? max(100, $position * 100)),
                likeCount: $nullable || ($videoId === self::ANCHOR && $this->anchorEngagementMissing)
                    ? null
                    : $position * 10,
                commentCount: $nullable || ($videoId === self::ANCHOR && $this->anchorEngagementMissing)
                    ? null
                    : $position,
            );
        }

        $warnings = count($videos) === count($request->ids) ? [] : ['YouTube omitted one requested recent video.'];

        return new VideoDetailsBatch($videos, $this->observedAt ?? new DateTimeImmutable('2026-08-09 12:00:00 UTC'), $warnings);
    }

    public function fetchChannels(YouTubeIdBatchRequest $request): ChannelDetailsBatch
    {
        return new ChannelDetailsBatch([
            new ChannelDetails(
                channelId: 'channel000001',
                title: 'Cohort author',
                customUrl: '@cohort',
                thumbnailUrl: null,
                country: 'RO',
                subscriberCount: $this->hiddenSubscribers ? null : $this->channelSubscriberCount,
                viewCount: $this->channelViewCount,
                videoCount: $this->channelVideoCount,
                subscriberCountHidden: $this->hiddenSubscribers,
                metadata: ['uploads_playlist_id' => 'UUchannel000001'],
            ),
        ], $this->observedAt ?? new DateTimeImmutable('2026-08-09 12:00:00 UTC'));
    }
}
