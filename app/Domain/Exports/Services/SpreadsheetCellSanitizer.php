<?php

namespace App\Domain\Exports\Services;

final class SpreadsheetCellSanitizer
{
    public function forCsv(bool|float|int|string|null $value): bool|float|int|string|null
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1
            ? "'{$value}"
            : $value;
    }

    public function forXml(bool|float|int|string|null $value): bool|float|int|string|null
    {
        if (! is_string($value)) {
            return $value;
        }

        return preg_replace('/[^\x{0009}\x{000A}\x{000D}\x{0020}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $value) ?? '';
    }
}
