<?php

namespace App\Domain\Retention\Actions;

use App\Domain\Retention\Enums\CleanupMode;
use App\Domain\Retention\Enums\CleanupStatus;
use App\Domain\Retention\Enums\DeletionOutcome;
use App\Domain\Retention\Services\BuildRetentionPlan;
use App\Jobs\Retention\ExecuteCleanupRun;
use App\Models\CleanupRun;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

final readonly class CreateCleanupRun
{
    public function __construct(private BuildRetentionPlan $plans) {}

    /**
     * @param  list<string>  $selectedRunPublicIds
     */
    public function handle(
        User $user,
        CleanupMode $mode,
        bool $dryRun,
        array $selectedRunPublicIds = [],
        bool $favoriteImpactConfirmed = false,
        ?User $initiator = null,
    ): CleanupRun {
        $plan = $this->plans->handle($user, $mode, $selectedRunPublicIds);

        if (
            $mode === CleanupMode::ManualSelection
            && ! $favoriteImpactConfirmed
            && collect($plan->items)->contains(fn (array $item): bool => $item['favorite_impacted'])
        ) {
            throw new DomainException('Confirm that deleting the selected run will also remove its favorite.');
        }

        $cleanup = DB::transaction(function () use ($user, $mode, $dryRun, $plan, $initiator): CleanupRun {
            $cleanup = CleanupRun::query()->create([
                'user_id' => $user->id,
                'initiated_by_user_id' => $initiator?->id,
                'mode' => $mode,
                'status' => $dryRun ? CleanupStatus::Previewed : CleanupStatus::Queued,
                'cutoff_at' => $plan->cutoffAt,
                'dry_run' => $dryRun,
                'eligible_counts' => $plan->counts,
                'deleted_counts' => [],
                'completed_at' => $dryRun ? now() : null,
            ]);

            foreach ($plan->items as $item) {
                $cleanup->items()->create([
                    ...$item,
                    'outcome' => $dryRun && $item['outcome'] === DeletionOutcome::Eligible
                        ? DeletionOutcome::Eligible
                        : $item['outcome'],
                ]);
            }

            return $cleanup;
        });

        if (! $dryRun) {
            ExecuteCleanupRun::dispatch($cleanup->id)->afterCommit();
        }

        return $cleanup;
    }
}
