<?php

namespace App\Domain\Semantic\Data;

final readonly class EditorialTitlePattern
{
    public function __construct(
        public string $key,
        public string $label,
    ) {}
}
