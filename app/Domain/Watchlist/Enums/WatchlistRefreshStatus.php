<?php

namespace App\Domain\Watchlist\Enums;

enum WatchlistRefreshStatus: string
{
    case Queued = 'queued';
    case Processing = 'processing';
    case Completed = 'completed';
    case Partial = 'partial';
    case Failed = 'failed';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Partial, self::Failed], true);
    }
}
