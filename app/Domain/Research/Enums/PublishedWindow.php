<?php

namespace App\Domain\Research\Enums;

enum PublishedWindow: string
{
    case Any = 'any';
    case PastWeek = 'past_week';
    case PastMonth = 'past_month';
    case PastThreeMonths = 'past_three_months';
    case PastYear = 'past_year';
    case Custom = 'custom';

    public function lookbackDays(): ?int
    {
        return match ($this) {
            self::Any, self::Custom => null,
            self::PastWeek => 7,
            self::PastMonth => 30,
            self::PastThreeMonths => 90,
            self::PastYear => 365,
        };
    }
}
