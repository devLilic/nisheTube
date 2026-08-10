<?php

namespace App\Domain\Analyzer\Actions;

use App\Domain\Analyzer\Enums\AnalyzerVideoRole;
use App\Domain\Analyzer\Services\RelativePerformanceCalculator;
use App\Models\AnalyzerRun;
use DomainException;

final readonly class CalculateAnalyzerRelativePerformance
{
    public function __construct(private RelativePerformanceCalculator $calculator) {}

    public function handle(AnalyzerRun $run): void
    {
        $anchor = $run->videoMemberships()
            ->where('role', AnalyzerVideoRole::Anchor)
            ->with('videoSnapshot')
            ->first();
        $cohort = $run->videoMemberships()
            ->where('role', AnalyzerVideoRole::ChannelRecentUpload)
            ->with('videoSnapshot')
            ->get();
        $configuredThresholds = config('analyzer.relative_performance.thresholds');

        if (! is_array($configuredThresholds)) {
            throw new DomainException('Analyzer relative-performance thresholds are not configured.');
        }

        $thresholds = [
            'underperformer_max_exclusive' => (float) ($configuredThresholds['underperformer_max_exclusive'] ?? 0.5),
            'normal_max_exclusive' => (float) ($configuredThresholds['normal_max_exclusive'] ?? 1.5),
            'above_average_max_exclusive' => (float) ($configuredThresholds['above_average_max_exclusive'] ?? 3.0),
            'strong_max_inclusive' => (float) ($configuredThresholds['strong_max_inclusive'] ?? 5.0),
        ];
        $minimumBaselineCount = max(1, (int) config('analyzer.relative_performance.minimum_baseline_count', 3));
        $cohortViews = [];

        foreach ($cohort as $membership) {
            $cohortViews[$membership->video_id] = $membership->videoSnapshot->view_count;
        }

        $anchorVideoId = $anchor === null ? 0 : $anchor->video_id;
        $anchorViews = $anchor === null ? null : $anchor->videoSnapshot->view_count;
        $result = $this->calculator->calculate(
            anchorVideoId: $anchorVideoId,
            anchorViews: $anchorViews,
            cohortViewsByVideoId: $cohortViews,
            minimumBaselineCount: $minimumBaselineCount,
            thresholds: $thresholds,
        );
        $videoMetrics = $run->videoMetrics()->first();

        if ($videoMetrics !== null) {
            $videoMetrics->update([
                'channel_median_ratio' => $this->decimalOrNull($result->anchorMedianRatio, 8),
                'channel_average_ratio' => $this->decimalOrNull($result->anchorAverageRatio, 8),
                'recent_rank' => $result->recentRank,
                'recent_percentile' => $this->decimalOrNull($result->recentPercentile, 4),
                'recent_comparison_count' => $result->recentComparisonCount,
                'breakout_class' => $result->anchorClass?->value,
                'threshold_version' => $run->threshold_version,
                'warnings' => $this->warnings($videoMetrics->warnings, $result->anchorWarnings),
            ]);
        }
        $channelMetrics = $run->channelMetrics()->firstOrFail();
        $channelMetrics->update([
            'strong_count' => $result->strongCount,
            'strong_share_percent' => $this->decimalOrNull($result->strongSharePercent, 4),
            'breakout_count' => $result->breakoutCount,
            'breakout_share_percent' => $this->decimalOrNull($result->breakoutSharePercent, 4),
            'threshold_version' => $run->threshold_version,
            'warnings' => $this->warnings($channelMetrics->warnings, $result->cohortWarnings),
        ]);

        foreach ($cohort as $membership) {
            $classification = $result->cohortClassifications[$membership->video_id] ?? null;
            $membership->update([
                'channel_median_ratio' => $this->decimalOrNull($classification['ratio'] ?? null, 8),
                'breakout_class' => $classification['class']->value ?? null,
                'threshold_version' => $run->threshold_version,
            ]);
        }
    }

    /**
     * @param  list<string>|null  $existing
     * @param  list<string>  $additional
     * @return list<string>|null
     */
    private function warnings(?array $existing, array $additional): ?array
    {
        $warnings = array_values(array_unique([...($existing ?? []), ...$additional]));

        return $warnings === [] ? null : $warnings;
    }

    private function decimalOrNull(?float $value, int $scale): ?string
    {
        return $value === null ? null : number_format($value, $scale, '.', '');
    }
}
