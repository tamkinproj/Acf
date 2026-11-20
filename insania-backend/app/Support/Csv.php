<?php

namespace App\Support;

/** Builds CSV text that is safe to open in a spreadsheet (no cell can run as a formula). */
final class Csv
{
    public static function cell(mixed $value): string
    {
        $text = (string) $value;

        // Spreadsheets treat these leading characters as the start of a formula.
        if ($text !== '' && in_array($text[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            $text = "'".$text;
        }

        return '"'.str_replace('"', '""', $text).'"';
    }

    /** @param array<int, array<int, mixed>> $rows */
    public static function build(array $rows): string
    {
        $lines = array_map(fn ($row) => implode(',', array_map([self::class, 'cell'], $row)), $rows);

        return "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n";
    }
}
