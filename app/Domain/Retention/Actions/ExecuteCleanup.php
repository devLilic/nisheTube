<?php

namespace App\Domain\Retention\Actions;

use App\Domain\Exports\Actions\DeleteResearchExport;
use App\Domain\Library\Enums\LibraryTargetType;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Enums\CleanupStatus;
use App\Domain\Retention\Enums\CleanupTargetType;
use App\Domain\Retention\Enums\DeletionOutcome;
use App\Models\CleanupRun;
use App\Models\Favorite;
use App\Models\ResearchExport;
use App\Models\ResearchRun;
use App\Models\SnapshotDeletionItem;
use App\Models\Taggable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

final readonly class ExecuteCleanup
{
    public function __construct(private DeleteResearchExport $deleteExport) {}

    public function handle(CleanupRun $cleanup): void
    {
        $shouldRun = DB::transaction(function () use ($cleanup): bool {
            $locked = CleanupRun::query()->lockForUpdate()->find($cleanup->id);

            if (
                $locked === null
                || $locked->dry_run
                || in_array($locked->status, [CleanupStatus::Completed, CleanupStatus::Failed], true)
            ) {
                return false;
            }

            $locked->update([
                'status' => CleanupStatus::Processing,
                'started_at' => $locked->started_at ?? Date::now(),
                'failed_at' => null,
                'error_code' => null,
                'error_message' => null,
            ]);

            return true;
        });

        if (! $shouldRun) {
            return;
        }

        $cleanup->items()
            ->where('outcome', DeletionOutcome::Eligible->value)
            ->orderBy('id')
            ->eachById(function (SnapshotDeletionItem $item) use ($cleanup): void {
                match ($item->target_type) {
                    CleanupTargetType::ResearchRun => $this->deleteRun($cleanup, $item),
                    CleanupTargetType::Export => $this->deleteExpiredExport($cleanup, $item),
                };
            });

        DB::transaction(function () use ($cleanup): void {
            $locked = CleanupRun::query()->lockForUpdate()->find($cleanup->id);

            if ($locked === null || $locked->status === CleanupStatus::Completed) {
                return;
            }

            $locked->update([
                'status' => CleanupStatus::Completed,
                'completed_at' => Date::now(),
            ]);
        });
    }

    private function deleteRun(CleanupRun $cleanup, SnapshotDeletionItem $item): void
    {
        DB::transaction(function () use ($cleanup, $item): void {
            $lockedItem = SnapshotDeletionItem::query()->lockForUpdate()->find($item->id);

            if ($lockedItem === null || $lockedItem->outcome !== DeletionOutcome::Eligible) {
                return;
            }

            $run = ResearchRun::query()
                ->where('user_id', $cleanup->user_id)
                ->where('public_id', $lockedItem->target_reference)
                ->lockForUpdate()
                ->first();

            if ($run === null) {
                $lockedItem->update(['outcome' => DeletionOutcome::SkippedMissing]);

                return;
            }

            $favoriteExists = Favorite::query()
                ->where('user_id', $cleanup->user_id)
                ->where('target_type', LibraryTargetType::ResearchRun->value)
                ->where('target_id', $run->id)
                ->exists();

            if ($cleanup->mode !== CleanupMode::ManualSelection) {
                $terminalAt = $this->terminalAt($run);

                if ($favoriteExists) {
                    $lockedItem->update([
                        'outcome' => DeletionOutcome::PreservedFavorite,
                        'favorite_impacted' => true,
                    ]);

                    return;
                }

                if ($terminalAt === null || ! $terminalAt->lt($cleanup->cutoff_at)) {
                    $lockedItem->update(['outcome' => DeletionOutcome::SkippedIneligible]);

                    return;
                }
            }

            $counts = [
                'research_runs' => 1,
                'video_snapshots' => $run->videoSnapshots()->count(),
                'channel_snapshots' => $run->channelSnapshots()->count(),
                'opportunity_scores' => $run->opportunityScores()->count(),
                'search_pages' => $run->searchPages()->count(),
                'search_results' => $run->searchResults()->count(),
                'video_memberships' => $run->videoMemberships()->count(),
            ];

            Favorite::query()
                ->where('target_type', LibraryTargetType::ResearchRun->value)
                ->where('target_id', $run->id)
                ->delete();
            Taggable::query()
                ->where('target_type', LibraryTargetType::ResearchRun->value)
                ->where('target_id', $run->id)
                ->delete();
            $run->delete();

            $lockedItem->update([
                'outcome' => DeletionOutcome::Deleted,
                'deleted_at' => Date::now(),
                'favorite_impacted' => $favoriteExists,
            ]);
            $this->addDeletedCounts($cleanup->id, $counts);
        });
    }

    private function deleteExpiredExport(CleanupRun $cleanup, SnapshotDeletionItem $item): void
    {
        $export = ResearchExport::query()
            ->where('user_id', $cleanup->user_id)
            ->where('public_id', $item->target_reference)
            ->first();

        if ($export === null) {
            $item->update(['outcome' => DeletionOutcome::SkippedMissing]);

            return;
        }

        if ($export->expires_at === null || ! $export->expires_at->lt(Date::now())) {
            $item->update(['outcome' => DeletionOutcome::SkippedIneligible]);

            return;
        }

        $this->deleteExport->handle($cleanup->user, $export);

        DB::transaction(function () use ($cleanup, $item): void {
            $lockedItem = SnapshotDeletionItem::query()->lockForUpdate()->find($item->id);

            if ($lockedItem === null || $lockedItem->outcome !== DeletionOutcome::Eligible) {
                return;
            }

            $lockedItem->update([
                'outcome' => DeletionOutcome::Deleted,
                'deleted_at' => Date::now(),
            ]);
            $this->addDeletedCounts($cleanup->id, ['expired_exports' => 1]);
        });
    }

    /** @param array<string, int> $increments */
    private function addDeletedCounts(int $cleanupId, array $increments): void
    {
        $cleanup = CleanupRun::query()->lockForUpdate()->findOrFail($cleanupId);
        $counts = $cleanup->deleted_counts;

        foreach ($increments as $key => $increment) {
            $counts[$key] = ($counts[$key] ?? 0) + $increment;
        }

        $cleanup->update(['deleted_counts' => $counts]);
    }

    private function terminalAt(ResearchRun $run): ?CarbonInterface
    {
        return match ($run->status) {
            ResearchRunStatus::Completed => $run->completed_at,
            ResearchRunStatus::Failed => $run->failed_at,
            default => null,
        };
    }
}
