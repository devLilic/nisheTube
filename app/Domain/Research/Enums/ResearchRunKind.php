<?php

namespace App\Domain\Research\Enums;

enum ResearchRunKind: string
{
    case Search = 'search';
    case DiscoveryValidation = 'discovery_validation';
}
