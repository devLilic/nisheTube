<?php

namespace App\Domain\Discovery\Enums;

enum NicheCandidateStatus: string
{
    case New = 'new';
    case Saved = 'saved';
    case Dismissed = 'dismissed';
    case Validated = 'validated';
}
