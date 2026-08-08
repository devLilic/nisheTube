<?php

namespace App\Domain\YouTube\Data;

use Carbon\CarbonImmutable;

final readonly class QuotaSummary
{
    /** @param list<QuotaBucketSummary> $buckets */
    public function __construct(
        public CarbonImmutable $generatedAt,
        public CarbonImmutable $resetAt,
        public array $buckets,
    ) {}

    /** @return array<string, mixed> */
    public function toSafeArray(): array
    {
        return [
            'label' => 'NisheTube estimate',
            'authoritative' => false,
            'generated_at' => $this->generatedAt->toIso8601String(),
            'reset_at' => $this->resetAt->toIso8601String(),
            'buckets' => array_map(
                fn (QuotaBucketSummary $bucket): array => $bucket->toSafeArray(),
                $this->buckets,
            ),
        ];
    }
}
