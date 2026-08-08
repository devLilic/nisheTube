<?php

namespace App\Domain\Retention\Enums;

enum CleanupMode: string
{
    case Scheduled = 'scheduled';
    case ManualRetention = 'manual_retention';
    case ManualSelection = 'manual_selection';
}
