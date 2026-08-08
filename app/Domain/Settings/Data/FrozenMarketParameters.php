<?php

namespace App\Domain\Settings\Data;

use InvalidArgumentException;

final readonly class FrozenMarketParameters
{
    public string $marketKey;

    public ?string $regionCode;

    public string $relevanceLanguage;

    public function __construct(
        string $marketKey,
        ?string $regionCode,
        string $relevanceLanguage,
    ) {
        $marketKey = strtolower(trim($marketKey));
        $regionCode = self::nullableTrim($regionCode);
        $relevanceLanguage = strtolower(trim($relevanceLanguage));

        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/', $marketKey) !== 1) {
            throw new InvalidArgumentException('The market key is invalid.');
        }

        if ($regionCode !== null && preg_match('/^[A-Za-z]{2}$/', $regionCode) !== 1) {
            throw new InvalidArgumentException('The market region code must be a two letter country code.');
        }

        if (preg_match('/^[a-z]{2,3}$/', $relevanceLanguage) !== 1) {
            throw new InvalidArgumentException('The market relevance language must be a two or three letter language code.');
        }

        $this->marketKey = $marketKey;
        $this->regionCode = $regionCode === null ? null : strtoupper($regionCode);
        $this->relevanceLanguage = $relevanceLanguage;
    }

    /**
     * @return array{relevanceLanguage: string, regionCode?: string}
     */
    public function youtubeRequestParameters(): array
    {
        $parameters = ['relevanceLanguage' => $this->relevanceLanguage];

        if ($this->regionCode !== null) {
            $parameters['regionCode'] = $this->regionCode;
        }

        return $parameters;
    }

    private static function nullableTrim(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
