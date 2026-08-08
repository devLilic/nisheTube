<?php

namespace App\Domain\Discovery\Enums;

enum DiscoveryRunStatus: string
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Analyzing = 'analyzing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
