<?php

namespace App\Models;

use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $analyzer_run_id
 * @property int $video_id
 * @property int $video_snapshot_id
 * @property int|null $channel_snapshot_id
 * @property AnalyzerVideoRole $role
 * @property int|null $source_position
 * @property string|null $channel_median_ratio
 * @property string|null $breakout_class
 * @property string|null $threshold_version
 * @property Video $video
 * @property VideoSnapshot $videoSnapshot
 * @property ChannelSnapshot|null $channelSnapshot
 */
#[Fillable([
    'analyzer_run_id', 'video_id', 'video_snapshot_id', 'channel_snapshot_id', 'role', 'source_position',
    'channel_median_ratio', 'breakout_class', 'threshold_version',
])]
class AnalyzerRunVideo extends Model
{
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

    /** @return BelongsTo<VideoSnapshot, $this> */
    public function videoSnapshot(): BelongsTo
    {
        return $this->belongsTo(VideoSnapshot::class);
    }

    /** @return BelongsTo<ChannelSnapshot, $this> */
    public function channelSnapshot(): BelongsTo
    {
        return $this->belongsTo(ChannelSnapshot::class);
    }

    protected function casts(): array
    {
        return [
            'role' => AnalyzerVideoRole::class,
            'source_position' => 'integer',
            'channel_median_ratio' => 'decimal:8',
        ];
    }
}
