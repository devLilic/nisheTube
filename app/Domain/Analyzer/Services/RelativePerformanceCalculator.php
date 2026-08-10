<?php

namespace App\Domain\Analyzer\Services;

use App\Domain\Analyzer\Data\RelativePerformanceResult;
use App\Domain\Analyzer\Enums\BreakoutClass;

final class RelativePerformanceCalculator
{
    /**
     * @param  array<int, int|null>  $cohortViewsByVideoId
     * @param  array{
     *     underperformer_max_exclusive: float,
     *     normal_max_exclusive: float,
     *     above_average_max_exclusive: float,
     *     strong_max_inclusive: float
     * }  $thresholds
     */
    public function calculate(
        int $anchorVideoId,
        ?int $anchorViews,
        array $cohortViewsByVideoId,
        int $minimumBaselineCount,
        array $thresholds,
    ): RelativePerformanceResult {
        $validCohort = array_filter(
            $cohortViewsByVideoId,
            static fn (?int $views): bool => $views !== null && $views >= 0,
        );
        $anchorBaseline = $validCohort;
        unset($anchorBaseline[$anchorVideoId]);
        $anchorWarnings = [];
        $cohortWarnings = [];
        $anchorMedian = $this->median(array_values($anchorBaseline));
        $anchorAverage = $this->average(array_values($anchorBaseline));
        $anchorMedianRatio = null;
        $anchorAverageRatio = null;
        $anchorClass = null;

        if ($anchorViews === null) {
            $anchorWarnings[] = 'Anchor view count is unavailable, so relative performance, rank, and percentile are unavailable.';
        }

        if (count($anchorBaseline) < $minimumBaselineCount) {
            $anchorWarnings[] = "At least {$minimumBaselineCount} other recent videos with public views are required for relative performance.";
        } elseif ($anchorMedian === null || $anchorMedian <= 0) {
            $anchorWarnings[] = 'The recent baseline median is zero, so ratio and breakout classification are unavailable.';
        } elseif ($anchorViews !== null) {
            $anchorMedianRatio = $anchorViews / $anchorMedian;
            $anchorClass = $this->classify($anchorMedianRatio, $thresholds);
        }

        if (
            count($anchorBaseline) >= $minimumBaselineCount
            && $anchorViews !== null
            && $anchorAverage !== null
            && $anchorAverage > 0
        ) {
            $anchorAverageRatio = $anchorViews / $anchorAverage;
        }

        $comparison = $validCohort;

        if ($anchorViews !== null) {
            $comparison[$anchorVideoId] = $anchorViews;
        }

        [$rank, $percentile] = $anchorViews === null
            ? [null, null]
            : $this->rankAndPercentile($anchorViews, array_values($comparison));
        $cohortClassifications = [];
        $strongCount = null;
        $strongShare = null;
        $breakoutCount = null;
        $breakoutShare = null;
        $cohortMedian = $this->median(array_values($validCohort));

        if (count($validCohort) < $minimumBaselineCount) {
            $cohortWarnings[] = "At least {$minimumBaselineCount} recent videos with public views are required for outlier detection.";
        } elseif ($cohortMedian === null || $cohortMedian <= 0) {
            $cohortWarnings[] = 'The channel median view count is zero, so recent outlier classes are unavailable.';
        } else {
            foreach ($validCohort as $videoId => $views) {
                $ratio = $views / $cohortMedian;
                $cohortClassifications[$videoId] = [
                    'ratio' => $ratio,
                    'class' => $this->classify($ratio, $thresholds),
                ];
            }

            $strongCount = count(array_filter(
                $cohortClassifications,
                static fn (array $classification): bool => $classification['ratio'] >= $thresholds['above_average_max_exclusive'],
            ));
            $breakoutCount = count(array_filter(
                $cohortClassifications,
                static fn (array $classification): bool => $classification['ratio'] > $thresholds['strong_max_inclusive'],
            ));
            $strongShare = ($strongCount / count($validCohort)) * 100;
            $breakoutShare = ($breakoutCount / count($validCohort)) * 100;
        }

        return new RelativePerformanceResult(
            anchorMedianRatio: $anchorMedianRatio,
            anchorAverageRatio: $anchorAverageRatio,
            recentRank: $rank,
            recentPercentile: $percentile,
            recentComparisonCount: count($comparison),
            anchorClass: $anchorClass,
            cohortClassifications: $cohortClassifications,
            strongCount: $strongCount,
            strongSharePercent: $strongShare,
            breakoutCount: $breakoutCount,
            breakoutSharePercent: $breakoutShare,
            anchorWarnings: $anchorWarnings,
            cohortWarnings: $cohortWarnings,
        );
    }

    /**
     * @param  array{
     *     underperformer_max_exclusive: float,
     *     normal_max_exclusive: float,
     *     above_average_max_exclusive: float,
     *     strong_max_inclusive: float
     * }  $thresholds
     */
    private function classify(float $ratio, array $thresholds): BreakoutClass
    {
        return match (true) {
            $ratio < $thresholds['underperformer_max_exclusive'] => BreakoutClass::Underperformer,
            $ratio < $thresholds['normal_max_exclusive'] => BreakoutClass::Normal,
            $ratio < $thresholds['above_average_max_exclusive'] => BreakoutClass::AboveAverage,
            $ratio <= $thresholds['strong_max_inclusive'] => BreakoutClass::Strong,
            default => BreakoutClass::Breakout,
        };
    }

    /** @param list<int> $values */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 1
            ? (float) $values[$middle]
            : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /** @param list<int> $values */
    private function average(array $values): ?float
    {
        return $values === [] ? null : array_sum($values) / count($values);
    }

    /**
     * @param  list<int>  $values
     * @return array{int|null, float|null}
     */
    private function rankAndPercentile(int $anchorViews, array $values): array
    {
        if ($values === []) {
            return [null, null];
        }

        $greater = count(array_filter($values, static fn (int $views): bool => $views > $anchorViews));
        $below = count(array_filter($values, static fn (int $views): bool => $views < $anchorViews));
        $equal = count($values) - $greater - $below;

        return [
            $greater + 1,
            (($below + ($equal / 2)) / count($values)) * 100,
        ];
    }
}
