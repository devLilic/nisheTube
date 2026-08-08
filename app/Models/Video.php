<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $provider
 * @property string $provider_video_id
 * @property int $channel_id
 * @property string $title
 * @property string|null $thumbnail_url
 * @property Carbon $published_at
 * @property int|null $duration_seconds
 * @property string|null $category_id
 * @property bool|null $is_short
 */
#[Fillable([
    'provider',
    'provider_video_id',
    'channel_id',
    'title',
    'thumbnail_url',
    'published_at',
    'duration_seconds',
    'category_id',
    'is_short',
])]
class Video extends Model
{
    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    /** @return HasMany<VideoSnapshot, $this> */
    public function snapshots(): HasMany
    {
        return $this->hasMany(VideoSnapshot::class);
    }

    /** @return HasMany<ResearchRunVideo, $this> */
    public function runMemberships(): HasMany
    {
        return $this->hasMany(ResearchRunVideo::class);
    }

    /** @return BelongsToMany<ResearchRun, $this, ResearchRunVideo, 'pivot'> */
    public function researchRuns(): BelongsToMany
    {
        return $this->belongsToMany(ResearchRun::class, 'research_run_videos')
            ->using(ResearchRunVideo::class)
            ->withPivot(['result_rank', 'page_number', 'provider_order', 'matched_query_metadata'])
            ->withTimestamps();
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'published_at' => 'immutable_datetime',
            'duration_seconds' => 'integer',
            'is_short' => 'boolean',
        ];
    }
}
