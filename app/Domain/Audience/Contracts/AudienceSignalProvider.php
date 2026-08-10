<?php

namespace App\Domain\Audience\Contracts;

use App\Domain\Audience\Data\AudienceProfileResult;
use App\Domain\Audience\Data\AudienceSignalInput;

interface AudienceSignalProvider
{
    public function name(): string;

    public function version(): string;

    /** @param list<AudienceSignalInput> $comments */
    public function analyze(array $comments): AudienceProfileResult;
}
