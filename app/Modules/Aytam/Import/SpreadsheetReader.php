<?php

namespace App\Modules\Aytam\Import;

use InvalidArgumentException;
use OpenSpout\Reader\CSV\Options as CsvOptions;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;
use ZipArchive;

/**
 * Reads the first sheet of a CSV or Excel (.xlsx) file as rows of plain strings. Nothing is trusted: the file type is decided
 * from the bytes, the size, row and column counts are capped, and a spreadsheet that unpacks to something absurd is refused.
 */
class SpreadsheetReader
{
    public const MAX_BYTES = 8 * 1024 * 1024;
    public const MAX_UNPACKED = 80 * 1024 * 1024;
    public const MAX_COLUMNS = 60;

    /** @return 'csv'|'xlsx' */
    public function detect(string $path, string $clientName): string
    {
        if (filesize($path) > self::MAX_BYTES) {
            throw new InvalidArgumentException('The file is larger than '.(self::MAX_BYTES / 1024 / 1024).' MB.');
        }
        $head = (string) file_get_contents($path, false, null, 0, 4);
        $ext = strtolower(pathinfo($clientName, PATHINFO_EXTENSION));

        if (str_starts_with($head, "PK\x03\x04")) {
            if ($ext !== 'xlsx') {
                throw new InvalidArgumentException('Only .csv and .xlsx files can be imported. Save older Excel files (.xls) as .xlsx or .csv first.');
            }
            $this->assertSafeZip($path);

            return 'xlsx';
        }
        if (! in_array($ext, ['csv', 'txt'], true)) {
            throw new InvalidArgumentException('Only .csv and .xlsx files can be imported.');
        }
        $sample = (string) file_get_contents($path, false, null, 0, 65536);
        if (str_contains($sample, "\0") || $sample === '') {
            throw new InvalidArgumentException('This does not look like a CSV text file.');
        }

        return 'csv';
    }

    /**
     * @param  callable(int $number, list<string> $cells):void  $each  called for every non-empty data row (numbers start at 1 = first row after the header)
     * @return array{headers:list<string>,count:int}
     */
    public function read(string $path, string $type, int $maxRows, callable $each): array
    {
        $reader = $type === 'xlsx' ? new XlsxReader : new CsvReader($this->csvOptions($path));
        $headers = null;
        $count = 0;

        try {
            $reader->open($path);
            foreach ($reader->getSheetIterator() as $sheet) {
                foreach ($sheet->getRowIterator() as $row) {
                    $cells = $this->cells($row->toArray());
                    if ($headers === null) {
                        if ($this->blank($cells)) {
                            continue;   // leading empty lines
                        }
                        $headers = $this->headers($cells);
                        continue;
                    }
                    if ($this->blank($cells)) {
                        continue;
                    }
                    if (++$count > $maxRows) {
                        throw new InvalidArgumentException("The file has more than {$maxRows} rows. Split it into smaller files.");
                    }
                    $each($count, array_slice(array_pad($cells, count($headers), ''), 0, count($headers)));
                }
                break;   // first sheet only
            }
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new InvalidArgumentException('This file could not be read. Check that it is a valid '.strtoupper($type).' file.');
        } finally {
            try {
                $reader->close();
            } catch (\Throwable) {
            }
        }
        if ($headers === null) {
            throw new InvalidArgumentException('The file is empty.');
        }
        if ($count === 0) {
            throw new InvalidArgumentException('The file has a header row but no data rows.');
        }

        return ['headers' => $headers, 'count' => $count];
    }

    private function csvOptions(string $path): CsvOptions
    {
        $sample = (string) file_get_contents($path, false, null, 0, 8192);
        $counts = [',' => substr_count($sample, ','), ';' => substr_count($sample, ';'), "\t" => substr_count($sample, "\t")];
        arsort($counts);

        return new CsvOptions(
            FIELD_DELIMITER: (string) array_key_first($counts),
            // What Excel on Windows saves as "CSV" when the text is not UTF-8.
            ENCODING: mb_check_encoding($sample, 'UTF-8') ? 'UTF-8' : 'Windows-1252',
        );
    }

    /** @param array<int,mixed> $raw @return list<string> */
    private function cells(array $raw): array
    {
        return array_map(function ($v) {
            return match (true) {
                $v === null => '',
                $v instanceof \DateTimeInterface => $v->format('Y-m-d'),
                is_bool($v) => $v ? 'true' : 'false',
                is_float($v) => floor($v) == $v && abs($v) < 1e15 ? (string) (int) $v : rtrim(rtrim(number_format($v, 8, '.', ''), '0'), '.'),
                default => trim((string) $v),
            };
        }, array_values($raw));
    }

    private function blank(array $cells): bool
    {
        return count(array_filter($cells, fn ($c) => $c !== '')) === 0;
    }

    /** @return list<string> */
    private function headers(array $cells): array
    {
        while ($cells !== [] && end($cells) === '') {
            array_pop($cells);   // trailing empty header cells
        }
        if (count($cells) > self::MAX_COLUMNS) {
            throw new InvalidArgumentException('The file has more than '.self::MAX_COLUMNS.' columns.');
        }

        return array_map(fn ($h, $i) => $h !== '' ? mb_substr($h, 0, 120) : 'Column '.($i + 1), $cells, array_keys($cells));
    }

    private function assertSafeZip(string $path): void
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new InvalidArgumentException('This is not a valid Excel file.');
        }
        try {
            if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName('xl/workbook.xml') === false || $zip->numFiles > 500) {
                throw new InvalidArgumentException('This is not a valid Excel (.xlsx) workbook.');
            }
            $total = 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $total += (int) ($zip->statIndex($i)['size'] ?? 0);
            }
            if ($total > self::MAX_UNPACKED) {
                throw new InvalidArgumentException('This workbook is too large to import.');
            }
        } finally {
            $zip->close();
        }
    }
}
