<?php

namespace App\Jobs\Watchlist;

use App\Domain\Analyzer\Enums\AnalyzerRunStatus;
use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Watchlist\Enums\WatchlistRefreshStatus;
use App\Models\ChannelSnapshot;
use App\Models\VideoSnapshot;
use App\Models\WatchlistRefreshRun;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\DB;

class FinalizeWatchlistRefresh implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 600;

    public function __construct(public readonly int $refreshRunId) {}

    public function uniqueId(): string
    {
        return (string) $this->refreshRunId;
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping("watchlist-refresh:{$this->refreshRunId}"))->releaseAfter(5)->expireAfter(120)];
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $refresh = WatchlistRefreshRun::query()
                ->with(['item.target', 'analyzerRun'])
                ->lockForUpdate()
                ->find($this->refreshRunId);
            if ($refresh === null || $refresh->status->isTerminal()) {
                return;
            }

            $item = $refresh->item;
            $analyzer = $refresh->analyzerRun;
            if ($item->user_id !== $refresh->user_id || $analyzer->user_id !== $refresh->user_id) {
                $refresh->update([
                    'status' => WatchlistRefreshStatus::Failed,
                    'error_code' => 'ownership_changed',
                    'error_message' => 'The watched subject is no longer available to this account.',
                    'failed_at' => now(),
                ]);
                $item->update(['last_refresh_run_id' => $refresh->id]);

                return;
            }

            if ($analyzer->status !== AnalyzerRunStatus::Completed) {
                $refresh->update([
                    'status' => WatchlistRefreshStatus::Failed,
                    'progress_percent' => $analyzer->progress_percent,
                    'warnings' => $analyzer->warnings,
                    'error_code' => $analyzer->error_code ?? 'analysis_failed',
                    'error_message' => $analyzer->error_message ?? 'The observation refresh could not be completed.',
                    'failed_at' => $analyzer->failed_at ?? now(),
                ]);
                $item->update(['last_refresh_run_id' => $refresh->id]);

                return;
            }

            $currentVideo = $analyzer->videoMemberships()->where('role', AnalyzerVideoRole::Anchor->value)->value('video_snapshot_id');
            $currentChannel = $analyzer->channel_snapshot_id
                ?? $analyzer->videoMemberships()->where('role', AnalyzerVideoRole::Anchor->value)->value('channel_snapshot_id');
            $previous = $item->refreshRuns()
                ->whereKeyNot($refresh->id)
                ->whereIn('status', [WatchlistRefreshStatus::Completed->value, WatchlistRefreshStatus::Partial->value])
                ->latest('id')
                ->first();
            $previousVideo = $previous?->current_video_snapshot_id;
            $previousChannel = $previous?->current_channel_snapshot_id;
            $deltas = $this->deltas($previousVideo, $currentVideo, $previousChannel, $currentChannel);
            $warnings = $analyzer->warnings ?? [];
            $partial = ($currentVideo === null && $currentChannel === null) || $warnings !== [];

            $refresh->update([
                'status' => $partial ? WatchlistRefreshStatus::Partial : WatchlistRefreshStatus::Completed,
                'progress_percent' => 100,
                'warnings' => $warnings,
                'previous_video_snapshot_id' => $previousVideo,
                'current_video_snapshot_id' => $currentVideo,
                'previous_channel_snapshot_id' => $previousChannel,
                'current_channel_snapshot_id' => $currentChannel,
                'deltas' => $deltas,
                'started_at' => $analyzer->started_at,
                'completed_at' => $analyzer->completed_at ?? now(),
            ]);

            $observedAt = collect([
                $currentVideo === null ? null : VideoSnapshot::query()->whereKey($currentVideo)->value('collected_at'),
                $currentChannel === null ? null : ChannelSnapshot::query()->whereKey($currentChannel)->value('collected_at'),
            ])->filter()->max();
            $item->update([
                'last_refresh_run_id' => $refresh->id,
                'last_refreshed_at' => $refresh->completed_at ?? now(),
                'last_observed_at' => $observedAt,
                'next_refresh_at' => null,
            ]);
        });
    }

    /** @return array<string, array<string, int|null>> */
    private function deltas(?int $previousVideoId, ?int $currentVideoId, ?int $previousChannelId, ?int $currentChannelId): array
    {
        $beforeVideo = $previousVideoId === null ? null : VideoSnapshot::query()->find($previousVideoId);
        $afterVideo = $currentVideoId === null ? null : VideoSnapshot::query()->find($currentVideoId);
        $beforeChannel = $previousChannelId === null ? null : ChannelSnapshot::query()->find($previousChannelId);
        $afterChannel = $currentChannelId === null ? null : ChannelSnapshot::query()->find($currentChannelId);

        return [
            'video' => [
                'views' => $this->delta($beforeVideo?->view_count, $afterVideo?->view_count),
                'likes' => $this->delta($beforeVideo?->like_count, $afterVideo?->like_count),
                'comments' => $this->delta($beforeVideo?->comment_count, $afterVideo?->comment_count),
            ],
            'channel' => [
                'subscribers' => $this->delta($beforeChannel?->subscriber_count, $afterChannel?->subscriber_count),
                'views' => $this->delta($beforeChannel?->view_count, $afterChannel?->view_count),
                'videos' => $this->delta($beforeChannel?->video_count, $afterChannel?->video_count),
            ],
        ];
    }

    private function delta(?int $before, ?int $after): ?int
    {
        return $before === null || $after === null ? null : $after - $before;
    }
}
