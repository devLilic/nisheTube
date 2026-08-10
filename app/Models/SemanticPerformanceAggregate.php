<?php

namespace App\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $group_type
 * @property string $label
 * @property string $label_key
 * @property bool $is_unclassified
 * @property bool $meets_minimum_sample
 * @property int $sample_count
 * @property int $view_sample_count
 * @property string|null $median_views
 * @property string|null $average_views
 * @property int $views_per_day_sample_count
 * @property string|null $median_views_per_day
 * @property string|null $average_views_per_day
 * @property int $breakout_sample_count
 * @property int $breakout_count
 * @property string|null $breakout_rate_percent
 * @property list<string> $evidence_video_ids
 */
#[Fillable([
    'semantic_performance_profile_id', 'group_type', 'label', 'label_key', 'is_unclassified', 'meets_minimum_sample',
    'position', 'sample_count', 'view_sample_count', 'median_views', 'average_views', 'views_per_day_sample_count',
    'median_views_per_day', 'average_views_per_day', 'breakout_sample_count', 'breakout_count',
    'breakout_rate_percent', 'evidence_video_ids',
])]
class SemanticPerformanceAggregate extends Model
{
    protected static function booted(): void
    {
        static::updating(fn (): never => throw new DomainException('Semantic performance aggregates are immutable.'));
    }

    /** @return BelongsTo<SemanticPerformanceProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(SemanticPerformanceProfile::class, 'semantic_performance_profile_id');
    }

    protected function casts(): array
    {
        return [
            'is_unclassified' => 'boolean',
            'meets_minimum_sample' => 'boolean',
            'position' => 'integer',
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
