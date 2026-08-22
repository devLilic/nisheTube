<?php

namespace App\Domain\Localization\Enums;

enum UiLocale: string
{
    case Romanian = 'ro';
    case English = 'en';

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
