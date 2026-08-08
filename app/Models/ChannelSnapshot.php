<?php

namespace App\Models;

use App\Models\Concerns\HasImmutableMetrics;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $channel_id
 * @property int $research_run_id
 * @property int|null $subscriber_count
 * @property int|null $view_count
 * @property int|null $video_count
 * @property bool $subscriber_count_hidden
 * @property array<string, mixed>|null $metadata
 * @property Carbon $collected_at
 */
#[Fillable([
    'channel_id',
    'research_run_id',
    'subscriber_count',
    'view_count',
    'video_count',
    'subscriber_count_hidden',
    'metadata',
    'collected_at',
])]
class ChannelSnapshot extends Model
{
    use HasImmutableMetrics;

    /** @return BelongsTo<Channel, $this> */
    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
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
            'subscriber_count' => 'integer',
            'view_count' => 'integer',
            'video_count' => 'integer',
            'subscriber_count_hidden' => 'boolean',
            'metadata' => 'array',
            'collected_at' => 'immutable_datetime',
        ];
    }
}
