<?php

namespace App\Domain\Settings\Enums;

enum MarketKey: string
{
    case GlobalEnglish = 'global_en';
    case RomaniaRomanian = 'ro_ro';
    case RussiaRussian = 'ru_ru';

    public function label(): string
    {
        return match ($this) {
            self::GlobalEnglish => 'Global / English',
            self::RomaniaRomanian => 'Romania / Romanian',
            self::RussiaRussian => 'Russia / Russian',
        };
    }

    public function regionCode(): ?string
    {
        return match ($this) {
            self::GlobalEnglish => null,
            self::RomaniaRomanian => 'RO',
            self::RussiaRussian => 'RU',
        };
    }

    public function relevanceLanguage(): string
    {
        return match ($this) {
            self::GlobalEnglish => 'en',
            self::RomaniaRomanian => 'ro',
            self::RussiaRussian => 'ru',
        };
    }

    public function sortOrder(): int
    {
        return match ($this) {
            self::GlobalEnglish => 10,
            self::RomaniaRomanian => 20,
            self::RussiaRussian => 30,
        };
    }
}
