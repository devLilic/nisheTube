<?php

namespace App\Policies;

use App\Models\ResearchProject;
use App\Models\User;

class ResearchProjectPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, ResearchProject $project): bool
    {
        return $project->user_id === $user->id;
    }

    public function update(User $user, ResearchProject $project): bool
    {
        return $project->user_id === $user->id;
    }

    public function delete(User $user, ResearchProject $project): bool
    {
        return $project->user_id === $user->id;
    }
}
