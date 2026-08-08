<?php

namespace App\Domain\Scoring\Data;

final readonly class ScoringInput
{
    /**
     * @param  list<ScoringVideoInput>  $videos
     * @param  list<string>  $collectionWarnings
     */
    public function __construct(
        public int $requestedResultCount,
        public int $collectedResultCount,
        public int $enrichedResultCount,
        public array $collectionWarnings,
        public array $videos,
        public ?float $previousMedianViewsPerDay,
    ) {}
}
