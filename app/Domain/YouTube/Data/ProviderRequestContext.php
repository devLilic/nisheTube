<?php

namespace App\Domain\YouTube\Data;

use InvalidArgumentException;

final readonly class ProviderRequestContext
{
    public function __construct(
        public ?int $userId = null,
        public ?int $researchRunId = null,
        public ?int $collectionRunId = null,
    ) {
        if ($userId !== null && $userId < 1) {
            throw new InvalidArgumentException('The provider user context must be a positive identifier.');
        }

        if ($researchRunId !== null && $researchRunId < 1) {
            throw new InvalidArgumentException('The provider run context must be a positive identifier.');
        }

        if ($collectionRunId !== null && $collectionRunId < 1) {
            throw new InvalidArgumentException('The provider collection context must be a positive identifier.');
        }
    }
}
