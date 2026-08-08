<?php

namespace App\Domain\Scoring\Services;

final class RobustStatistics
{
    /** @param list<float|int> $values */
    public function median(array $values): ?float
    {
        return $this->percentile($values, 50);
    }

    /** @param list<float|int> $values */
    public function percentile(array $values, float $percentile): ?float
    {
        if ($values === []) {
            return null;
        }

        $numbers = array_map(static fn (float|int $value): float => (float) $value, $values);
        sort($numbers, SORT_NUMERIC);

        $rank = ($this->clamp($percentile, 0, 100) / 100) * (count($numbers) - 1);
        $lowerIndex = (int) floor($rank);
        $upperIndex = (int) ceil($rank);

        if ($lowerIndex === $upperIndex) {
            return $numbers[$lowerIndex];
        }

        $fraction = $rank - $lowerIndex;

        return $numbers[$lowerIndex] + (($numbers[$upperIndex] - $numbers[$lowerIndex]) * $fraction);
    }

    /** @param list<float|int> $values */
    public function winsorizedMean(array $values, float $lowerPercentile, float $upperPercentile): ?float
    {
        if ($values === []) {
            return null;
        }

        $lower = $this->percentile($values, $lowerPercentile);
        $upper = $this->percentile($values, $upperPercentile);

        if ($lower === null || $upper === null) {
            return null;
        }

        $winsorized = array_map(
            fn (float|int $value): float => $this->clamp((float) $value, $lower, $upper),
            $values,
        );

        return array_sum($winsorized) / count($winsorized);
    }

    public function linearScore(float $value, float $low, float $high): float
    {
        if ($high <= $low) {
            return $value >= $high ? 100.0 : 0.0;
        }

        return $this->clamp((($value - $low) / ($high - $low)) * 100, 0, 100);
    }

    public function inverseLinearScore(float $value, float $good, float $poor): float
    {
        return 100 - $this->linearScore($value, $good, $poor);
    }

    public function logarithmicScore(float $value, float $low, float $high): float
    {
        if ($value <= 0) {
            return 0.0;
        }

        return $this->linearScore(log10($value + 1), log10($low + 1), log10($high + 1));
    }

    public function clamp(float $value, float $minimum = 0, float $maximum = 100): float
    {
        return min($maximum, max($minimum, $value));
    }
}
