<?php

namespace App\Http\ViewModels;

use App\Domain\Retention\Enums\CleanupStatus;
use App\Domain\Retention\Services\BuildRetentionPlan;
use App\Models\CleanupRun;
use App\Models\SnapshotDeletionItem;
use App\Models\User;
use Illuminate\Support\Facades\Date;

final readonly class RetentionViewModel
{
    public function __construct(private BuildRetentionPlan $plans) {}

    /** @return array<string, mixed> */
    public function workspace(User $user): array
    {
        $history = CleanupRun::query()
            ->where('user_id', $user->id)
            ->with(['items' => fn ($query) => $query->orderBy('id')])
            ->latest('id')
            ->limit(10)
            ->get();

        return [
            'preview' => $this->plans->handle($user)->toArray(),
            'has_active' => $history->contains(
                fn (CleanupRun $cleanup): bool => in_array(
                    $cleanup->status,
                    [CleanupStatus::Queued, CleanupStatus::Processing],
                    true,
                ),
            ),
            'history' => $history->map(fn (CleanupRun $cleanup): array => [
                'public_id' => $cleanup->public_id,
                'mode' => $cleanup->mode->value,
                'status' => $cleanup->status->value,
                'dry_run' => $cleanup->dry_run,
                'cutoff_at' => $cleanup->cutoff_at->toIso8601String(),
                'eligible_counts' => $cleanup->eligible_counts,
                'deleted_counts' => $cleanup->deleted_counts,
                'started_at' => $cleanup->started_at?->toIso8601String(),
                'created_at' => $cleanup->created_at?->toIso8601String(),
                'completed_at' => $cleanup->completed_at?->toIso8601String(),
                'failed_at' => $cleanup->failed_at?->toIso8601String(),
                'error_code' => $cleanup->error_code,
                'error_message' => $cleanup->error_message,
                'items' => $cleanup->items->map(fn (SnapshotDeletionItem $item): array => [
                    'target_type' => $item->target_type->value,
                    'target_reference' => $item->target_reference,
                    'original_collection_at' => $item->original_collection_at->toIso8601String(),
                    'outcome' => $item->outcome->value,
                    'favorite_impacted' => $item->favorite_impacted,
                    'deleted_at' => $item->deleted_at?->toIso8601String(),
                ])->all(),
            ])->all(),
            'refreshed_at' => Date::now()->toIso8601String(),
        ];
    }
}
