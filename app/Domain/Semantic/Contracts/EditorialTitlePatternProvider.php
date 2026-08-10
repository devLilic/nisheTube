<?php

namespace App\Domain\Semantic\Contracts;

use App\Domain\Semantic\Data\EditorialTitlePattern;

interface EditorialTitlePatternProvider
{
    /** @return list<EditorialTitlePattern> */
    public function detect(string $title): array;

    public function version(): string;
}
