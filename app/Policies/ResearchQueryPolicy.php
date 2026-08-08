<?php

namespace App\Policies;

use App\Models\ResearchQuery;
use App\Models\User;

class ResearchQueryPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, ResearchQuery $query): bool
    {
        return $query->user_id === $user->id;
    }

    public function update(User $user, ResearchQuery $query): bool
    {
        return $query->user_id === $user->id;
    }

    public function delete(User $user, ResearchQuery $query): bool
    {
        return $query->user_id === $user->id;
    }
}
