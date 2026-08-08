<?php

namespace App\Domain\Research\Actions;

use App\Domain\Research\Enums\ResearchRunStatus;
use App\Jobs\Research\CollectResearchRunSearch;
use App\Models\ResearchRun;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class QueueResearchRun
{
    public function __construct(private readonly TransitionResearchRun $transition) {}

    /** @throws AuthorizationException */
    public function handle(User $user, ResearchRun $run): ResearchRun
    {
        $run = ResearchRun::query()->findOrFail($run->id);

        if ($run->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        if ($run->status === ResearchRunStatus::Draft) {
            $run = $this->transition->handle($run, ResearchRunStatus::Queued);
        } elseif (! in_array($run->status, [ResearchRunStatus::Queued, ResearchRunStatus::Searching], true)) {
            throw new DomainException('Only draft, queued, or interrupted searching runs may be queued.');
        }

        CollectResearchRunSearch::dispatch($run->id)->afterCommit();

        return $run;
    }
}
