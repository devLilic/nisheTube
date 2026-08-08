<?php

namespace App\Domain\Exports\Services;

use App\Domain\Exports\Contracts\ExportWriter;
use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Writers\CsvExportWriter;
use App\Domain\Exports\Writers\XlsxExportWriter;

final readonly class ExportWriterManager
{
    public function __construct(
        private CsvExportWriter $csv,
        private XlsxExportWriter $xlsx,
    ) {}

    public function for(ExportFormat $format): ExportWriter
    {
        return match ($format) {
            ExportFormat::Csv => $this->csv,
            ExportFormat::Xlsx => $this->xlsx,
        };
    }
}
