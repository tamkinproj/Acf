<?php

namespace App\Services;

use App\Models\Child;
use App\Support\SheetReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Brings in the orphans from the sponsor organisation's report sheets ("كشف الأيتام المطلوب تقارير لهم"), cash or bank.
 * The columns are found by their headings, in any order. Sheets can be imported again: an orphan already known by the
 * sheet's "رقم اليتيم" is updated, never added twice.
 */
final class OrphanSheetImport
{
    private const COLUMNS = [
        'external_no' => ['رقم اليتيم', 'orphan no', 'orphan number', 'orphan id'],
        'name' => ['اسم اليتيم', 'orphan name'],
        'sponsor_file_no' => ['رقم الكافل', 'رقم الكفيل', 'sponsor no', 'sponsor number', 'sponsor id'],
        'sponsor_name' => ['اسم الكافل', 'اسم الكفيل', 'sponsor name'],
        'report_status' => ['حاله التقرير', 'report status'],
        'delay_reason' => ['سبب التاخر', 'سبب التاخير', 'delay reason'],
        'expected_send_date' => ['التاريخ المتوقع', 'expected date', 'expected send date'],
    ];

    /**
     * @param  string  $default  'auto' (read cash/bank from each sheet's title), 'cash' or 'bank' (used when a sheet's title does not say)
     * @return array{dry_run: bool, new: int, updated: int, skipped: int, sheets: list<array>}
     */
    public static function run(string $path, string $originalName, string $default, int $foundationId, bool $dryRun): array
    {
        $sheets = SheetReader::read($path, $originalName);
        $report = ['dry_run' => $dryRun, 'new' => 0, 'updated' => 0, 'skipped' => 0, 'sheets' => []];
        $seen = [];

        DB::transaction(function () use ($sheets, $default, $foundationId, $dryRun, &$report, &$seen) {
            foreach ($sheets as $sheet) {
                $info = self::importSheet($sheet, $default, $foundationId, $dryRun, $seen);
                if ($info === null) {
                    continue;
                }
                foreach (['new', 'updated', 'skipped'] as $k) {
                    $report[$k] += $info[$k];
                }
                $report['sheets'][] = $info;
            }
        });

        return $report;
    }

