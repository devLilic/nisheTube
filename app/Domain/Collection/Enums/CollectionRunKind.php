<?php

namespace App\Domain\Collection\Enums;

enum CollectionRunKind: string
{
    case SearchEnrichment = 'search_enrichment';
    case VideoAnalysis = 'video_analysis';
    case ChannelAnalysis = 'channel_analysis';
    case WatchlistRefresh = 'watchlist_refresh';
}
