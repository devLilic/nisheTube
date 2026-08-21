<?php

namespace App\Domain\Research\Actions;

use App\Domain\Collection\Actions\SyncResearchCollectionRun;
use App\Domain\Research\Data\RunFailure;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Research\Exceptions\InvalidResearchRunTransition;
use App\Models\ResearchRun;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransitionResearchRun
{
    public function __construct(private readonly SyncResearchCollectionRun $syncCollectionRun) {}

    public function handle(
        ResearchRun $run,
        ResearchRunStatus $nextStatus,
        ?RunFailure $failure = null,
    ): ResearchRun {
        return DB::transaction(function () use ($run, $nextStatus, $failure): ResearchRun {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($run->id);
            $currentStatus = $lockedRun->status;

            if ($currentStatus === $nextStatus) {
                return $lockedRun;
            }

            if (! $currentStatus->canTransitionTo($nextStatus)) {
                throw InvalidResearchRunTransition::between($currentStatus, $nextStatus);
            }

            if (in_array($nextStatus, [ResearchRunStatus::Failed, ResearchRunStatus::Cancelled], true) && $failure === null) {
                throw new InvalidArgumentException('A safe terminal reason is required when a research run stops.');
            }

            if (! in_array($nextStatus, [ResearchRunStatus::Failed, ResearchRunStatus::Cancelled], true) && $failure !== null) {
                throw new InvalidArgumentException('Terminal details may only be stored on a failed or cancelled research run.');
            }

            $attributes = ['status' => $nextStatus];

            if ($nextStatus === ResearchRunStatus::Searching) {
                $attributes['started_at'] = $lockedRun->started_at ?? now();
            }

            if ($nextStatus === ResearchRunStatus::Enriching) {
                $attributes['search_completed_at'] = $lockedRun->search_completed_at ?? now();
            }

            if ($nextStatus === ResearchRunStatus::Completed) {
                $attributes['completed_at'] = now();
                $attributes['progress_percent'] = 100;
            }

            if ($nextStatus === ResearchRunStatus::Scoring) {
                $attributes['progress_percent'] = max(90, $lockedRun->progress_percent);
            }

            if (in_array($nextStatus, [ResearchRunStatus::Failed, ResearchRunStatus::Cancelled], true)) {
                $attributes['failed_at'] = now();
                $attributes['error_code'] = $failure->code;
                $attributes['error_message'] = $failure->message;
            }

            $lockedRun->update($attributes);
            $this->syncCollectionRun->handle($lockedRun);

            return $lockedRun;
        });
    }
}
