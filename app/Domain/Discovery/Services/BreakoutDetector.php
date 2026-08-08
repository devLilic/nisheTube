<?php

namespace App\Domain\Discovery\Services;

use App\Domain\Discovery\Data\BreakoutSignal;
use App\Domain\Discovery\Data\DiscoveryObservation;

class BreakoutDetector
{
    private const SAMPLE_LIMIT = 25;

    /**
     * @param  list<DiscoveryObservation>  $observations
     * @return list<BreakoutSignal>
     */
    public function detect(array $observations): array
    {
        $velocities = array_values(array_map(
            fn (DiscoveryObservation $observation): float => $observation->viewsPerDay,
            array_filter($observations, fn (DiscoveryObservation $observation): bool => $observation->viewsPerDay !== null),
        ));
        $baseline = $this->median($velocities);
        $signals = [];

        foreach ($observations as $observation) {
            $multiplier = $baseline > 0 && $observation->viewsPerDay !== null
                ? $observation->viewsPerDay / $baseline
                : 0.0;
            $reach = max($observation->reachRatio ?? 0.0, 0.0);

            if ($multiplier < 1.5 && $reach < 1.5) {
                continue;
            }

            $velocityScore = min($multiplier / 3, 1) * 65;
            $reachScore = min($reach / 3, 1) * 35;
            $signals[] = new BreakoutSignal(
                observation: $observation,
                velocityMultiplier: round($multiplier, 4),
                strength: round(min($velocityScore + $reachScore, 100), 4),
            );
        }

        usort($signals, fn (BreakoutSignal $left, BreakoutSignal $right): int => [
            -$left->strength,
            $left->observation->providerVideoId,
        ] <=> [
            -$right->strength,
            $right->observation->providerVideoId,
        ]);

        return array_slice($signals, 0, self::SAMPLE_LIMIT);
    }

    /** @param list<float> $values */
    private function median(array $values): float
    {
        if ($values === []) {
            return 0.0;
        }

        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);

        return count($values) % 2 === 0
            ? ($values[$middle - 1] + $values[$middle]) / 2
            : $values[$middle];
    }
}
