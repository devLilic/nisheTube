<?php

namespace App\Models;

use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int|null $public_comment_id
 * @property int $video_id
 * @property string $provider_comment_id
 * @property string $comment_text
 * @property Carbon|null $source_published_at
 * @property Carbon|null $created_at
 * @property User $user
 * @property PublicComment|null $publicComment
 * @property Video $video
 */
#[Fillable([
    'user_id', 'public_comment_id', 'video_id', 'provider_comment_id', 'comment_text',
    'source_published_at',
])]
class SavedCommentIdea extends Model
{
    use HasPublicId;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PublicComment, $this> */
    public function publicComment(): BelongsTo
    {
        return $this->belongsTo(PublicComment::class);
    }

    /** @return BelongsTo<Video, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    protected function casts(): array
    {
        return [
            'source_published_at' => 'immutable_datetime',
        ];
    }
}
