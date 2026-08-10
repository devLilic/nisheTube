<?php

namespace App\Domain\Semantic\Data;

final readonly class SemanticLabel
{
    /** @param list<string> $evidenceVideoIds */
    public function __construct(
        public string $kind,
        public string $label,
        public string $key,
        public float $confidence,
        public array $evidenceVideoIds,
    ) {}
}
