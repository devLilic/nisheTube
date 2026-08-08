<?php

namespace App\Domain\Research\Enums;

enum VideoDurationFilter: string
{
    case Any = 'any';
    case Short = 'short';
    case Medium = 'medium';
    case Long = 'long';
}
