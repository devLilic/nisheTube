<?php

namespace App\Domain\Discovery\Enums;

enum DiscoverySeedSource: string
{
    case User = 'user';
    case Expanded = 'expanded';
}
