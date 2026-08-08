<?php

namespace App\Domain\YouTube\Contracts;

use App\Domain\YouTube\Data\QuotaAttempt;
use App\Domain\YouTube\Data\QuotaSummary;
use App\Domain\YouTube\Enums\QuotaUsageOutcome;
use App\Domain\YouTube\Enums\YouTubeErrorCode;
use Carbon\CarbonImmutable;

interface QuotaLedger
{
    public function begin(QuotaAttempt $attempt): int;

    public function complete(
        int $eventId,
        QuotaUsageOutcome $outcome,
        ?YouTubeErrorCode $errorCode = null,
    ): void;

    public function summary(?CarbonImmutable $at = null): QuotaSummary;
}
