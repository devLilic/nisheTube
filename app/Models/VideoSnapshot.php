<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableMetrics;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $video_id
 * @property int $research_run_id
 * @property int|null $view_count
 * @property int|null $like_count
 * @property int|null $comment_count
 * @property int|null $age_seconds
 * @property string|null $views_per_day
 * @property string|null $views_to_subscribers_ratio
 * @property Carbon $collected_at
 */
#[Fillable([
    'video_id',
    'research_run_id',
    'view_count',
    'like_count',
    'comment_count',
    'age_seconds',
    'views_per_day',
    'views_to_subscribers_ratio',
    'collected_at',
])]
class VideoSnapshot extends Model
{
    use HasImmutableMetrics;

    /** @return BelongsTo<Video, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /** @return BelongsTo<ResearchRun, $this> */
    public function researchRun(): BelongsTo
    {
        return $this->belongsTo(ResearchRun::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'view_count' => 'integer',
            'like_count' => 'integer',
            'comment_count' => 'integer',
            'age_seconds' => 'integer',
            'views_per_day' => 'decimal:6',
            'views_to_subscribers_ratio' => 'decimal:8',
            'collected_at' => 'immutable_datetime',
        ];
    }
}
