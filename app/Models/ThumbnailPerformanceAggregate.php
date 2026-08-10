<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'thumbnail_analysis_profile_id', 'cluster_key', 'label', 'position', 'meets_minimum_sample', 'sample_count',
    'view_sample_count', 'median_views', 'average_views', 'views_per_day_sample_count', 'median_views_per_day',
    'average_views_per_day', 'breakout_sample_count', 'breakout_count', 'breakout_rate_percent', 'evidence_video_ids',
])]
class ThumbnailPerformanceAggregate extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Thumbnail performance aggregates are immutable.'));
    }

    /** @return BelongsTo<ThumbnailAnalysisProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(ThumbnailAnalysisProfile::class, 'thumbnail_analysis_profile_id');
    }

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'meets_minimum_sample' => 'boolean',
            'sample_count' => 'integer',
            'view_sample_count' => 'integer',
            'median_views' => 'decimal:4',
            'average_views' => 'decimal:4',
            'views_per_day_sample_count' => 'integer',
            'median_views_per_day' => 'decimal:6',
            'average_views_per_day' => 'decimal:6',
            'breakout_sample_count' => 'integer',
            'breakout_count' => 'integer',
            'breakout_rate_percent' => 'decimal:4',
            'evidence_video_ids' => 'array',
        ];
    }
}
