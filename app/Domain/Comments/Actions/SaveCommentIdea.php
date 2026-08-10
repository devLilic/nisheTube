<?php

namespace App\Domain\Comments\Actions;

use App\Models\PublicComment;
use App\Models\SavedCommentIdea;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class SaveCommentIdea
{
    public function handle(User $user, PublicComment $comment): SavedCommentIdea
    {
        $comment->loadMissing('collectionRun.video');
        $collection = $comment->collectionRun;

        if ($collection->user_id !== $user->id) {
            throw (new ModelNotFoundException)->setModel(PublicComment::class);
        }

        return SavedCommentIdea::query()->firstOrCreate(
            [
                'user_id' => $user->id,
                'video_id' => $collection->video_id,
                'provider_comment_id' => $comment->provider_comment_id,
            ],
            [
                'public_comment_id' => $comment->id,
                'comment_text' => $comment->text,
                'source_published_at' => $comment->published_at,
            ],
        );
    }
}
