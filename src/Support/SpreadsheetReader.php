<?php

namespace Alimarchal\LaravelChartOfAccounts\Support;

use Alimarchal\LaravelChartOfAccounts\Exceptions\AccountingException;

/**
 * Reads the first sheet of a CSV or XLSX file into rows keyed by their (normalised) header. No dependency:
 * CSV via fgetcsv (comma, semicolon or tab, UTF-8 BOM stripped), XLSX via ZipArchive and the sheet XML.
 */
class SpreadsheetReader
{
    private const MAX_PART_BYTES = 50 * 1024 * 1024;

    /**
     * @return list<array<string, string>> rows keyed by header (lower case, spaces → underscores); blank rows are skipped
     */
    public static function read(string $path, string $extension, int $maxRows = 10000): array
    {
        $table = match (strtolower($extension)) {
            'csv', 'txt' => self::csv($path),
            'xlsx' => self::xlsx($path),
            default => throw new AccountingException('Upload a CSV or Excel (.xlsx) file.'),
        };

        $header = array_map(fn ($cell) => (string) str(trim((string) $cell))->lower()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_'), array_shift($table) ?? []);

        if (array_filter($header) === []) {
            throw new AccountingException('The file is empty: the first row must hold the column names.');
        }

        $rows = [];

        foreach ($table as $cells) {
            if (array_filter($cells, fn ($cell) => trim((string) $cell) !== '') === []) {
                continue;
            }

            if (count($rows) >= $maxRows) {
                throw new AccountingException("The file has more than {$maxRows} rows.");
            }

            $row = [];

            foreach ($header as $index => $key) {
                if ($key !== '') {
                    $row[$key] = trim((string) ($cells[$index] ?? ''));
                }
            }

            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private static function csv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;

        if (! mb_check_encoding($content, 'UTF-8')) {
            $content = mb_convert_encoding($content, 'UTF-8', 'Windows-1252');
        }

        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = collect([',', ';', "\t"])->sortByDesc(fn ($candidate) => substr_count($firstLine, $candidate))->first();

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);

        $rows = [];

        while (($cells = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($cell) => (string) $cell, $cells);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private static function xlsx(string $path): array
    {
        if (! class_exists(\ZipArchive::class)) {
            throw new AccountingException('Reading Excel files needs the PHP zip extension. Upload a CSV instead.');
        }

        $zip = new \ZipArchive;

        if ($zip->open($path) !== true) {
            throw new AccountingException('The file is not a valid Excel (.xlsx) workbook.');
        }

        try {
            $sheetPath = self::firstSheetPath($zip);
            self::assertSize($zip, $sheetPath);
            self::assertSize($zip, 'xl/sharedStrings.xml');
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw new AccountingException('The Excel workbook has no worksheet.');
            }

            $strings = self::sharedStrings($zip);
            $sheet = self::xml($sheetXml);
        } finally {
            $zip->close();
        }

        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];
            $next = 0;

            foreach ($row->c as $cell) {
                $index = isset($cell['r']) ? self::columnIndex((string) $cell['r']) : $next;
                $next = $index + 1;
                $type = (string) ($cell['t'] ?? '');

                $cells[$index] = match ($type) {
                    's' => $strings[(int) $cell->v] ?? '',
                    'inlineStr' => self::text($cell->is),
                    'b' => ((string) $cell->v) === '1' ? 'true' : 'false',
                    default => self::number((string) $cell->v),
                };
            }

            $line = [];

            for ($i = 0, $max = $cells === [] ? -1 : max(array_keys($cells)); $i <= $max; $i++) {
                $line[] = $cells[$i] ?? '';
            }

            $rows[] = $line;
        }

        return $rows;
    }

    private static function firstSheetPath(\ZipArchive $zip): string
    {
        $workbook = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbook !== false && $rels !== false) {
            $book = self::xml($workbook);
            $sheet = $book->sheets->sheet[0] ?? null;
            $id = $sheet ? (string) ($sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'] ?? '') : '';

            foreach (self::xml($rels)->Relationship as $relationship) {
                if ((string) $relationship['Id'] === $id) {
                    $target = ltrim((string) $relationship['Target'], '/');

                    return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                }
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    /**
     * @return list<string>
     */
    private static function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $strings = [];

        foreach (self::xml($xml)->si as $item) {
            $strings[] = self::text($item);
        }

        return $strings;
    }

    private static function text(?\SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }

        if (isset($node->t)) {
            return (string) $node->t;
        }

        $text = '';

        foreach ($node->r ?? [] as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /**
     * Excel stores 1101 as "1101" but 0.1 + 0.2 style values as "0.30000000000000004": print numbers plainly.
     */
    private static function number(string $value): string
    {
        if ($value !== '' && is_numeric($value) && str_contains(strtolower($value), 'e') === false && strlen($value) > 15) {
            return rtrim(rtrim(number_format((float) $value, 10, '.', ''), '0'), '.');
        }

        return $value;
    }

    private static function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?: 'A';
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = $index * 26 + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * A small .xlsx can inflate to gigabytes (zip bomb): refuse parts whose uncompressed size is above the limit.
     */
    private static function assertSize(\ZipArchive $zip, string $name): void
    {
        $stat = $zip->statName($name);

        if ($stat !== false && $stat['size'] > self::MAX_PART_BYTES) {
            throw new AccountingException('The Excel workbook is too large to import. Split it or upload a CSV.');
        }
    }

    private static function xml(string $content): \SimpleXMLElement
    {
        // Workbooks never carry a DOCTYPE; one means entity-expansion tricks.
        if (stripos($content, '<!DOCTYPE') !== false || stripos($content, '<!ENTITY') !== false) {
            throw new AccountingException('The Excel workbook could not be read.');
        }

        $previous = libxml_use_internal_errors(true);

        try {
            // No external entities: LIBXML_NONET, and no LIBXML_NOENT so entities are never expanded.
            $xml = simplexml_load_string($content, \SimpleXMLElement::class, LIBXML_NONET);
        } finally {
            libxml_use_internal_errors($previous);
        }

        if ($xml === false) {
            throw new AccountingException('The Excel workbook could not be read.');
        }

        return $xml;
    }
}
