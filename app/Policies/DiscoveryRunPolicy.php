<?php

namespace App\Policies;

use App\Models\DiscoveryRun;
use App\Models\User;

class DiscoveryRunPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, DiscoveryRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    public function update(User $user, DiscoveryRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    public function delete(User $user, DiscoveryRun $run): bool
    {
        return $run->user_id === $user->id;
    }
}
