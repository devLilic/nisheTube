<?php

namespace App\Domain\YouTube\Services;

use DateTimeImmutable;
use Exception;

class YouTubeResourceValueNormalizer
{
    public function optionalString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    public function nonNegativeInteger(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (! is_string($value) || ! ctype_digit($value)) {
            return null;
        }

        $integer = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 0],
        ]);

        return is_int($integer) ? $integer : null;
    }

    public function dateTime(mixed $value): ?DateTimeImmutable
    {
        if (! is_string($value)) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    public function durationSeconds(mixed $value): ?int
    {
        if (! is_string($value)) {
            return null;
        }

        $matched = preg_match(
            '/^P(?:(\d+)D)?(?:T(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?)?$/',
            $value,
            $parts,
        );

        if ($matched !== 1 || count(array_filter(array_slice($parts, 1), fn (string $part): bool => $part !== '')) === 0) {
            return null;
        }

        return ((int) ($parts[1] ?? 0) * 86400)
            + ((int) ($parts[2] ?? 0) * 3600)
            + ((int) ($parts[3] ?? 0) * 60)
            + (int) ($parts[4] ?? 0);
    }

    public function thumbnailUrl(mixed $thumbnails): ?string
    {
        if (! is_array($thumbnails)) {
            return null;
        }

        foreach (['maxres', 'standard', 'high', 'medium', 'default'] as $size) {
            $url = $thumbnails[$size]['url'] ?? null;
            $url = $this->optionalString($url);

            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }
}
