<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function update(User $authenticatedUser, User $targetUser): bool
    {
        return $authenticatedUser->is($targetUser);
    }

    public function delete(User $authenticatedUser, User $targetUser): bool
    {
        return $authenticatedUser->is($targetUser);
    }
}
