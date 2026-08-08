<?php

namespace App\Domain\Exports\Data;

final readonly class ExportDataset
{
    /**
     * @param  list<string>  $headers
     * @param  list<list<bool|float|int|string|null>>  $rows
     */
    public function __construct(
        public array $headers,
        public array $rows,
        public string $sheetName = 'Research export',
    ) {}
}
