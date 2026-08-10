<?php

namespace App\Domain\Analyzer\Data;

final readonly class ChannelBehaviorResult
{
    /**
     * @param  array<string, array{count: int, median_views_per_day: float|null}>  $durationPerformanceBuckets
     * @param  list<string>  $warnings
     */
    public function __construct(
        public int $momentumRecentCount,
        public int $momentumPreviousCount,
        public ?float $momentumRecentMedianViewsPerDay,
        public ?float $momentumPreviousMedianViewsPerDay,
        public ?float $momentumRatio,
        public ?string $momentumClass,
        public int $consistencySampleCount,
        public ?float $consistencyScore,
        public ?string $consistencyClass,
        public int $durationPerformanceSampleCount,
        public ?float $durationPerformanceCorrelation,
        public ?string $durationPerformanceClass,
        public array $durationPerformanceBuckets,
        public array $warnings,
    ) {}
}
