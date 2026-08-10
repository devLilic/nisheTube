<?php

namespace App\Policies;

use App\Models\SavedCommentIdea;
use App\Models\User;

class SavedCommentIdeaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function delete(User $user, SavedCommentIdea $idea): bool
    {
        return $idea->user_id === $user->id;
    }
}
