<?php

namespace App\Domain\Watchlist\Actions;

use App\Domain\Analyzer\Actions\CreateAnalyzerRun;
use App\Domain\Collection\Enums\CollectionCachePolicy;
use App\Domain\Collection\Enums\CollectionRunKind;
use App\Domain\Watchlist\Enums\WatchlistRefreshStatus;
use App\Jobs\Analyzer\CollectAnalyzerRun;
use App\Jobs\Watchlist\FinalizeWatchlistRefresh;
use App\Models\User;
use App\Models\WatchlistItem;
use App\Models\WatchlistRefreshRun;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;

final readonly class StartWatchlistRefresh
{
    public function __construct(private CreateAnalyzerRun $createAnalyzerRun) {}

    public function handle(User $user, WatchlistItem $item, bool $forceRefresh = true): WatchlistRefreshRun
    {
        [$refresh, $created] = DB::transaction(function () use ($user, $item, $forceRefresh): array {
            $locked = WatchlistItem::query()->whereKey($item->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            if (! $locked->is_active) {
                throw new \DomainException('Resume this watchlist item before refreshing it.');
            }

            $active = WatchlistRefreshRun::query()->where('watchlist_item_id', $locked->id)
                ->whereIn('status', [WatchlistRefreshStatus::Queued->value, WatchlistRefreshStatus::Processing->value])
                ->latest('id')->first();
            if ($active !== null) {
                return [$active, false];
            }

            $target = $locked->target;
            if ($target === null) {
                throw new \DomainException('The watched subject is no longer available.');
            }
            $providerId = $locked->target_type === 'video'
                ? (string) $target->getAttribute('provider_video_id')
                : (string) $target->getAttribute('provider_channel_id');
            $analyzer = $this->createAnalyzerRun->handleTarget(
                $user,
                $locked->target_type,
                $providerId,
                $forceRefresh ? CollectionCachePolicy::ForceRefresh : CollectionCachePolicy::AllowFreshCache,
                'watchlist',
                $locked->public_id,
                collectionKind: CollectionRunKind::WatchlistRefresh,
            );
            $attempt = ((int) WatchlistRefreshRun::query()->where('watchlist_item_id', $locked->id)->max('attempt_number')) + 1;
            $refresh = WatchlistRefreshRun::query()->create([
                'watchlist_item_id' => $locked->id,
                'user_id' => $user->id,
                'analyzer_run_id' => $analyzer->id,
                'collection_run_id' => $analyzer->collection_run_id,
                'status' => WatchlistRefreshStatus::Queued,
                'attempt_number' => $attempt,
                'progress_percent' => 0,
            ]);

            return [$refresh, true];
        });

        if ($created) {
            Bus::chain([
                new CollectAnalyzerRun($refresh->analyzer_run_id),
                new FinalizeWatchlistRefresh($refresh->id),
            ])->dispatch();
        }

        return $refresh;
    }
}
