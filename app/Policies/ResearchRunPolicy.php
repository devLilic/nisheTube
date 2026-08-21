<?php

namespace App\Policies;

use App\Models\ResearchRun;
use App\Models\User;

class ResearchRunPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, ResearchRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    public function update(User $user, ResearchRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    public function retry(User $user, ResearchRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    public function cancel(User $user, ResearchRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    public function delete(User $user, ResearchRun $run): bool
    {
        return $run->user_id === $user->id;
    }
}
