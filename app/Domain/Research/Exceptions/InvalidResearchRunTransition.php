<?php

namespace App\Domain\Research\Exceptions;

use App\Domain\Research\Enums\ResearchRunStatus;
use DomainException;

class InvalidResearchRunTransition extends DomainException
{
    public static function between(ResearchRunStatus $current, ResearchRunStatus $next): self
    {
        return new self("A research run cannot transition from [{$current->value}] to [{$next->value}].");
    }
}
