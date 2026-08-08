<?php

namespace App\Domain\Research\Actions;

use App\Domain\Research\Data\RunFailure;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Domain\Research\Exceptions\InvalidResearchRunTransition;
use App\Models\ResearchRun;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class TransitionResearchRun
{
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

            if ($nextStatus === ResearchRunStatus::Failed && $failure === null) {
                throw new InvalidArgumentException('A safe failure is required when a research run fails.');
            }

            if ($nextStatus !== ResearchRunStatus::Failed && $failure !== null) {
                throw new InvalidArgumentException('Failure details may only be stored on a failed research run.');
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

            if ($nextStatus === ResearchRunStatus::Failed) {
                $attributes['failed_at'] = now();
                $attributes['error_code'] = $failure->code;
                $attributes['error_message'] = $failure->message;
            }

            $lockedRun->update($attributes);

            return $lockedRun;
        });
    }
}
