<?php

namespace App\Domain\Exports\Writers;

use App\Domain\Exports\Contracts\ExportWriter;
use App\Domain\Exports\Data\ExportDataset;
use App\Domain\Exports\Enums\ExportFormat;
use App\Domain\Exports\Services\SpreadsheetCellSanitizer;
use Phar;
use PharData;
use RuntimeException;

final readonly class XlsxExportWriter implements ExportWriter
{
    public function __construct(private SpreadsheetCellSanitizer $sanitizer) {}

    public function format(): ExportFormat
    {
        return ExportFormat::Xlsx;
    }

    public function write(ExportDataset $dataset, string $path): void
    {
        if (is_file($path) && ! unlink($path)) {
            throw new RuntimeException('The previous XLSX work file could not be replaced.');
        }

        $worksheetPath = $path.'.worksheet.xml';
        $this->writeWorksheet($dataset, $worksheetPath);

        try {
            $archive = new PharData($path, 0, null, Phar::ZIP);
            $archive->addFromString('[Content_Types].xml', $this->contentTypes());
            $archive->addFromString('_rels/.rels', $this->rootRelationships());
            $archive->addFromString('docProps/app.xml', $this->appProperties());
            $archive->addFromString('docProps/core.xml', $this->coreProperties());
            $archive->addFromString('xl/workbook.xml', $this->workbook($dataset->sheetName));
            $archive->addFromString('xl/_rels/workbook.xml.rels', $this->workbookRelationships());
            $archive->addFromString('xl/styles.xml', $this->styles());
            $archive->addFile($worksheetPath, 'xl/worksheets/sheet1.xml');
            unset($archive);
        } finally {
            if (is_file($worksheetPath)) {
                unlink($worksheetPath);
            }
        }

        if (! is_file($path) || filesize($path) === 0) {
            throw new RuntimeException('The XLSX export could not be written.');
        }
    }

    private function writeWorksheet(ExportDataset $dataset, string $path): void
    {
        $handle = fopen($path, 'wb');

        if ($handle === false) {
            throw new RuntimeException('The XLSX worksheet could not be opened for writing.');
        }

        try {
            fwrite($handle, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>');
            fwrite($handle, '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>');
            $this->writeWorksheetRow($handle, 1, $dataset->headers, true);

            foreach ($dataset->rows as $index => $row) {
                $this->writeWorksheetRow($handle, $index + 2, $row, false);
            }

            fwrite($handle, '</sheetData></worksheet>');
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  resource  $handle
     * @param  list<bool|float|int|string|null>  $row
     */
    private function writeWorksheetRow($handle, int $rowNumber, array $row, bool $header): void
    {
        fwrite($handle, '<row r="'.$rowNumber.'">');

        foreach ($row as $index => $value) {
            $reference = $this->columnName($index + 1).$rowNumber;
            fwrite($handle, $this->cell($reference, $this->sanitizer->forXml($value), $header));
        }

        fwrite($handle, '</row>');
    }

    private function cell(string $reference, bool|float|int|string|null $value, bool $header): string
    {
        $style = $header ? ' s="1"' : '';

        if ($value === null) {
            return '<c r="'.$reference.'"'.$style.'/>';
        }

        if (is_int($value) || is_float($value)) {
            $number = is_float($value) ? str_replace(',', '.', sprintf('%.15g', $value)) : (string) $value;

            return '<c r="'.$reference.'"'.$style.'><v>'.$number.'</v></c>';
        }

        if (is_bool($value)) {
            return '<c r="'.$reference.'" t="b"'.$style.'><v>'.($value ? '1' : '0').'</v></c>';
        }

        $escaped = htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $space = trim($value) !== $value ? ' xml:space="preserve"' : '';

        return '<c r="'.$reference.'" t="inlineStr"'.$style.'><is><t'.$space.'>'.$escaped.'</t></is></c>';
    }

    private function columnName(int $column): string
    {
        $name = '';

        while ($column > 0) {
            $column--;
            $name = chr(65 + ($column % 26)).$name;
            $column = intdiv($column, 26);
        }

        return $name;
    }

    private function contentTypes(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>'
            .'<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            .'</Types>';
    }

    private function rootRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            .'<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/>'
            .'</Relationships>';
    }

    private function workbook(string $sheetName): string
    {
        $name = htmlspecialchars(mb_substr($sheetName, 0, 31), ENT_XML1 | ENT_QUOTES, 'UTF-8');

        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets><sheet name="'.$name.'" sheetId="1" r:id="rId1"/></sheets></workbook>';
    }

    private function workbookRelationships(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>';
    }

    private function styles(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="1"><fill><patternFill patternType="none"/></fill></fills>'
            .'<borders count="1"><border/></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/><xf numFmtId="0" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1"/></cellXfs>'
            .'</styleSheet>';
    }

    private function coreProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/">'
            .'<dc:creator>NisheTube</dc:creator><dc:title>Research export</dc:title></cp:coreProperties>';
    }

    private function appProperties(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties">'
            .'<Application>NisheTube</Application></Properties>';
    }
}
