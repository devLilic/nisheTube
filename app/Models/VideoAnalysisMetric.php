<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $analyzer_run_id
 * @property int $video_id
 * @property int $age_seconds
 * @property string|null $lifetime_views_per_day
 * @property string|null $views_to_subscribers_ratio
 * @property string|null $channel_median_ratio
 * @property string|null $channel_average_ratio
 * @property int|null $recent_rank
 * @property string|null $recent_percentile
 * @property int $recent_comparison_count
 * @property string|null $breakout_class
 * @property string|null $threshold_version
 * @property int|null $previous_video_snapshot_id
 * @property int|null $observed_elapsed_seconds
 * @property int|null $observed_view_delta
 * @property int|null $observed_like_delta
 * @property int|null $observed_comment_delta
 * @property string|null $observed_recent_views_per_day
 * @property string|null $observed_view_growth_percent
 * @property string|null $behavior_version
 * @property string|null $like_rate_percent
 * @property string|null $comment_rate_percent
 * @property string|null $public_engagement_rate_percent
 * @property string $calculation_version
 * @property array<string, mixed> $input_summary
 * @property list<string>|null $warnings
 * @property Carbon $calculated_at
 */
#[Fillable([
    'analyzer_run_id', 'video_id', 'age_seconds', 'lifetime_views_per_day',
    'views_to_subscribers_ratio', 'channel_median_ratio', 'channel_average_ratio', 'recent_rank',
    'recent_percentile', 'recent_comparison_count', 'breakout_class', 'threshold_version',
    'previous_video_snapshot_id', 'observed_elapsed_seconds', 'observed_view_delta',
    'observed_like_delta', 'observed_comment_delta', 'observed_recent_views_per_day',
    'observed_view_growth_percent', 'behavior_version',
    'like_rate_percent', 'comment_rate_percent',
    'public_engagement_rate_percent', 'calculation_version', 'input_summary', 'warnings', 'calculated_at',
])]
class VideoAnalysisMetric extends Model
{
    /** @return BelongsTo<AnalyzerRun, $this> */
    public function analyzerRun(): BelongsTo
    {
        return $this->belongsTo(AnalyzerRun::class);
    }

    protected function casts(): array
    {
        return [
            'age_seconds' => 'integer',
            'lifetime_views_per_day' => 'decimal:6',
            'views_to_subscribers_ratio' => 'decimal:8',
            'channel_median_ratio' => 'decimal:8',
            'channel_average_ratio' => 'decimal:8',
            'recent_rank' => 'integer',
            'recent_percentile' => 'decimal:4',
            'recent_comparison_count' => 'integer',
            'observed_elapsed_seconds' => 'integer',
            'observed_view_delta' => 'integer',
            'observed_like_delta' => 'integer',
            'observed_comment_delta' => 'integer',
            'observed_recent_views_per_day' => 'decimal:6',
            'observed_view_growth_percent' => 'decimal:6',
            'like_rate_percent' => 'decimal:6',
            'comment_rate_percent' => 'decimal:6',
            'public_engagement_rate_percent' => 'decimal:6',
            'input_summary' => 'array',
            'warnings' => 'array',
            'calculated_at' => 'immutable_datetime',
        ];
    }
}
