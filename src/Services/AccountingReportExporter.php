<?php

namespace Alimarchal\LaravelChartOfAccounts\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AccountingReportExporter
{
    /**
     * @param  iterable<int, array<string, mixed>|object>  $rows
     */
    public function download(iterable $rows, string $filename, string $format): Response|StreamedResponse
    {
        $rows = collect($rows)->map(fn ($row): array => (array) $row)->values();

        return match ($format) {
            'csv' => $this->csv($rows, "{$filename}.csv"),
            'xlsx' => response($this->xlsx($rows), 200, [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => "attachment; filename=\"{$filename}.xlsx\"",
            ]),
            'pdf' => response($this->pdf($rows, $filename), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => "attachment; filename=\"{$filename}.pdf\"",
            ]),
            default => abort(404),
        };
    }

    private function csv(Collection $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'w');
            $headers = array_keys($rows->first() ?? []);
            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, array_map(fn ($value): string => (string) $value, array_values($row)));
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function xlsx(Collection $rows): string
    {
        if (! class_exists(\ZipArchive::class)) {
            return $this->tabSeparated($rows);
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'accounting-xlsx-');
        $zip = new \ZipArchive;
        $zip->open($tempFile, \ZipArchive::OVERWRITE);

        $headers = array_keys($rows->first() ?? []);
        $sheetRows = collect([$headers])->merge($rows->map(fn (array $row): array => array_values($row)));
        $sheetXml = '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>';

        foreach ($sheetRows as $index => $row) {
            $rowNumber = $index + 1;
            $sheetXml .= "<row r=\"{$rowNumber}\">";

            foreach (array_values($row) as $column => $value) {
                $cell = chr(65 + $column).$rowNumber;
                $escaped = htmlspecialchars((string) $value, ENT_QUOTES | ENT_XML1);
                $sheetXml .= "<c r=\"{$cell}\" t=\"inlineStr\"><is><t>{$escaped}</t></is></c>";
            }

            $sheetXml .= '</row>';
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

    private function tabSeparated(Collection $rows): string
    {
        $headers = array_keys($rows->first() ?? []);
        $lines = [implode("\t", $headers)];

        foreach ($rows as $row) {
            $lines[] = implode("\t", array_map(fn ($value): string => (string) $value, array_values($row)));
        }

        return implode("\n", $lines);
    }

    private function pdf(Collection $rows, string $title): string
    {
        $text = $title."\n\n".$this->tabSeparated($rows);
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], mb_substr($text, 0, 12000));
        $stream = "BT /F1 8 Tf 40 780 Td 10 TL ({$escaped}) Tj ET";
        $objects = [
            '1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj',
            '2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj',
            '3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >> endobj',
            '4 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Courier >> endobj',
            '5 0 obj << /Length '.strlen($stream)." >> stream\n{$stream}\nendstream endobj",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $object) {
            $offsets[] = strlen($pdf);
            $pdf .= $object."\n";
        }

        $xref = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
