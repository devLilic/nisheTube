<?php

namespace App\Domain\Retention\Enums;

enum CleanupTargetType: string
{
    case ResearchRun = 'research_run';
    case Export = 'export';
}
