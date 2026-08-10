<?php

namespace App\Domain\Comments\Actions;

use App\Models\SavedCommentIdea;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class RemoveCommentIdea
{
    public function handle(User $user, SavedCommentIdea $idea): void
    {
        if ($idea->user_id !== $user->id) {
            throw (new ModelNotFoundException)->setModel(SavedCommentIdea::class);
        }

        $idea->delete();
    }
}
