<?php

namespace App\Domain\Catalog\Data;

final readonly class DerivedVideoMetrics
{
    public function __construct(
        public int $ageSeconds,
        public ?string $viewsPerDay,
        public ?string $viewsToSubscribersRatio,
    ) {}
}
