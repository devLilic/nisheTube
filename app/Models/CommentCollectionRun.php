<?php

namespace App\Models;

use App\Domain\Comments\Enums\CommentCollectionStatus;
use App\Models\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int $user_id
 * @property int $analyzer_run_id
 * @property int $video_id
 * @property string $provider
 * @property string $provider_video_id
 * @property CommentCollectionStatus $status
 * @property int $max_comments
 * @property int $page_size
 * @property string|null $next_page_token
 * @property int $pages_collected
 * @property int $comments_collected
 * @property int|null $reported_total_results
 * @property string $reply_scope
 * @property string|null $error_code
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $collected_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $failed_at
 * @property AnalyzerRun $analyzerRun
 */
#[Fillable([
    'user_id', 'analyzer_run_id', 'video_id', 'provider', 'provider_video_id', 'status', 'max_comments',
    'page_size', 'next_page_token', 'pages_collected', 'comments_collected', 'reported_total_results',
    'reply_scope', 'error_code', 'error_message', 'started_at', 'collected_at', 'completed_at', 'failed_at',
])]
class CommentCollectionRun extends Model
{
    use HasPublicId;

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AnalyzerRun, $this> */
    public function analyzerRun(): BelongsTo
    {
        return $this->belongsTo(AnalyzerRun::class);
    }

    /** @return BelongsTo<Video, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /** @return HasMany<PublicComment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(PublicComment::class);
    }

    /** @return HasMany<AudienceSignalProfile, $this> */
    public function audienceSignalProfiles(): HasMany
    {
        return $this->hasMany(AudienceSignalProfile::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CommentCollectionStatus::class,
            'max_comments' => 'integer',
            'page_size' => 'integer',
            'pages_collected' => 'integer',
            'comments_collected' => 'integer',
            'reported_total_results' => 'integer',
            'started_at' => 'immutable_datetime',
            'collected_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
        ];
    }
}
