<?php

namespace App\Domain\Collection\Enums;

enum CollectionCachePolicy: string
{
    case FreshOnly = 'fresh_only';
    case AllowFreshCache = 'allow_fresh_cache';
    case ForceRefresh = 'force_refresh';
}
