<?php

namespace App\Policies;

use App\Models\CollectionRun;
use App\Models\User;

class CollectionRunPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CollectionRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    public function update(User $user, CollectionRun $run): bool
    {
        return $run->user_id === $user->id;
    }

    public function delete(User $user, CollectionRun $run): bool
    {
        return $run->user_id === $user->id;
    }
}
