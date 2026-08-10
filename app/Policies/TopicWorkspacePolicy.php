<?php

namespace App\Policies;

use App\Models\TopicWorkspace;
use App\Models\User;

class TopicWorkspacePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function view(User $user, TopicWorkspace $workspace): bool
    {
        return $workspace->user_id === $user->id;
    }

    public function update(User $user, TopicWorkspace $workspace): bool
    {
        return $workspace->user_id === $user->id;
    }

    public function archive(User $user, TopicWorkspace $workspace): bool
    {
        return $workspace->user_id === $user->id;
    }

    public function addEvidence(User $user, TopicWorkspace $workspace): bool
    {
        return $workspace->user_id === $user->id && $workspace->archived_at === null;
    }

    public function launch(User $user, TopicWorkspace $workspace): bool
    {
        return $workspace->user_id === $user->id && $workspace->archived_at === null;
    }
}
