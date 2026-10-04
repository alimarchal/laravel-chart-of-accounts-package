<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Illuminate\Database\Query\Builder;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingReportExporter
{
    private const PDF_LINES_PER_PAGE = 58;

    private const PDF_CHARS_PER_LINE = 190;

    /**
     * CSV is streamed row by row from a database cursor, so it works at any size. XLSX and PDF are
     * built in memory and refused above accounting.export_max_rows.
     *
     * @param  Builder|iterable<int, array<string, mixed>|object>  $rows
     */
    public function download(Builder|iterable $rows, string $filename, string $format): Response|StreamedResponse
    {
        if ($format === 'csv') {
            return $this->csv($rows, "{$filename}.csv");
        }

        abort_unless(in_array($format, ['xlsx', 'pdf'], true), 404);

        $rows = $this->limited($rows, $format);

        return match ($format) {
            'xlsx' => response($this->xlsx($rows), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}.xlsx\"",
            ]),
            'pdf' => response($this->pdf($rows, $filename), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
            ]),
        };
    }

    /**
     * @param  Builder|iterable<int, array<string, mixed>|object>  $rows
     * @return Collection<int, array<string, mixed>>
     */
    private function limited(Builder|iterable $rows, string $format): Collection
    {
        $max = max(1, (int) config("accounting.export_max_rows.{$format}", $format === 'pdf' ? 2000 : 50000));
        $rows = $rows instanceof Builder ? $rows->limit($max + 1)->get() : collect($rows);

        abort_if($rows->count() > $max, 422, "This report has more than {$max} rows, the limit for ".strtoupper($format).' exports. Narrow the filters or export as CSV.');

        /** @var Collection<int, array<string, mixed>> $arrays */
        $arrays = $rows->map(fn ($row): array => (array) $row)->values();

        return $arrays;
    }

    /**
     * @param  Builder|iterable<int, array<string, mixed>|object>  $rows
     */
    private function csv(Builder|iterable $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            $headerWritten = false;

            foreach ($rows instanceof Builder ? $rows->cursor() : $rows as $row) {
                $row = (array) $row;

                if (! $headerWritten) {
                    fputcsv($handle, array_keys($row));
                    $headerWritten = true;
                }

                fputcsv($handle, array_map(fn ($value): string => (string) $value, array_values($row)));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function xlsx(Collection $rows): string
    {
        if (! class_exists(\ZipArchive::class)) {
            return $this->tabSeparated($rows);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'accounting-xlsx-');
        $zip = new \ZipArchive;
        $zip->open($tempFile, \ZipArchive::OVERWRITE);

        $headers = array_keys($rows->first() ?? []);
        $sheetXml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';
        $sheetXml .= $this->xlsxRow(1, $headers, false);

        foreach ($rows as $index => $row) {
            $sheetXml .= $this->xlsxRow($index + 2, array_values($row), true);
        }

        $sheetXml .= '</sheetData></worksheet>';

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Report" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
        $zip->close();

        $content = file_get_contents($tempFile);
        @unlink($tempFile);

        return $content === false ? $this->tabSeparated($rows) : $content;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function xlsxRow(int $rowNumber, array $values, bool $detectNumbers): string
    {
        $xml = "<row r=\"{$rowNumber}\">";

        foreach ($values as $column => $value) {
            $cell = $this->columnLetter($column).$rowNumber;
            $value = (string) $value;

            // Amounts become real numbers; codes such as "0012" keep their leading zeros as text.
            if ($detectNumbers && preg_match('/^-?(0|[1-9]\d{0,14})(\.\d+)?$/', $value)) {
                $xml .= "<c r=\"{$cell}\"><v>{$value}</v></c>";
            } else {
                $escaped = htmlspecialchars($value, ENT_QUOTES | ENT_XML1);
                $xml .= "<c r=\"{$cell}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }
        }

        return $xml.'</row>';
    }

    /**
     * 0 => A, 25 => Z, 26 => AA, ...
     */
    private function columnLetter(int $index): string
    {
        $letters = '';

        for ($index++; $index > 0; $index = intdiv($index - 1, 26)) {
            $letters = chr(65 + ($index - 1) % 26).$letters;
        }

        return $letters;
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function tabSeparated(Collection $rows): string
    {
        $headers = array_keys($rows->first() ?? []);
        $lines = [implode("\t", $headers)];

        foreach ($rows as $row) {
            $lines[] = implode("\t", array_map(fn ($value): string => (string) $value, array_values($row)));
        }

        return implode("\n", $lines);
    }

    /**
     * A dependency-free PDF: landscape A4, monospaced columns, header repeated on every page.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     */
    private function pdf(Collection $rows, string $title): string
    {
        $headers = array_keys($rows->first() ?? []);
        $widths = [];

        foreach ($headers as $i => $header) {
            $widths[$i] = min(28, max(mb_strlen($header), ...$rows->map(fn (array $row): int => mb_strlen((string) array_values($row)[$i]))->all() ?: [0]));
        }

        $format = function (array $values) use ($widths): string {
            $cells = [];

            foreach (array_values($values) as $i => $value) {
                $value = (string) $value;
                $value = mb_strlen($value) > $widths[$i] ? mb_substr($value, 0, $widths[$i] - 1).'~' : $value;
                $cells[] = is_numeric($value) ? str_pad($value, $widths[$i], ' ', STR_PAD_LEFT) : str_pad($value, $widths[$i]);
            }

            return mb_substr(implode('  ', $cells), 0, self::PDF_CHARS_PER_LINE);
        };

        $headerLine = $format($headers);
        $bodyLines = $rows->map(fn (array $row): string => $format($row))->all() ?: ['(no rows)'];
        $pages = array_chunk($bodyLines, self::PDF_LINES_PER_PAGE - 4);
        $pageCount = count($pages);
        $generated = now()->format('Y-m-d H:i');

        $objects = [
            1 => '<< /Type /Catalog /Pages 2 0 R >>',
            3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier /Encoding /WinAnsiEncoding >>',
            4 => '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>',
        ];
        $kids = [];

        foreach ($pages as $number => $lines) {
            $stream = 'BT /F2 11 Tf 30 565 Td ('.$this->pdfText(str($title)->headline().'  -  page '.($number + 1)." of {$pageCount}  -  {$generated}").') Tj ET'."\n";
            $stream .= 'BT /F2 7 Tf 9 TL 30 545 Td ('.$this->pdfText($headerLine).') Tj T* ('.$this->pdfText(str_repeat('-', mb_strlen($headerLine))).') Tj /F1 7 Tf';

            foreach ($lines as $line) {
                $stream .= ' T* ('.$this->pdfText($line).') Tj';
            }

            $stream .= ' ET';
            $contentId = 5 + $number * 2;
            $pageId = $contentId + 1;
            $objects[$contentId] = '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream";
            $objects[$pageId] = "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 842 595] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> >> /Contents {$contentId} 0 R >>";
            $kids[] = "{$pageId} 0 R";
        }

        $objects[2] = '<< /Type /Pages /Kids ['.implode(' ', $kids).'] /Count '.$pageCount.' >>';
        ksort($objects);

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $id => $object) {
            $offsets[$id] = strlen($pdf);
            $pdf .= "{$id} 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function pdfText(string $text): string
    {
        $text = function_exists('iconv') ? (iconv('UTF-8', 'Windows-1252//TRANSLIT//IGNORE', $text) ?: '') : $text;

        return str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', ' ', ' '], $text);
    }
}
