<?php

namespace App\Domain\Analyzer\Services;

use App\Domain\Analyzer\Data\ChannelBehaviorResult;

final class ChannelBehaviorCalculator
{
    /**
     * @param  list<array{views_per_day: float|null, duration_seconds: int|null}>  $videos
     * @param  array{declining_max_exclusive: float, stable_max_inclusive: float}  $momentumThresholds
     * @param  array{consistent_minimum: float, mixed_minimum: float}  $consistencyThresholds
     * @param  array{weak_max_exclusive: float, moderate_max_exclusive: float}  $correlationThresholds
     */
    public function calculate(
        array $videos,
        int $momentumBlockSize,
        int $minimumConsistencySample,
        int $minimumCorrelationSample,
        array $momentumThresholds,
        array $consistencyThresholds,
        array $correlationThresholds,
    ): ChannelBehaviorResult {
        $momentumBlockSize = max(1, $momentumBlockSize);
        $recent = $this->validRates(array_slice($videos, 0, $momentumBlockSize));
        $previous = $this->validRates(array_slice($videos, $momentumBlockSize, $momentumBlockSize));
        $recentMedian = $this->median($recent);
        $previousMedian = $this->median($previous);
        $momentumRatio = null;
        $momentumClass = null;
        $warnings = [];

        if (count($recent) < $momentumBlockSize || count($previous) < $momentumBlockSize) {
            $warnings[] = "Momentum needs {$momentumBlockSize} public Lifetime Average Views/Day values in both the recent and preceding blocks.";
        } elseif ($previousMedian === null || $previousMedian <= 0) {
            $warnings[] = 'Momentum is unavailable because the preceding block median is zero.';
        } else {
            $momentumRatio = $recentMedian / $previousMedian;
            $momentumClass = match (true) {
                $momentumRatio < $momentumThresholds['declining_max_exclusive'] => 'declining',
                $momentumRatio <= $momentumThresholds['stable_max_inclusive'] => 'stable',
                default => 'growing',
            };
        }

        $rates = $this->validRates($videos);
        $consistencyMedian = $this->median($rates);
        $consistencyScore = null;
        $consistencyClass = null;

        if (count($rates) < max(1, $minimumConsistencySample)) {
            $warnings[] = 'Consistency has insufficient public Lifetime Average Views/Day samples.';
        } elseif ($consistencyMedian === null || $consistencyMedian <= 0) {
            $warnings[] = 'Consistency is unavailable because the cohort median is zero.';
        } else {
            $deviations = array_map(
                static fn (float $value): float => abs($value - $consistencyMedian),
                $rates,
            );
            $mad = $this->median($deviations) ?? 0.0;
            $consistencyScore = max(0.0, min(100.0, 100.0 * (1.0 - ($mad / $consistencyMedian))));
            $consistencyClass = match (true) {
                $consistencyScore >= $consistencyThresholds['consistent_minimum'] => 'consistent',
                $consistencyScore >= $consistencyThresholds['mixed_minimum'] => 'mixed',
                default => 'volatile',
            };
        }

        $pairs = array_values(array_filter($videos, static fn (array $video): bool => $video['views_per_day'] !== null && $video['duration_seconds'] !== null
        ));
        $correlation = null;
        $correlationClass = null;

        if (count($pairs) < max(2, $minimumCorrelationSample)) {
            $warnings[] = 'Duration/performance correlation has insufficient videos with both duration and Lifetime Average Views/Day.';
        } else {
            $durationRanks = $this->ranks(array_map(static fn (array $video): float => (float) $video['duration_seconds'], $pairs));
            $performanceRanks = $this->ranks(array_map(static fn (array $video): float => (float) $video['views_per_day'], $pairs));
            $correlation = $this->pearson($durationRanks, $performanceRanks);

            if ($correlation === null) {
                $warnings[] = 'Duration/performance correlation is unavailable because duration or performance has no variation.';
            } else {
                $strength = match (true) {
                    abs($correlation) < $correlationThresholds['weak_max_exclusive'] => 'weak',
                    abs($correlation) < $correlationThresholds['moderate_max_exclusive'] => 'moderate',
                    default => 'strong',
                };
                $direction = $correlation < 0 ? 'negative' : 'positive';
                $correlationClass = "{$strength}_{$direction}";
            }
        }

        return new ChannelBehaviorResult(
            momentumRecentCount: count($recent),
            momentumPreviousCount: count($previous),
            momentumRecentMedianViewsPerDay: $recentMedian,
            momentumPreviousMedianViewsPerDay: $previousMedian,
            momentumRatio: $momentumRatio,
            momentumClass: $momentumClass,
            consistencySampleCount: count($rates),
            consistencyScore: $consistencyScore,
            consistencyClass: $consistencyClass,
            durationPerformanceSampleCount: count($pairs),
            durationPerformanceCorrelation: $correlation,
            durationPerformanceClass: $correlationClass,
            durationPerformanceBuckets: $this->durationBuckets($pairs),
            warnings: $warnings,
        );
    }

