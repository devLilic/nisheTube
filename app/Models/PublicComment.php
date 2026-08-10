<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $comment_collection_run_id
 * @property string $provider_comment_id
 * @property string $text
 * @property int|null $like_count
 * @property int $reply_count
 * @property Carbon|null $published_at
 * @property Carbon|null $provider_updated_at
 */
#[Fillable([
    'comment_collection_run_id', 'provider_comment_id', 'text', 'like_count', 'reply_count',
    'published_at', 'provider_updated_at',
])]
class PublicComment extends Model
{
    /** @return BelongsTo<CommentCollectionRun, $this> */
    public function collectionRun(): BelongsTo
    {
        return $this->belongsTo(CommentCollectionRun::class, 'comment_collection_run_id');
    }

    /** @return HasMany<SavedCommentIdea, $this> */
    public function savedIdeas(): HasMany
    {
        return $this->hasMany(SavedCommentIdea::class);
    }

    protected function casts(): array
    {
        return [
            'like_count' => 'integer',
            'reply_count' => 'integer',
            'published_at' => 'immutable_datetime',
            'provider_updated_at' => 'immutable_datetime',
        ];
    }
}
