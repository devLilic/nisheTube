<?php

namespace App\Policies;

use App\Models\AnalyzerRun;
use App\Models\User;

class AnalyzerRunPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, AnalyzerRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    public function refresh(User $user, AnalyzerRun $run): bool
    {
        return $run->user_id === $user->id && $run->status->isTerminal();
    }

    public function collectComments(User $user, AnalyzerRun $run): bool
    {
        return $run->user_id === $user->id
            && $run->target_kind === 'video'
            && $run->status->value === 'completed';
    }

    public function analyzeAudienceSignals(User $user, AnalyzerRun $run): bool
    {
        return $this->collectComments($user, $run);
    }

    public function manageTranscripts(User $user, AnalyzerRun $run): bool
    {
        return $this->collectComments($user, $run);
    }

    public function analyzeThumbnails(User $user, AnalyzerRun $run): bool
    {
        return $run->user_id === $user->id && $run->status->value === 'completed';
    }
}
