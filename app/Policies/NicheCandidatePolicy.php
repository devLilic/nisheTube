<?php

namespace App\Policies;

use App\Models\NicheCandidate;
use App\Models\User;

class NicheCandidatePolicy
{
    public function view(User $user, NicheCandidate $candidate): bool
    {
        return $candidate->discoveryRun()->where('user_id', $user->id)->exists();
    }

    public function update(User $user, NicheCandidate $candidate): bool
    {
        return $this->view($user, $candidate);
    }

    public function delete(User $user, NicheCandidate $candidate): bool
    {
        return $this->view($user, $candidate);
    }
}
