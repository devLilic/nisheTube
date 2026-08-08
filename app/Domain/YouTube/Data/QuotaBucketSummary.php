<?php

namespace App\Domain\YouTube\Data;

use Carbon\CarbonImmutable;

final readonly class QuotaBucketSummary
{
    public function __construct(
        public string $bucket,
        public int $allowance,
        public int $used,
        public int $remaining,
        public bool $exhausted,
        public ?string $lastEndpoint,
        public ?int $lastCost,
        public ?CarbonImmutable $lastOccurredAt,
        public ?string $lastOutcome,
    ) {}

    /** @return array<string, bool|int|string|null> */
    public function toSafeArray(): array
    {
        return [
            'bucket' => $this->bucket,
            'allowance' => $this->allowance,
            'used' => $this->used,
            'remaining' => $this->remaining,
            'exhausted' => $this->exhausted,
            'last_endpoint' => $this->lastEndpoint,
            'last_cost' => $this->lastCost,
            'last_occurred_at' => $this->lastOccurredAt?->toIso8601String(),
            'last_outcome' => $this->lastOutcome,
        ];
    }
}
