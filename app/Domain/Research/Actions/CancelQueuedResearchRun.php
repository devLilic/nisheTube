<?php

namespace App\Domain\Research\Actions;

use App\Domain\Research\Data\RunFailure;
use App\Domain\Research\Enums\ResearchRunStatus;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CancelQueuedResearchRun
{
    public function __construct(private readonly TransitionResearchRun $transition) {}

    /** @throws AuthorizationException */
    public function handle(User $user, ResearchRun $run): ResearchRun
    {
        return DB::transaction(function () use ($user, $run): ResearchRun {
            $lockedRun = ResearchRun::query()->lockForUpdate()->findOrFail($run->id);

            if ($lockedRun->user_id !== $user->id) {
                throw new AuthorizationException;
            }

            if ($lockedRun->status === ResearchRunStatus::Cancelled) {
                return $lockedRun;
            }

            if ($lockedRun->status !== ResearchRunStatus::Queued) {
                throw new DomainException('Only a queued research run may be cancelled.');
            }

            return $this->transition->handle(
                $lockedRun,
                ResearchRunStatus::Cancelled,
                new RunFailure(
                    'research_cancelled',
                    'This queued run was cancelled before collection started. No YouTube requests were made.',
                ),
            );
        });
    }
}
