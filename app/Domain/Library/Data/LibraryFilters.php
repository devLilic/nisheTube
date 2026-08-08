<?php

namespace App\Domain\Library\Data;

use App\Domain\Library\Enums\LibraryTargetType;

final readonly class LibraryFilters
{
    public function __construct(
        public ?LibraryTargetType $targetType = null,
        public ?int $projectId = null,
        public ?int $tagId = null,
        public ?string $search = null,
        public ?bool $archived = null,
    ) {}
}
