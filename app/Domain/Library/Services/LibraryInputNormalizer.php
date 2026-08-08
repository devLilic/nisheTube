<?php

namespace App\Domain\Library\Services;

use DomainException;
use Illuminate\Support\Str;

class LibraryInputNormalizer
{
    public function name(string $value, int $maximum = 160): string
    {
        $value = Str::squish($value);

        if ($value === '' || mb_strlen($value) > $maximum) {
            throw new DomainException("The name must contain between 1 and {$maximum} characters.");
        }

        return $value;
    }

    public function optionalText(?string $value, int $maximum): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if (mb_strlen($value) > $maximum) {
            throw new DomainException("The text may not exceed {$maximum} characters.");
        }

        return $value;
    }

    public function color(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = strtoupper(trim($value));

        if (preg_match('/^#[0-9A-F]{6}$/', $value) !== 1) {
            throw new DomainException('Colors must use six-digit hexadecimal notation.');
        }

        return $value;
    }
}
