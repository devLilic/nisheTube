<?php

namespace App\Domain\Retention\Enums;

enum CleanupStatus: string
{
    case Previewed = 'previewed';
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';
}
