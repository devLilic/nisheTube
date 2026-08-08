<?php

namespace App\Domain\Discovery\Data;

use Carbon\CarbonImmutable;

final readonly class DiscoveryObservation
{
    public function __construct(
        public int $seedId,
        public string $seedQuery,
        public string $providerVideoId,
        public string $providerChannelId,
        public string $title,
        public CarbonImmutable $publishedAt,
        public ?int $viewCount,
        public ?float $viewsPerDay,
        public ?float $reachRatio,
    ) {}
}
