<?php

namespace App\Domain\Discovery\Data;

final readonly class BreakoutSignal
{
    public function __construct(
        public DiscoveryObservation $observation,
        public float $velocityMultiplier,
        public float $strength,
    ) {}
}
