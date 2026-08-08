<?php

namespace App\Domain\Exports\Contracts;

use App\Domain\Exports\Data\ExportDataset;
use App\Domain\Exports\Enums\ExportFormat;

interface ExportWriter
{
    public function format(): ExportFormat;

    public function write(ExportDataset $dataset, string $path): void;
}
