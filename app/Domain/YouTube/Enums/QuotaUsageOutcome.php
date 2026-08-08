<?php

namespace App\Domain\YouTube\Enums;

enum QuotaUsageOutcome: string
{
    case Attempted = 'attempted';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
