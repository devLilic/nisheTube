<?php

namespace App\Domain\Analyzer\Data;

use App\Domain\Analyzer\Enums\BreakoutClass;

final readonly class RelativePerformanceResult
{
    /**
     * @param  array<int, array{ratio: float, class: BreakoutClass}>  $cohortClassifications
     * @param  list<string>  $anchorWarnings
     * @param  list<string>  $cohortWarnings
     */
    public function __construct(
        public ?float $anchorMedianRatio,
        public ?float $anchorAverageRatio,
        public ?int $recentRank,
        public ?float $recentPercentile,
        public int $recentComparisonCount,
        public ?BreakoutClass $anchorClass,
        public array $cohortClassifications,
        public ?int $strongCount,
        public ?float $strongSharePercent,
        public ?int $breakoutCount,
        public ?float $breakoutSharePercent,
        public array $anchorWarnings = [],
        public array $cohortWarnings = [],
    ) {}
}
