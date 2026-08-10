<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $analyzer_run_id
 * @property int $channel_id
 * @property int $recent_valid_count
 * @property int $recent_requested_count
 * @property string $coverage_percent
 * @property string|null $median_views
 * @property string|null $average_views
 * @property int|null $minimum_views
 * @property int|null $maximum_views
 * @property string|null $median_likes
 * @property string|null $median_comments
 * @property string|null $median_duration_seconds
 * @property string|null $average_duration_seconds
 * @property int|null $minimum_duration_seconds
 * @property int|null $maximum_duration_seconds
 * @property string|null $median_age_days
 * @property string|null $average_upload_gap_days
 * @property string|null $median_upload_gap_days
 * @property string|null $longest_upload_gap_days
 * @property string|null $videos_per_week
 * @property string|null $videos_per_month
 * @property array<string, int> $duration_distribution
 * @property list<array{id: string, name: string|null, count: int}> $category_distribution
 * @property int|null $strong_count
 * @property string|null $strong_share_percent
 * @property int|null $breakout_count
 * @property string|null $breakout_share_percent
 * @property string|null $threshold_version
 * @property int $momentum_recent_count
 * @property int $momentum_previous_count
 * @property string|null $momentum_recent_median_views_per_day
 * @property string|null $momentum_previous_median_views_per_day
 * @property string|null $momentum_ratio
 * @property string|null $momentum_class
 * @property int $consistency_sample_count
 * @property string|null $consistency_score
 * @property string|null $consistency_class
 * @property int $duration_performance_sample_count
 * @property string|null $duration_performance_correlation
 * @property string|null $duration_performance_class
 * @property array<string, array{count: int, median_views_per_day: float|null}>|null $duration_performance_buckets
 * @property int|null $previous_channel_snapshot_id
 * @property int|null $observed_elapsed_seconds
 * @property int|null $observed_view_delta
 * @property int|null $observed_subscriber_delta
 * @property int|null $observed_video_delta
 * @property string|null $observed_view_growth_percent
 * @property string|null $behavior_version
 * @property string $calculation_version
 * @property array<string, mixed> $input_summary
 * @property list<string>|null $warnings
 * @property Carbon $calculated_at
 */
#[Fillable([
    'analyzer_run_id', 'channel_id', 'recent_valid_count', 'recent_requested_count', 'coverage_percent',
    'median_views', 'average_views', 'minimum_views', 'maximum_views', 'median_likes', 'median_comments',
    'median_duration_seconds', 'average_duration_seconds', 'minimum_duration_seconds', 'maximum_duration_seconds',
    'median_age_days', 'average_upload_gap_days', 'median_upload_gap_days', 'longest_upload_gap_days',
    'videos_per_week', 'videos_per_month', 'duration_distribution', 'category_distribution',
    'strong_count', 'strong_share_percent', 'breakout_count', 'breakout_share_percent', 'threshold_version',
    'momentum_recent_count', 'momentum_previous_count', 'momentum_recent_median_views_per_day',
    'momentum_previous_median_views_per_day', 'momentum_ratio', 'momentum_class',
    'consistency_sample_count', 'consistency_score', 'consistency_class',
    'duration_performance_sample_count', 'duration_performance_correlation', 'duration_performance_class',
    'duration_performance_buckets', 'previous_channel_snapshot_id', 'observed_elapsed_seconds',
    'observed_view_delta', 'observed_subscriber_delta', 'observed_video_delta',
    'observed_view_growth_percent', 'behavior_version',
    'calculation_version', 'input_summary', 'warnings', 'calculated_at',
])]
class ChannelAnalysisMetric extends Model
{
    /** @return BelongsTo<AnalyzerRun, $this> */
    public function analyzerRun(): BelongsTo
    {
        return $this->belongsTo(AnalyzerRun::class);
    }

    protected function casts(): array
    {
        return [
            'recent_valid_count' => 'integer',
            'recent_requested_count' => 'integer',
            'coverage_percent' => 'decimal:4',
            'median_views' => 'decimal:4',
            'average_views' => 'decimal:4',
            'minimum_views' => 'integer',
            'maximum_views' => 'integer',
            'median_likes' => 'decimal:4',
            'median_comments' => 'decimal:4',
            'median_duration_seconds' => 'decimal:4',
            'average_duration_seconds' => 'decimal:4',
            'minimum_duration_seconds' => 'integer',
            'maximum_duration_seconds' => 'integer',
            'median_age_days' => 'decimal:4',
            'average_upload_gap_days' => 'decimal:6',
            'median_upload_gap_days' => 'decimal:6',
            'longest_upload_gap_days' => 'decimal:6',
            'videos_per_week' => 'decimal:6',
            'videos_per_month' => 'decimal:6',
            'duration_distribution' => 'array',
            'category_distribution' => 'array',
            'strong_count' => 'integer',
            'strong_share_percent' => 'decimal:4',
            'breakout_count' => 'integer',
            'breakout_share_percent' => 'decimal:4',
            'momentum_recent_count' => 'integer',
            'momentum_previous_count' => 'integer',
            'momentum_recent_median_views_per_day' => 'decimal:6',
            'momentum_previous_median_views_per_day' => 'decimal:6',
            'momentum_ratio' => 'decimal:8',
            'consistency_sample_count' => 'integer',
            'consistency_score' => 'decimal:4',
            'duration_performance_sample_count' => 'integer',
            'duration_performance_correlation' => 'decimal:8',
            'duration_performance_buckets' => 'array',
            'observed_elapsed_seconds' => 'integer',
            'observed_view_delta' => 'integer',
            'observed_subscriber_delta' => 'integer',
            'observed_video_delta' => 'integer',
            'observed_view_growth_percent' => 'decimal:6',
            'input_summary' => 'array',
            'warnings' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
