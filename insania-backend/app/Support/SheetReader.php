<?php

namespace App\Support;

use RuntimeException;
use ZipArchive;

/**
 * Reads a spreadsheet the office sends (.xlsx or .csv) into plain rows of text, with no extra library:
 * an .xlsx file is a zip of small XML files. Only the cell texts are read - never formulas, macros, links or images.
 */
final class SheetReader
{
    private const MAX_ROWS = 5000;
    private const MAX_XML_BYTES = 30 * 1024 * 1024;

    /** @return list<array{name: string, rows: list<list<string>>}> */
    public static function read(string $path, string $originalName = ''): array
    {
        $head = (string) file_get_contents($path, false, null, 0, 4);
        if (str_starts_with($head, "PK")) {
            return self::xlsx($path);
        }
        if (str_ends_with(strtolower($originalName), '.xls') && ! str_ends_with(strtolower($originalName), '.xlsx')) {
            throw new RuntimeException('This is an old .xls file. Open it in Excel and use Save As .xlsx, then upload that.');
        }

        return [['name' => 'Sheet', 'rows' => self::csv($path)]];
    }

    private static function csv(string $path): array
    {
        $raw = (string) file_get_contents($path);
        if (str_starts_with($raw, "\xEF\xBB\xBF")) {
            $raw = substr($raw, 3);
        }
        if (! mb_check_encoding($raw, 'UTF-8')) {
            $raw = mb_convert_encoding($raw, 'UTF-8', 'Windows-1256');
        }
        $first = strtok($raw, "\n") ?: '';
        $delim = substr_count($first, ';') > substr_count($first, ',') ? ';' : (substr_count($first, "\t") > substr_count($first, ',') ? "\t" : ',');
        $rows = [];
        $h = fopen('php://temp', 'r+');
        fwrite($h, $raw);
        rewind($h);
        while (($r = fgetcsv($h, 0, $delim)) !== false && count($rows) < self::MAX_ROWS) {
            $rows[] = array_map(fn ($v) => trim((string) $v), $r);
        }
        fclose($h);

        return $rows;
    }

    private static function xlsx(string $path): array
    {
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException('This file could not be opened. Save it again as .xlsx from Excel.');
        }
        try {
            $shared = [];
            if (($xml = self::entry($zip, 'xl/sharedStrings.xml')) !== null) {
                $doc = self::xml($xml);
                foreach ($doc->si as $si) {
                    $t = '';
                    if (isset($si->t)) {
                        $t = (string) $si->t;
                    }
                    foreach ($si->r as $run) {
                        $t .= (string) $run->t;
                    }
                    $shared[] = $t;
                }
            }

            $wb = self::xml(self::entry($zip, 'xl/workbook.xml') ?? throw new RuntimeException('This is not an Excel workbook.'));
            $rels = [];
            if (($rx = self::entry($zip, 'xl/_rels/workbook.xml.rels')) !== null) {
                foreach (self::xml($rx)->Relationship as $rel) {
                    $rels[(string) $rel['Id']] = ltrim((string) $rel['Target'], '/');
                }
            }
            $out = [];
            $wb->registerXPathNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
            foreach ($wb->sheets->sheet ?? [] as $i => $sheet) {
                $rid = (string) $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                $target = $rels[$rid] ?? 'worksheets/sheet'.($i + 1).'.xml';
                $target = str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
                $sx = self::entry($zip, $target);
                if ($sx === null) {
                    continue;
                }
                $out[] = ['name' => (string) $sheet['name'], 'rows' => self::sheetRows(self::xml($sx), $shared)];
            }

            return $out;
        } finally {
            $zip->close();
        }
    }

    private static function sheetRows(\SimpleXMLElement $doc, array $shared): array
    {
        $rows = [];
        foreach ($doc->sheetData->row ?? [] as $row) {
            if (count($rows) >= self::MAX_ROWS + 20) {
                break;
            }
            $cells = [];
            $next = 0;
            foreach ($row->c as $c) {
                $idx = isset($c['r']) ? self::colIndex((string) $c['r']) : $next;
                $next = $idx + 1;
                $type = (string) $c['t'];
                $v = isset($c->v) ? (string) $c->v : '';
                if ($type === 's') {
                    $v = $shared[(int) $v] ?? '';
                } elseif ($type === 'inlineStr') {
                    $v = isset($c->is->t) ? (string) $c->is->t : '';
                    foreach ($c->is->r ?? [] as $run) {
                        $v .= (string) $run->t;
                    }
                } elseif ($type === 'b') {
                    $v = $v === '1' ? 'TRUE' : 'FALSE';
                } elseif ($type === 'e') {
                    $v = '';
                }
                $cells[$idx] = trim($v);
            }
            $max = $cells ? max(array_keys($cells)) : -1;
            $line = [];
            for ($k = 0; $k <= $max; $k++) {
                $line[] = $cells[$k] ?? '';
            }
            $rows[] = $line;
        }

        return $rows;
    }

    private static function colIndex(string $ref): int
    {
        preg_match('/^[A-Z]+/i', $ref, $m);
        $n = 0;
        foreach (str_split(strtoupper($m[0] ?? 'A')) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }

        return $n - 1;
    }

    private static function entry(ZipArchive $zip, string $name): ?string
    {
        $stat = $zip->statName($name);
        if ($stat === false) {
            return null;
        }
        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new RuntimeException('This sheet is too large to import. Split it into smaller files.');
        }

        return $zip->getFromName($name) ?: null;
    }

    private static function xml(string $xml): \SimpleXMLElement
    {
        $doc = @simplexml_load_string($xml, \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT);
        if (! $doc) {
            throw new RuntimeException('This file is damaged. Save it again as .xlsx from Excel.');
        }

        return $doc;
    }
}
