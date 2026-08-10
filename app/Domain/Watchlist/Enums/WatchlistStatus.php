<?php

namespace App\Domain\Watchlist\Enums;

enum WatchlistStatus: string
{
    case Monitoring = 'monitoring';
    case Attention = 'attention';
    case Promising = 'promising';
    case RuledOut = 'ruled_out';
}
