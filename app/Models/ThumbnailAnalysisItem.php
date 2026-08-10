<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property int $id
 * @property int $thumbnail_analysis_profile_id
 * @property int $analyzer_run_video_id
 * @property int $video_id
 * @property int|null $source_item_id
 * @property string $role
 * @property string $status
 * @property string $cache_status
 * @property string|null $source_url
 * @property string|null $source_url_hash
 * @property string|null $source_checksum
 * @property string|null $mime_type
 * @property int|null $byte_count
 * @property int|null $width
 * @property int|null $height
 * @property string|null $aspect_ratio
 * @property string|null $average_brightness
 * @property string|null $average_saturation
 * @property string|null $contrast_score
 * @property string|null $edge_density
 * @property string|null $dominant_color
 * @property string|null $brightness_class
 * @property string|null $saturation_class
 * @property string|null $contrast_class
 * @property string|null $composition_class
 * @property string|null $cluster_key
 * @property string|null $confidence_score
 * @property string|null $error_code
 * @property Carbon|null $analyzed_at
 */
#[Fillable([
    'thumbnail_analysis_profile_id', 'analyzer_run_video_id', 'video_id', 'source_item_id', 'role', 'status',
    'cache_status', 'source_url', 'source_url_hash', 'source_checksum', 'mime_type', 'byte_count', 'width', 'height',
    'aspect_ratio', 'average_brightness', 'average_saturation', 'contrast_score', 'edge_density', 'dominant_color',
    'brightness_class', 'saturation_class', 'contrast_class', 'composition_class', 'cluster_key', 'confidence_score',
    'error_code', 'analyzed_at',
])]
class ThumbnailAnalysisItem extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Thumbnail analysis items are immutable.'));
    }

    /** @return BelongsTo<ThumbnailAnalysisProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ThumbnailAnalysisProfile::class, 'thumbnail_analysis_profile_id');
    }

    /** @return BelongsTo<AnalyzerRunVideo, $this> */
    public function membership(): BelongsTo
    {
        return $this->belongsTo(AnalyzerRunVideo::class, 'analyzer_run_video_id');
    }

    /** @return BelongsTo<Video, $this> */
    public function video(): BelongsTo
    {
        return $this->belongsTo(Video::class);
    }

    /** @return BelongsTo<ThumbnailAnalysisItem, $this> */
    public function sourceItem(): BelongsTo
    {
        return $this->belongsTo(self::class, 'source_item_id');
    }

    protected function casts(): array
    {
        return [
            'byte_count' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'aspect_ratio' => 'decimal:4',
            'average_brightness' => 'decimal:4',
            'average_saturation' => 'decimal:4',
            'contrast_score' => 'decimal:4',
            'edge_density' => 'decimal:4',
            'confidence_score' => 'decimal:4',
            'analyzed_at' => 'immutable_datetime',
        ];
    }
}
