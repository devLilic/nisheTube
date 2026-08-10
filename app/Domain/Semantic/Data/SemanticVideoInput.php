<?php

namespace App\Domain\Semantic\Data;

final readonly class SemanticVideoInput
{
    public function __construct(
        public string $providerVideoId,
        public string $title,
    ) {}
}
