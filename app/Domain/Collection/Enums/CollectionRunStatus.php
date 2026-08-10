<?php

namespace App\Domain\Collection\Enums;

enum CollectionRunStatus: string
{
    case Queued = 'queued';
    case Collecting = 'collecting';
    case Completed = 'completed';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Failed], true);
    }
}