    /**
     * @param  list<array{views_per_day: float|null, duration_seconds: int|null}>  $videos
     * @return list<float>
     */
    private function validRates(array $videos): array
    {
        return array_values(array_map(
            static fn (array $video): float => (float) $video['views_per_day'],
            array_filter($videos, static fn (array $video): bool => $video['views_per_day'] !== null),
        ));
    }

    /** @param list<float> $values */
    private function median(array $values): ?float
    {
        if ($values === []) {
            return null;
        }

        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;
    }

    /**
     * @param  list<float>  $values
     * @return list<float>
     */
    private function ranks(array $values): array
    {
        $ordered = $values;
        sort($ordered, SORT_NUMERIC);
        $ranksByValue = [];

        for ($index = 0; $index < count($ordered);) {
            $end = $index;
            while ($end + 1 < count($ordered) && $ordered[$end + 1] === $ordered[$index]) {
                $end++;
            }
            $ranksByValue[(string) $ordered[$index]] = (($index + 1) + ($end + 1)) / 2;
            $index = $end + 1;
        }

        return array_map(static fn (float $value): float => $ranksByValue[(string) $value], $values);
    }

    /**
     * @param  list<float>  $left
     * @param  list<float>  $right
     */
    private function pearson(array $left, array $right): ?float
    {
        $count = count($left);
        $leftMean = array_sum($left) / $count;
        $rightMean = array_sum($right) / $count;
        $numerator = 0.0;
        $leftSquared = 0.0;
        $rightSquared = 0.0;

        for ($index = 0; $index < $count; $index++) {
            $leftDelta = $left[$index] - $leftMean;
            $rightDelta = $right[$index] - $rightMean;
            $numerator += $leftDelta * $rightDelta;
            $leftSquared += $leftDelta ** 2;
            $rightSquared += $rightDelta ** 2;
        }

        $denominator = sqrt($leftSquared * $rightSquared);

        return $denominator <= 0 ? null : max(-1.0, min(1.0, $numerator / $denominator));
    }

    /**
     * @param  list<array{views_per_day: float|null, duration_seconds: int|null}>  $pairs
     * @return array<string, array{count: int, median_views_per_day: float|null}>
     */
    private function durationBuckets(array $pairs): array
    {
        $buckets = ['under_5' => [], '5_to_10' => [], '10_to_20' => [], '20_to_40' => [], '40_plus' => []];

        foreach ($pairs as $pair) {
            $key = match (true) {
                $pair['duration_seconds'] < 300 => 'under_5',
                $pair['duration_seconds'] < 600 => '5_to_10',
                $pair['duration_seconds'] < 1200 => '10_to_20',
                $pair['duration_seconds'] < 2400 => '20_to_40',
                default => '40_plus',
            };
            $buckets[$key][] = (float) $pair['views_per_day'];
        }

        return array_map(fn (array $values): array => [
            'count' => count($values),
            'median_views_per_day' => $this->median($values),
        ], $buckets);
    }
}
