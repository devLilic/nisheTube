<?php

namespace App\Policies;

use App\Models\CleanupRun;
use App\Models\User;

final class CleanupRunPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CleanupRun $cleanup): bool
    {
        return $cleanup->user_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }
}
