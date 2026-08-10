<?php

namespace Tests\Feature\Watchlist;

use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Collection\Enums\CollectionRunKind;
use App\Domain\Collection\Enums\CollectionRunStatus;
use App\Domain\Retention\Services\BuildRetentionPlan;
use App\Jobs\Analyzer\CollectAnalyzerRun;
use App\Jobs\Watchlist\FinalizeWatchlistRefresh;
use App\Models\AnalyzerRun;
use App\Models\AnalyzerRunVideo;
use App\Models\Channel;
use App\Models\ChannelSnapshot;
use App\Models\CollectionRun;
use App\Models\User;
use App\Models\Video;
use App\Models\VideoSnapshot;
use App\Models\WatchlistItem;
use App\Models\WatchlistRefreshRun;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class WatchlistWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_update_and_remove_a_video_without_creating_or_removing_a_favorite(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $video = $this->ownedVideo($owner);
        $payload = ['target_type' => 'video', 'target_reference' => $video->provider_video_id];

        $this->actingAs($owner)->post(route('watchlist.store'), $payload)->assertRedirect();
        $this->actingAs($owner)->post(route('watchlist.store'), $payload)->assertRedirect();

        $item = WatchlistItem::query()->sole();
        $this->assertSame($owner->id, $item->user_id);
        $this->assertDatabaseCount('watchlist_items', 1);
        $this->assertDatabaseCount('favorites', 0);

        $this->actingAs($other)->patch(route('watchlist.update', $item), [
            'status' => 'attention', 'is_active' => false, 'note' => 'Foreign edit',
        ])->assertForbidden();

        $this->actingAs($owner)->patch(route('watchlist.update', $item), [
            'status' => 'promising', 'is_active' => false, 'note' => 'Review after the next upload.',
        ])->assertRedirect();
        $this->assertDatabaseHas('watchlist_items', [
            'id' => $item->id, 'status' => 'promising', 'is_active' => false, 'note' => 'Review after the next upload.',
        ]);

        $this->actingAs($owner)->delete(route('watchlist.destroy', $item))->assertRedirect();
        $this->assertDatabaseCount('watchlist_items', 0);
        $this->assertDatabaseCount('favorites', 0);
        $this->assertDatabaseCount('videos', 1);
    }

    public function test_manual_refresh_is_queued_once_reuses_the_analyzer_boundary_and_rechecks_state(): void
    {
        Bus::fake();
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $video = $this->ownedVideo($owner);
        $this->actingAs($owner)->post(route('watchlist.store'), [
            'target_type' => 'video', 'target_reference' => $video->provider_video_id,
        ]);
        $item = WatchlistItem::query()->sole();

        $this->actingAs($owner)->post(route('watchlist.refresh', $item), ['mode' => 'force_refresh'])->assertRedirect();
        $this->actingAs($owner)->post(route('watchlist.refresh', $item), ['mode' => 'force_refresh'])->assertRedirect();

        $refresh = WatchlistRefreshRun::query()->sole();
        $this->assertSame('queued', $refresh->status->value);
        $this->assertSame(CollectionRunKind::WatchlistRefresh, $refresh->collectionRun->kind);
        $this->assertSame('watchlist', $refresh->analyzerRun->origin_kind);
        $this->assertSame($item->public_id, $refresh->analyzerRun->origin_reference);
        $this->assertSame(CollectionCachePolicy::ForceRefresh, $refresh->analyzerRun->cache_policy);
        Bus::assertChained([CollectAnalyzerRun::class, FinalizeWatchlistRefresh::class]);
        (new CollectAnalyzerRun($refresh->analyzer_run_id))->failed(new \RuntimeException('Worker exhausted retries.'));
        Bus::assertDispatched(FinalizeWatchlistRefresh::class, fn (FinalizeWatchlistRefresh $job): bool => $job->refreshRunId === $refresh->id);

        $this->actingAs($other)->post(route('watchlist.refresh', $item), ['mode' => 'force_refresh'])->assertForbidden();
        $item->update(['is_active' => false]);
        $this->actingAs($owner)->post(route('watchlist.refresh', $item), ['mode' => 'force_refresh'])->assertForbidden();
        $this->assertDatabaseCount('watchlist_refresh_runs', 1);
    }

    public function test_completed_refreshes_pin_immutable_snapshots_calculate_deltas_and_protect_retention_sources(): void
    {
        Bus::fake();
        Date::setTestNow('2026-08-09 12:00:00');
        $owner = User::factory()->create();
        $video = $this->ownedVideo($owner);
        $this->actingAs($owner)->post(route('watchlist.store'), [
            'target_type' => 'video', 'target_reference' => $video->provider_video_id,
        ]);
        $item = WatchlistItem::query()->sole();

        $first = $this->queueAndCompleteRefresh($owner, $item, $video, 100, 10, 2, 1000);
        $second = $this->queueAndCompleteRefresh($owner, $item->fresh(), $video, 160, 13, 4, 1125);

        $this->assertSame($first->current_video_snapshot_id, $second->previous_video_snapshot_id);
        $this->assertSame(60, $second->deltas['video']['views']);
        $this->assertSame(125, $second->deltas['channel']['subscribers']);
        $this->assertSame($second->id, $item->fresh()->last_refresh_run_id);

        AnalyzerRun::query()->whereIn('id', [$first->analyzer_run_id, $second->analyzer_run_id])->update([
            'completed_at' => Date::now()->subMonthsNoOverflow(7),
            'updated_at' => Date::now()->subMonthsNoOverflow(7),
        ]);
        $plan = app(BuildRetentionPlan::class)->handle($owner);
        $this->assertGreaterThanOrEqual(1, $plan->counts['watchlist_source_links']);
        $this->assertGreaterThanOrEqual(1, $plan->counts['preserved_shared_sources']);
        $this->assertSame(0, $plan->counts['analyzer_runs']);
    }

    public function test_index_exposes_empty_active_partial_error_quota_and_success_context_without_provider_work(): void
    {
        Bus::fake();
        $owner = User::factory()->create();
        $video = $this->ownedVideo($owner);

        $this->actingAs($owner)->get(route('watchlist.index'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('watchlist/index')->has('items', 0)->where('counts.all', 0));

        $this->actingAs($owner)->post(route('watchlist.store'), ['target_type' => 'video', 'target_reference' => $video->provider_video_id]);
        $item = WatchlistItem::query()->sole();
        $this->actingAs($owner)->post(route('watchlist.refresh', $item), ['mode' => 'force_refresh']);
        $refresh = WatchlistRefreshRun::query()->sole();
        $refresh->update([
            'status' => 'failed', 'error_code' => 'youtube_quota_exhausted',
            'error_message' => 'The configured quota bucket is exhausted.', 'failed_at' => now(),
        ]);
        $item->update(['last_refresh_run_id' => $refresh->id]);

        $this->actingAs($owner)->get(route('watchlist.index'))->assertOk()->assertInertia(fn (Assert $page): Assert => $page
            ->component('watchlist/index')
            ->has('items', 1)
            ->where('items.0.refresh.status', 'failed')
            ->where('items.0.refresh.quota_exhausted', true)
            ->where('items.0.favorite', false)
            ->where('workspace_available', true)
            ->has('workspaces', 0));
    }

    private function ownedVideo(User $user): Video
    {
        $channel = Channel::query()->create([
            'provider' => 'youtube', 'provider_channel_id' => 'UC'.str_pad((string) $user->id, 22, 'A'), 'title' => 'Owned channel',
        ]);
        $video = Video::query()->create([
            'provider' => 'youtube', 'provider_video_id' => str_pad((string) $user->id, 11, 'v'), 'channel_id' => $channel->id,
            'title' => 'Owned video', 'published_at' => now()->subDays(10),
        ]);
        $collection = $this->collection($user, CollectionRunKind::VideoAnalysis);
        $user->analyzerRuns()->create([
            'target_kind' => 'video', 'target_provider_id' => $video->provider_video_id, 'video_id' => $video->id,
            'channel_id' => $channel->id, 'collection_run_id' => $collection->id, 'origin_kind' => 'manual',
            'cache_policy' => CollectionCachePolicy::AllowFreshCache, 'freshness_window_seconds' => 21600,
            'recent_video_limit' => 30, 'calculation_version' => 'video-profile-v1',
            'threshold_version' => 'video-relative-performance-v1', 'behavior_version' => 'channel-behavior-v1',
            'status' => 'queued', 'attempt_number' => 1, 'progress_percent' => 0,
        ]);

        return $video;
    }

    private function collection(User $user, CollectionRunKind $kind): CollectionRun
    {
        return $user->collectionRuns()->create([
            'provider' => 'youtube', 'kind' => $kind, 'status' => CollectionRunStatus::Queued,
            'attempt_number' => 1, 'frozen_request' => [], 'cache_policy' => CollectionCachePolicy::ForceRefresh,
            'requested_count' => 1, 'processed_count' => 0, 'progress_percent' => 0,
        ]);
    }

    private function queueAndCompleteRefresh(User $owner, WatchlistItem $item, Video $video, int $views, int $likes, int $comments, int $subscribers): WatchlistRefreshRun
    {
        $this->actingAs($owner)->post(route('watchlist.refresh', $item), ['mode' => 'force_refresh'])->assertRedirect();
        $refresh = $item->refreshRuns()->latest('id')->firstOrFail();
        $analyzer = $refresh->analyzerRun;
        $channelSnapshot = ChannelSnapshot::query()->create([
            'channel_id' => $video->channel_id, 'collection_run_id' => $analyzer->collection_run_id,
            'subscriber_count' => $subscribers, 'view_count' => $views * 20, 'video_count' => 20,
            'subscriber_count_hidden' => false, 'collected_at' => now(),
        ]);
        $videoSnapshot = VideoSnapshot::query()->create([
            'video_id' => $video->id, 'collection_run_id' => $analyzer->collection_run_id,
            'view_count' => $views, 'like_count' => $likes, 'comment_count' => $comments, 'collected_at' => now(),
        ]);
        $analyzer->update([
            'video_id' => $video->id, 'channel_id' => $video->channel_id, 'channel_snapshot_id' => $channelSnapshot->id,
            'status' => 'completed', 'progress_percent' => 100, 'started_at' => now(), 'completed_at' => now(),
        ]);
        AnalyzerRunVideo::query()->create([
            'analyzer_run_id' => $analyzer->id, 'video_id' => $video->id, 'video_snapshot_id' => $videoSnapshot->id,
            'channel_snapshot_id' => $channelSnapshot->id, 'role' => AnalyzerVideoRole::Anchor,
        ]);
        (new FinalizeWatchlistRefresh($refresh->id))->handle();

        return $refresh->fresh();
    }
}
