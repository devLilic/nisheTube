<?php

namespace App\Domain\Retention\Services;

use App\Domain\Exports\Enums\ExportStatus;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Retention\Data\RetentionPlan;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Enums\CleanupTargetType;
use App\Domain\Retention\Enums\DeletionOutcome;
use App\Models\Favorite;
use App\Models\ResearchExport;
use App\Models\ResearchRun;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;

final class BuildRetentionPlan
{
    /**
     * @param  list<string>  $selectedRunPublicIds
     *
     * @throws AuthorizationException
     */
    public function handle(
        User $user,
        CleanupMode $mode = CleanupMode::ManualRetention,
        array $selectedRunPublicIds = [],
    ): RetentionPlan {
        $months = max(1, (int) config('retention.months', 6));
        $cutoff = Date::now()->subMonthsNoOverflow($months)->toImmutable();
        $counts = $this->emptyCounts();
        $items = [];
        $runRows = [];

        $runs = $mode === CleanupMode::ManualSelection
            ? $this->selectedRuns($user, $selectedRunPublicIds)
            : $this->retentionRuns($user, $cutoff);

        $favoriteRunIds = Favorite::query()
            ->where('user_id', $user->id)
            ->where('target_type', LibraryTargetType::ResearchRun->value)
            ->whereIn('target_id', $runs->pluck('id'))
            ->pluck('target_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->flip();

        foreach ($runs as $run) {
            $favoriteImpacted = $favoriteRunIds->has($run->id);
            $preserved = $mode !== CleanupMode::ManualSelection && $favoriteImpacted;
            $terminalAt = $this->terminalAt($run);

            if ($terminalAt === null) {
                continue;
            }

            $items[] = [
                'target_type' => CleanupTargetType::ResearchRun,
                'target_reference' => $run->public_id,
                'original_collection_at' => $terminalAt,
                'outcome' => $preserved ? DeletionOutcome::PreservedFavorite : DeletionOutcome::Eligible,
                'favorite_impacted' => $favoriteImpacted,
            ];
            $runRows[] = [
                'public_id' => $run->public_id,
                'query_text' => $run->query_text,
                'status' => $run->status->value,
                'terminal_at' => $terminalAt->toIso8601String(),
                'favorite_impacted' => $favoriteImpacted,
                'video_snapshots' => $run->video_snapshots_count,
                'channel_snapshots' => $run->channel_snapshots_count,
                'opportunity_scores' => $run->opportunity_scores_count,
                'artifacts' => $run->video_snapshots_count
                    + $run->channel_snapshots_count
                    + $run->opportunity_scores_count
                    + $run->search_pages_count
                    + $run->search_results_count
                    + $run->video_memberships_count,
            ];

            if ($preserved) {
                $counts['preserved_favorites']++;

                continue;
            }

            $counts['research_runs']++;
            $counts['video_snapshots'] += $run->video_snapshots_count;
            $counts['channel_snapshots'] += $run->channel_snapshots_count;
            $counts['opportunity_scores'] += $run->opportunity_scores_count;
            $counts['search_pages'] += $run->search_pages_count;
            $counts['search_results'] += $run->search_results_count;
            $counts['video_memberships'] += $run->video_memberships_count;
        }

        if ($mode !== CleanupMode::ManualSelection) {
            foreach ($this->expiredExports($user) as $export) {
                $collectedAt = $export->completed_at ?? $export->created_at;

                if ($collectedAt === null) {
                    continue;
                }

                $items[] = [
                    'target_type' => CleanupTargetType::Export,
                    'target_reference' => $export->public_id,
                    'original_collection_at' => $collectedAt,
                    'outcome' => DeletionOutcome::Eligible,
                    'favorite_impacted' => false,
                ];
                $counts['expired_exports']++;
            }
        }

        return new RetentionPlan($cutoff, $counts, $items, $runRows);
    }

    /** @return Builder<ResearchRun> */
    private function runQuery(User $user): Builder
    {
        return ResearchRun::query()
            ->where('user_id', $user->id)
            ->withCount([
                'videoSnapshots',
                'channelSnapshots',
                'opportunityScores',
                'searchPages',
                'searchResults',
                'videoMemberships',
            ]);
    }

    /** @return Collection<int, ResearchRun> */
    private function retentionRuns(User $user, CarbonInterface $cutoff): Collection
    {
        return $this->runQuery($user)
            ->where(function (Builder $query) use ($cutoff): void {
                $query
                    ->where(function (Builder $completed) use ($cutoff): void {
                        $completed
                            ->where('status', ResearchRunStatus::Completed->value)
                            ->where('completed_at', '<', $cutoff);
                    })
                    ->orWhere(function (Builder $failed) use ($cutoff): void {
                        $failed
                            ->where('status', ResearchRunStatus::Failed->value)
                            ->where('failed_at', '<', $cutoff);
                    });
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  list<string>  $publicIds
     * @return Collection<int, ResearchRun>
     *
     * @throws AuthorizationException
     */
    private function selectedRuns(User $user, array $publicIds): Collection
    {
        $uniqueIds = array_values(array_unique($publicIds));

        if ($uniqueIds === []) {
            throw new AuthorizationException('No owned research runs were selected.');
        }

        $runs = $this->runQuery($user)
            ->whereIn('public_id', $uniqueIds)
            ->whereIn('status', [ResearchRunStatus::Completed->value, ResearchRunStatus::Failed->value])
            ->orderBy('id')
            ->get();

        if ($runs->count() !== count($uniqueIds)) {
            throw new AuthorizationException('One or more research runs are unavailable.');
        }

        return $runs;
    }

    /** @return Collection<int, ResearchExport> */
    private function expiredExports(User $user): Collection
    {
        return ResearchExport::query()
            ->where('user_id', $user->id)
            ->where('status', ExportStatus::Completed->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Date::now())
            ->orderBy('id')
            ->get();
    }

    private function terminalAt(ResearchRun $run): ?CarbonInterface
    {
        return match ($run->status) {
            ResearchRunStatus::Completed => $run->completed_at,
            ResearchRunStatus::Failed => $run->failed_at,
            default => null,
        };
    }

    /** @return array<string, int> */
    private function emptyCounts(): array
    {
        return [
            'research_runs' => 0,
            'video_snapshots' => 0,
            'channel_snapshots' => 0,
            'opportunity_scores' => 0,
            'search_pages' => 0,
            'search_results' => 0,
            'video_memberships' => 0,
            'expired_exports' => 0,
            'preserved_favorites' => 0,
        ];
    }
}
