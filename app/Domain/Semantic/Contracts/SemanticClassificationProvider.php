<?php

namespace App\Domain\Semantic\Contracts;

use App\Domain\Semantic\Data\SemanticProfileResult;
use App\Domain\Semantic\Data\SemanticVideoInput;

interface SemanticClassificationProvider
{
    /** @param list<SemanticVideoInput> $videos */
    public function classify(array $videos): SemanticProfileResult;

    public function name(): string;

    public function version(): string;
}