    private static function importSheet(array $sheet, string $default, int $foundationId, bool $dryRun, array &$seen): ?array
    {
        $rows = $sheet['rows'];
        $headerAt = null;
        foreach (array_slice($rows, 0, 20, true) as $i => $r) {
            foreach ($r as $cell) {
                $n = self::norm($cell);
                if (str_contains($n, 'اسم اليتيم') || str_contains($n, 'orphan name')) {
                    $headerAt = $i;
                    break 2;
                }
            }
        }
        if ($headerAt === null) {
            return count(array_filter($rows, fn ($r) => array_filter($r))) ? ['sheet' => $sheet['name'], 'title' => '', 'pay_type' => null, 'new' => 0, 'updated' => 0, 'skipped' => 0, 'expected' => null, 'errors' => ['No heading row with "اسم اليتيم" (orphan name) was found in this sheet.'], 'sample' => []] : null;
        }

        $title = trim(implode(' ', array_filter(array_map(fn ($r) => trim(implode(' ', array_filter($r))), array_slice($rows, 0, $headerAt)))));
        $nt = self::norm($title);
        $pay = str_contains($nt, 'نقدي') || str_contains($nt, 'نقد ') || preg_match('/\bcash\b/', $nt) ? 'cash'
            : (str_contains($nt, 'بنكي') || str_contains($nt, 'بنك') || preg_match('/\bbank\b/', $nt) ? 'bank' : null);
        $pay ??= in_array($default, ['cash', 'bank'], true) ? $default : null;
        $expected = preg_match('/\((\d+)\s*يتيم/u', self::digits($title), $m) ? (int) $m[1] : null;

        $info = ['sheet' => $sheet['name'], 'title' => mb_substr($title, 0, 200), 'pay_type' => $pay, 'new' => 0, 'updated' => 0, 'skipped' => 0, 'expected' => $expected, 'errors' => [], 'sample' => []];
        if ($pay === null) {
            $info['errors'][] = 'Cannot tell if this sheet is cash (نقدي) or bank (بنكي). Choose one and try again.';

            return $info;
        }

        // Which column holds what.
        $map = [];
        foreach ($rows[$headerAt] as $col => $cell) {
            $n = self::norm($cell);
            foreach (self::COLUMNS as $key => $words) {
                foreach ($words as $w) {
                    if ($n !== '' && ! isset($map[$key]) && str_contains($n, self::norm($w))) {
                        $map[$key] = $col;
                        break 2;
                    }
                }
            }
        }
        if (! isset($map['name'])) {
            $info['errors'][] = 'The orphan name column was not found.';

            return $info;
        }
        $label = mb_substr(trim(preg_replace('/\s*-?\s*عدد الأيتام.*$/u', '', preg_replace('/^كشف[^-]*-\s*[^-]*-\s*/u', '', $title))), 0, 150);

        foreach (array_slice($rows, $headerAt + 1, null, true) as $i => $r) {
            $get = fn (string $k) => isset($map[$k]) ? trim((string) ($r[$map[$k]] ?? '')) : '';
            $name = $get('name');
            if ($name === '' || is_numeric($name) || mb_strlen($name) < 2) {
                if (array_filter($r)) {
                    $info['skipped']++;
                }

                continue;
            }
            $ext = self::digits($get('external_no'));
            $ext = $ext === '' ? null : mb_substr(preg_replace('/\.0+$/', '', $ext), 0, 40);
            $key = $ext ?? 'n:'.self::norm($name).'|'.self::digits($get('sponsor_file_no'));
            if (isset($seen[$key])) {
                $info['skipped']++;
                $info['errors'][] = 'Row '.($i + 1).': '.$name.' appears twice in the file - the second one was skipped.';

                continue;
            }
            $seen[$key] = true;

            $data = array_filter([
                'sponsor_file_no' => mb_substr(preg_replace('/\.0+$/', '', self::digits($get('sponsor_file_no'))), 0, 60),
                'sponsor_name' => mb_substr($get('sponsor_name'), 0, 150),
                'delay_reason' => mb_substr($get('delay_reason'), 0, 500),
                'expected_send_date' => self::date($get('expected_send_date')),
                'organization_comment' => $get('report_status') !== '' ? mb_substr($get('report_status'), 0, 1500) : null,
                'source_label' => $label !== '' ? $label : null,
            ], fn ($v) => $v !== null && $v !== '');

            $existing = $ext !== null
                ? Child::withoutGlobalScopes()->where('foundation_id', $foundationId)->where('external_no', $ext)->first()
                : Child::withoutGlobalScopes()->where('foundation_id', $foundationId)->where('name_ar', $name)->when(isset($data['sponsor_file_no']), fn ($q) => $q->where('sponsor_file_no', $data['sponsor_file_no']))->first();

            if ($existing) {
                $info['updated']++;
                if (! $dryRun) {
                    $existing->forceFill($data)->save();
                    $existing->sponsorship()->first()?->update(['pay_type' => $pay]);
                }
            } else {
                $info['new']++;
                if (! $dryRun) {
                    $child = ChildRecords::create(['name_en' => mb_substr(\App\Support\ArabicNames::toEnglish($name), 0, 150), 'name_ar' => mb_substr($name, 0, 150), 'status' => 'active', 'pay_type' => $pay] + $data, $foundationId);
                    $child->forceFill(['external_no' => $ext])->save();
                    $child->sponsorship()->first()?->update(['status' => 'active']);
                }
            }
            if (count($info['sample']) < 5) {
                $info['sample'][] = ['no' => $ext, 'name' => $name, 'sponsor' => $data['sponsor_name'] ?? '', 'is_new' => ! $existing];
            }
        }
        $info['errors'] = array_slice($info['errors'], 0, 10);

        return $info;
    }

    /** Arabic text compared loosely: no vowel marks, one kind of alef, ya and ta marbuta, single spaces, lower case. */
    public static function norm(string $s): string
    {
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0640}\x{200C}\x{200D}\x{200E}\x{200F}]/u', '', $s) ?? $s;
        $s = strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);

        return trim(mb_strtolower(preg_replace('/\s+/u', ' ', $s) ?? $s));
    }

    /** Arabic-Indic digits to 0-9. */
    public static function digits(string $s): string
    {
        return trim(strtr($s, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']));
    }

    /** An Excel date (a serial number or text such as 2026-12-31 or 31/12/2026) as Y-m-d, or null. */
    private static function date(string $v): ?string
    {
        $v = self::digits($v);
        if ($v === '') {
            return null;
        }
        try {
            if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
                return Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString();
            }
            if (preg_match('#^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})$#', $v, $m)) {
                return Carbon::createFromDate((int) $m[3], (int) $m[2], (int) $m[1])->toDateString();
            }
            if (preg_match('#^\d{4}-\d{1,2}-\d{1,2}#', $v)) {
                return Carbon::parse(substr($v, 0, 10))->toDateString();
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
