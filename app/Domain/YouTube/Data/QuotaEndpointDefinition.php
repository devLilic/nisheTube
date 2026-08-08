<?php

namespace App\Domain\YouTube\Data;

use InvalidArgumentException;

final readonly class QuotaEndpointDefinition
{
    public function __construct(
        public string $name,
        public string $path,
        public string $bucket,
        public int $cost,
    ) {
        if (trim($name) === '' || trim($path) === '' || trim($bucket) === '') {
            throw new InvalidArgumentException('Quota endpoint definitions require a name, path, and bucket.');
        }

        if ($cost < 1) {
            throw new InvalidArgumentException('Quota endpoint cost must be at least one.');
        }
    }
}
