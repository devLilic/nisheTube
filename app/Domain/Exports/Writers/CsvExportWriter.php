<?php

namespace App\Domain\Exports\Writers;

use App\Domain\Exports\Contracts\ExportWriter;
use App\Domain\Exports\Data\ExportDataset;
use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Services\SpreadsheetCellSanitizer;
use RuntimeException;

final readonly class CsvExportWriter implements ExportWriter
{
    public function __construct(private SpreadsheetCellSanitizer $sanitizer) {}

    public function format(): ExportFormat
    {
        return ExportFormat::Csv;
    }

    public function write(ExportDataset $dataset, string $path): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('The CSV export could not be opened for writing.');
        }

        try {
            fwrite($handle, "\xEF\xBB\xBF");
            $this->writeRow($handle, $dataset->headers);

            foreach ($dataset->rows as $row) {
                $this->writeRow($handle, array_map($this->sanitizer->forCsv(...), $row));
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  list<bool|float|int|string|null>  $row
     */
    private function writeRow($handle, array $row): void
    {
        if (fputcsv($handle, $row, ',', '"', '', "\r\n") === false) {
            throw new RuntimeException('The CSV export could not be written.');
        }
    }
}
