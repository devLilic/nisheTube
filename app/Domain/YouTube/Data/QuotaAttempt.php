<?php

namespace App\Domain\YouTube\Data;

use InvalidArgumentException;

final readonly class QuotaAttempt
{
    public function __construct(
        public string $provider,
        public string $bucket,
        public string $endpoint,
        public int $estimatedCost,
        public ?int $userId = null,
        public ?int $researchRunId = null,
    ) {
        if (trim($provider) === '' || trim($bucket) === '' || trim($endpoint) === '') {
            throw new InvalidArgumentException('Quota attempts require provider, bucket, and endpoint values.');
        }

        if ($estimatedCost < 1) {
            throw new InvalidArgumentException('Quota attempt cost must be at least one.');
        }

        if ($userId !== null && $userId < 1) {
            throw new InvalidArgumentException('Quota attempt user identifiers must be positive.');
        }

        if ($researchRunId !== null && $researchRunId < 1) {
            throw new InvalidArgumentException('Quota attempt run identifiers must be positive.');
        }
    }
}
