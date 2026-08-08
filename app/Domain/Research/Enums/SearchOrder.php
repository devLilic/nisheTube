<?php

namespace App\Domain\Research\Enums;

enum SearchOrder: string
{
    case Relevance = 'relevance';
    case Date = 'date';
    case Rating = 'rating';
    case Title = 'title';
    case ViewCount = 'viewCount';
}
