<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\Child;
use App\Models\Guardian;
use App\Support\SheetReader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * TEMPORARY (UPDATE-61) - remove when the old Google Form sheets have been brought in.
 *
 * Adds the details from the office's Google Form answer sheets ("Orphans Updates 2025-2026" and "Date of births")
 * to the orphans already in the system, matched ONLY by the orphan number ("Numero ng bata"). Names are never used
 * to match. Rules agreed with the foundation:
 *  - an existing orphan: only empty fields are filled; a different value already saved is listed as a conflict, not written
 *  - a number not in the system (six digits): a new orphan, pending, with no bank or cash chosen yet
 *  - anything unclear (a cell with several numbers, a birth date that does not fit the age, ...) is listed for review
 *  - a number repeated in a file (the form sent again): the latest answer wins
 */
final class OrphanSheetMerge
{
    /** Columns, found by their heading. Order matters: the first key whose words match a heading takes that column. */
    private const HEAD = [
        'ts' => ['timestamp', 'time stamp'],
        'relation' => ['kaano-ano', 'kaano ano', 'relation'],
        'number' => ['numero ng bata', 'numero', 'orphan no', 'orphan number', 'رقم اليتيم'],
        'dob' => ['date f birth', 'date of birth', 'birth date', 'kapanganakan', 'تاريخ الميلاد'],
        'name' => ['pangalan', 'orphan name', 'name of the child', 'اسم اليتيم'],
        'gender' => ['kasarian', 'gender'],
        'age' => ['edad', 'age'],
        'madrasa' => ['madrasa', 'grade ng bata sa arabic'],
        'quran' => ['surah', 'qur-an', "qur'an", 'quran'],
        'guardian' => ['guardian ng bata', 'guardian name'],
        'changes' => ['pagbabago', 'changes'],
        'health' => ['kalusugan', 'health'],
        'message' => ['masasabi', 'message'],
        'photos' => ['picture', 'larawan', 'photo'],
        'bank_holder' => ['account name'],
        'bank_number' => ['account number'],
    ];

    private const RELATION = [
        'Mother' => ['nanay', 'mother', 'ina', 'mama', 'inay', 'mommy', 'mom'],
        'Father' => ['tatay', 'father', 'ama', 'papa', 'itay'],
        'Grandmother' => ['lola', 'grandmother', 'grandma', 'grand mother', 'grand mathers', 'apoh'],
        'Grandfather' => ['lolo', 'grandfather', 'grandpa'],
        'Aunt' => ['tita', 'auntie', 'aunty', 'aunt', 'antie', 'ante', 'aunti'],
        'Uncle' => ['tito', 'uncle', 'bapa', 'ankel'],
        'Sister' => ['ate', 'sister'],
        'Brother' => ['kuya', 'brother'],
    ];

    /** Health answers that clearly say the child is not well. Anything else leaves the health status as it is. */
    private const UNWELL = ['hindi mabuti', 'hindi mabutu', 'under observation', 'chickenpox', 'nagpapagaling', 'karamdaman'];

    /** Fields written on the orphan record (the guardian and bank account are handled on their own). */
    private const CHILD_FIELDS = ['gender', 'birth_date', 'madrasa_grade', 'quran_memorization', 'notable_changes', 'health_details', 'message_to_sponsor', 'photo_links'];

    /**
     * @param  list<array{path: string, name: string}>  $files
     */
    public static function run(array $files, int $foundationId, ?int $partnerId, bool $apply): array
    {
        $out = ['applied' => false, 'files' => [], 'numbers' => 0, 'matched' => 0, 'updated' => 0, 'created' => 0, 'fields' => [],
            'lists' => ['unclear_numbers' => [], 'birth_dates' => [], 'conflicts' => [], 'names_to_check' => [], 'other_partner' => [], 'no_details' => []], 'new_orphans' => []];
        $rows = [];
        foreach ($files as $f) {
            $rows = array_merge($rows, self::readFile($f['path'], $f['name'], $out));
        }

        // one entry per orphan number: the latest answer of each file wins, field by field
        $byNum = [];
        $existing = Child::withoutGlobalScopes()->where('foundation_id', $foundationId)->whereNotNull('external_no')->where('external_no', '!=', '')->pluck('external_no')->map(fn ($v) => (string) $v)->flip();
        foreach ($rows as $r) {
            $num = self::number($r['raw_number']);
            if ($num === null && preg_match_all('/\d{3,}/', self::digits($r['raw_number']), $m) === 1 && isset($existing[$m[0][0]])) {
                $num = $m[0][0];   // not six digits, but exactly an orphan already in the system
            }
            if ($num === null) {
                $out['lists']['unclear_numbers'][] = ['file' => $r['file'], 'row' => $r['row'], 'number' => $r['raw_number'], 'name' => $r['vals']['name'] ?? ''];

                continue;
            }
            $byNum[$num][] = $r;
        }
        $out['numbers'] = count($byNum);
        $merged = [];
        foreach ($byNum as $num => $list) {
            $merged[$num] = self::combine((string) $num, $list, $out);
        }

        $children = Child::withoutGlobalScopes()->with(['guardian', 'bankAccount', 'sponsorship'])->where('foundation_id', $foundationId)->whereIn('external_no', array_map('strval', array_keys($merged)))->get()->groupBy('external_no');
        $work = function () use (&$out, $merged, $children, $foundationId, $partnerId, $apply) {
            foreach ($merged as $num => $v) {
                $num = (string) $num;
                $found = $children->get($num, collect());
                $mine = $found->first(fn ($c) => $partnerId === null || (int) $c->partner_id === $partnerId);
                if ($mine) {
                    $out['matched']++;
                    if (self::fillExisting($mine, $v, $out, $apply)) {
                        $out['updated']++;
                    }
                } elseif ($found->isNotEmpty()) {
                    $out['lists']['other_partner'][] = ['number' => $num, 'name' => $v['name'] ?? ''];
                } elseif (strlen($num) === 6) {
                    self::createNew($num, $v, $out, $foundationId, $partnerId, $apply);
                } else {
                    $out['lists']['unclear_numbers'][] = ['file' => '', 'row' => '', 'number' => $num, 'name' => $v['name'] ?? ''];
                }
            }
        };
        if ($apply) {
            DB::transaction($work);
            $out['applied'] = true;
        } else {
            $work();
        }

        // orphans of this partner that neither sheet says anything about
        $q = Child::withoutGlobalScopes()->where('foundation_id', $foundationId)->where('status', '!=', 'ended')->when($partnerId, fn ($q) => $q->where('partner_id', $partnerId))->orderBy('external_no')->orderBy('id');
        foreach ($q->get(['id', 'ref', 'external_no', 'name_en', 'name_ar']) as $c) {
            if (! isset($merged[(string) $c->external_no])) {
                $out['lists']['no_details'][] = ['number' => (string) ($c->external_no ?: $c->ref), 'name_en' => (string) $c->name_en, 'name_ar' => (string) $c->name_ar];
            }
        }
        ksort($out['fields']);

        return $out;
    }

    /** @return list<array> the answer rows of every sheet of one file that has an orphan number column */
    private static function readFile(string $path, string $name, array &$out): array
    {
        $found = [];
        foreach (SheetReader::read($path, $name) as $sheet) {
            $rows = $sheet['rows'];
            $headerAt = null;
            $map = [];
            foreach (array_slice($rows, 0, 10, true) as $i => $r) {
                $map = self::columns($r);
                if (isset($map['number'])) {
                    $headerAt = $i;
                    break;
                }
            }
            $info = ['file' => $name, 'sheet' => $sheet['name'], 'rows' => 0, 'columns' => $headerAt === null ? [] : array_keys($map)];
            if ($headerAt === null) {
                $info['error'] = 'No orphan number column (Numero ng bata) was found in this sheet.';
                $out['files'][] = $info;

                continue;
            }
            foreach (array_slice($rows, $headerAt + 1, null, true) as $i => $r) {
                $get = fn (string $k) => isset($map[$k]) ? trim((string) ($r[$map[$k]] ?? '')) : '';
                if (! array_filter($r, fn ($c) => trim((string) $c) !== '')) {
                    continue;
                }
                $raw = $get('number');
                $vals = [];
                foreach (array_keys(self::HEAD) as $k) {
                    if (! in_array($k, ['ts', 'number'], true) && $get($k) !== '') {
                        $vals[$k] = $get($k);
                    }
                }
                if ($raw === '' && ! $vals) {
                    continue;
                }
                $info['rows']++;
                $found[] = ['file' => $name, 'row' => $i + 1, 'ts' => self::timestamp($get('ts'), $i), 'raw_number' => $raw, 'vals' => $vals];
            }
            $out['files'][] = $info;
        }

        return $found;
    }

    private static function columns(array $header): array
    {
        $map = [];
        foreach ($header as $col => $cell) {
            $n = mb_strtolower(preg_replace('/\s+/u', ' ', trim((string) $cell)));
            if ($n === '') {
                continue;
            }
            foreach (self::HEAD as $key => $words) {
                if (isset($map[$key])) {
                    continue;
                }
                foreach ($words as $w) {
                    if (str_contains($n, $w)) {
                        $map[$key] = $col;
                        continue 3;
                    }
                }
            }
        }

        return $map;
    }

    /** The orphan number in a cell: exactly one six-digit number and no other number, or null. */
    public static function number(string $raw): ?string
    {
        $s = self::digits(preg_replace('/\.0+$/', '', trim($raw)));
        if (preg_match('/^\d+(\.\d+)?e\+?\d+$/i', $s)) {   // 1.61186E5 written by Excel
            $s = sprintf('%.0f', (float) $s);
        }
        if (! preg_match_all('/\d{3,}/', $s, $m) || count($m[0]) !== 1) {
            return null;
        }

        return strlen($m[0][0]) === 6 ? $m[0][0] : null;
    }

    /** Everything known about one orphan number, from all its rows (latest answer first, field by field). */
    private static function combine(string $num, array $list, array &$out): array
    {
        usort($list, fn ($a, $b) => $a['ts'] <=> $b['ts']);
        $v = [];
        $dobs = [];   // file => the latest usable birth date in that file
        foreach ($list as $r) {
            foreach ($r['vals'] as $k => $val) {
                if ($k === 'dob') {
                    [$date, $why] = self::birthDate($val, $r['vals']['age'] ?? '', $r['ts']);
                    if ($date) {
                        $dobs[$r['file']] = $date;
                    } else {
                        $out['lists']['birth_dates'][] = ['number' => $num, 'file' => $r['file'], 'row' => $r['row'], 'written' => self::shownDate($val), 'age' => $r['vals']['age'] ?? '', 'reason' => $why];
                    }

                    continue;
                }
                $v[$k] = $val;   // later rows overwrite earlier ones
            }
        }
        $distinct = array_values(array_unique($dobs));
        if (count($distinct) === 1) {
            $v['birth_date'] = $distinct[0];
        } elseif (count($distinct) > 1) {
            $out['lists']['birth_dates'][] = ['number' => $num, 'file' => implode(' / ', array_keys($dobs)), 'row' => '', 'written' => implode(' / ', $distinct), 'age' => $v['age'] ?? '', 'reason' => 'The two files give different birth dates'];
        }

        // into the record's own fields
        $f = [];
        if (isset($v['name'])) {
            $f['name'] = mb_substr(trim(preg_replace('/\s+/u', ' ', $v['name'])), 0, 150);
        }
        if (isset($v['gender'])) {
            $g = mb_strtolower($v['gender']);
            $f['gender'] = preg_match('/babae|female|girl|أنثى/u', $g) ? 'f' : (preg_match('/lalaki|male|boy|ذكر/u', $g) ? 'm' : null);
        }
        $f['birth_date'] = $v['birth_date'] ?? null;
        $f['madrasa_grade'] = isset($v['madrasa']) ? mb_substr(preg_replace('/^(\d+)\.0+$/', '$1', $v['madrasa']), 0, 60) : null;
        $f['quran_memorization'] = isset($v['quran']) ? mb_substr(preg_replace('/^(\d+)\.0+$/', '$1', $v['quran']), 0, 150) : null;
        $f['notable_changes'] = isset($v['changes']) ? mb_substr($v['changes'], 0, 1500) : null;
        $f['health_details'] = isset($v['health']) ? mb_substr($v['health'], 0, 1000) : null;
        $f['unwell'] = isset($v['health']) && (bool) array_filter(self::UNWELL, fn ($w) => str_contains(mb_strtolower($v['health']), $w));
        $f['message_to_sponsor'] = isset($v['message']) ? mb_substr($v['message'], 0, 1500) : null;
        $f['photo_links'] = isset($v['photos']) ? mb_substr($v['photos'], 0, 3000) : null;
        $f['guardian_name'] = isset($v['guardian']) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $v['guardian'])), 0, 150) : null;
        $f['guardian_relation'] = isset($v['relation']) ? self::relation($v['relation']) : null;
        $f['bank_holder'] = isset($v['bank_holder']) ? mb_substr(trim(preg_replace('/\s+/u', ' ', $v['bank_holder'])), 0, 150) : null;
        $f['bank_last4'] = isset($v['bank_number']) ? self::last4($v['bank_number']) : null;

        return $f;
    }

    /** "Nanay", "Ina", "MOTHER" => Mother ... Anything not known is kept as it was written. */
    public static function relation(string $raw): string
    {
        $n = mb_strtolower(trim($raw));
        foreach (self::RELATION as $to => $words) {
            foreach ($words as $w) {
                if (preg_match('/(^|[^a-z])'.preg_quote($w, '/').'([^a-z]|$)/u', $n)) {
                    return $to;
                }
            }
        }

        return mb_substr(trim($raw), 0, 60);
    }

    private static function last4(string $raw): ?string
    {
        $s = trim($raw);
        if (preg_match('/^\d+(\.\d+)?e\+?\d+$/i', $s)) {   // 9.34109693715E11 written by Excel
            $s = sprintf('%.0f', (float) $s);
        }
        $d = preg_replace('/\D/', '', self::digits(preg_replace('/\.0+$/', '', $s)));

        return strlen($d) >= 4 ? substr($d, -4) : null;
    }

    /** @return array{0: ?string, 1: string} the date (Y-m-d) or null, and the reason when it cannot be used */
    private static function birthDate(string $raw, string $age, float $ts): array
    {
        $v = trim(self::digits($raw));
        $date = null;
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            $date = Carbon::create(1899, 12, 30)->addDays((int) $v);
        } elseif (preg_match('/(\d{4})-(\d{1,2})-(\d{1,2})/', $v, $m)) {
            $date = Carbon::createFromDate((int) $m[1], (int) $m[2], (int) $m[3]);
        } elseif (preg_match('#(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})#', $v, $m)) {
            [$a, $b, $y] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($a > 12 && $b <= 12) {
                $date = Carbon::createFromDate($y, $b, $a);
            } elseif ($b > 12 && $a <= 12) {
                $date = Carbon::createFromDate($y, $a, $b);
            } else {
                return [null, 'Written as text: day and month cannot be told apart'];
            }
        }
        if (! $date) {
            return [null, 'Not a date'];
        }
        if ($date->isFuture() || $date->year < 1995) {
            return [null, 'Not a possible birth date'];
        }
        // the age written, when it is one (some people wrote the birth date there: then there is nothing to check against)
        if (! preg_match('/\d{4}/', self::digits($age)) && preg_match('/\d+/', self::digits($age), $m) && (int) $m[0] <= 25) {
            $at = $ts > 20000 && $ts < 80000 ? Carbon::create(1899, 12, 30)->addDays((int) $ts) : now();
            if (abs($date->diffInYears($at) - (int) $m[0]) > 1) {
                return [null, 'Does not fit the age written ('.$m[0].')'];
            }
        }

        return [$date->toDateString(), ''];
    }

    private static function shownDate(string $raw): string
    {
        $v = trim($raw);

        return is_numeric($v) && (float) $v > 20000 && (float) $v < 80000 ? Carbon::create(1899, 12, 30)->addDays((int) $v)->toDateString() : $v;
    }

    private static function timestamp(string $v, int $row): float
    {
        if (is_numeric($v)) {
            return (float) $v;
        }
        $t = $v !== '' ? strtotime($v) : false;

        return $t ? $t / 86400 + 25569 : $row / 1e6;   // rows without a time keep their order in the file
    }

    private static function digits(string $s): string
    {
        return OrphanSheetImport::digits($s);
    }

    private static function same(mixed $a, mixed $b): bool
    {
        $n = fn ($x) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', (string) $x)));

        return $n($a) === $n($b);
    }

    private static function blank(mixed $v): bool
    {
        return $v === null || trim((string) $v) === '' || trim((string) $v) === '-';
    }

    /** Fills the empty fields of an orphan already in the system. Returns whether anything was (or would be) written. */
    private static function fillExisting(Child $c, array $v, array &$out, bool $apply): bool
    {
        $num = (string) $c->external_no;
        $set = [];
        $conflict = function (string $field, mixed $now, mixed $sheet) use (&$out, $num, $c) {
            $out['lists']['conflicts'][] = ['number' => $num, 'name' => (string) ($c->name_en ?: $c->name_ar), 'field' => $field, 'saved' => (string) $now, 'sheet' => (string) $sheet];
        };
        foreach (self::CHILD_FIELDS as $k) {
            if (self::blank($v[$k] ?? null)) {
                continue;
            }
            $now = $c->{$k} instanceof \DateTimeInterface ? $c->{$k}->format('Y-m-d') : $c->{$k};
            if (self::blank($now)) {
                $set[$k] = $v[$k];
            } elseif (! self::same($now, $v[$k])) {
                $conflict($k, $now, $v[$k]);
            }
        }
        if ($v['unwell']) {
            // "good" is what every new record starts with: while nobody has written anything about the health, it was never decided
            if (self::blank($c->health_status) || ($c->health_status === 'good' && self::blank($c->health_details))) {
                $set['health_status'] = 'needs_followup';
            } elseif ($c->health_status !== 'needs_followup') {
                $conflict('health_status', $c->health_status, 'needs_followup');
            }
        }
        $wrote = (bool) $set;
        foreach (array_keys($set) as $k) {
            $out['fields'][$k] = ($out['fields'][$k] ?? 0) + 1;
        }
        if ($apply && $set) {
            $c->forceFill($set)->save();
        }

        // the guardian
        $g = $c->guardian;
        if (! self::blank($v['guardian_name'])) {
            if (! $g) {
                $wrote = true;
                $out['fields']['guardian_name'] = ($out['fields']['guardian_name'] ?? 0) + 1;
                if ($v['guardian_relation']) {
                    $out['fields']['guardian_relation'] = ($out['fields']['guardian_relation'] ?? 0) + 1;
                }
                if ($apply) {
                    $g = Guardian::withoutGlobalScopes()->create(['foundation_id' => $c->foundation_id, 'name' => $v['guardian_name'], 'relation' => $v['guardian_relation']]);
                    $c->forceFill(['guardian_id' => $g->id])->save();
                }
            } else {
                $gset = [];
                if (self::blank($g->name)) {
                    $gset['name'] = $v['guardian_name'];
                } elseif (! self::same($g->name, $v['guardian_name'])) {
                    $conflict('guardian_name', $g->name, $v['guardian_name']);
                }
                if ($v['guardian_relation']) {
                    if (self::blank($g->relation)) {
                        $gset['relation'] = $v['guardian_relation'];
                    } elseif (! self::same($g->relation, $v['guardian_relation'])) {
                        $conflict('guardian_relation', $g->relation, $v['guardian_relation']);
                    }
                }
                foreach (array_keys($gset) as $k) {
                    $out['fields']['guardian_'.$k] = ($out['fields']['guardian_'.$k] ?? 0) + 1;
                }
                if ($gset) {
                    $wrote = true;
                    if ($apply) {
                        $g->forceFill($gset)->save();
                    }
                }
            }
        }

        // the bank account: only for an orphan paid by bank
        if ($v['bank_holder'] && $v['bank_last4'] && $c->sponsorship?->pay_type === 'bank') {
            $b = $c->bankAccount;
            if (! $b) {
                $wrote = true;
                $out['fields']['bank_account'] = ($out['fields']['bank_account'] ?? 0) + 1;
                if ($apply) {
                    BankAccount::withoutGlobalScopes()->create(['foundation_id' => $c->foundation_id, 'child_id' => $c->id, 'holder' => $v['bank_holder'], 'bank_name' => '', 'last4' => $v['bank_last4'], 'verified' => false]);
                }
            } else {
                if (self::blank($b->holder)) {
                    $wrote = true;
                    $out['fields']['bank_holder'] = ($out['fields']['bank_holder'] ?? 0) + 1;
                    if ($apply) {
                        $b->forceFill(['holder' => $v['bank_holder'], 'verified' => false, 'verified_by' => null, 'verified_at' => null])->save();
                    }
                }
                if ($b->last4 !== $v['bank_last4']) {
                    $conflict('bank_last4', $b->last4, $v['bank_last4']);
                }
            }
        }

        return $wrote;
    }

    /** A number the system does not have yet: a new orphan, pending, with no bank or cash chosen. */
    private static function createNew(string $num, array $v, array &$out, int $foundationId, ?int $partnerId, bool $apply): void
    {
        $name = $v['name'] ?? '';
        if ($name === '' || preg_match('/\d|\b(born|running|years?|yrs?|old|taon)\b/iu', $name)) {
            $out['lists']['names_to_check'][] = ['number' => $num, 'name' => $name];
        }
        $name = $name !== '' ? $name : 'Orphan '.$num;
        $out['created']++;
        $out['new_orphans'][] = ['number' => $num, 'name' => $name];
        $fields = array_filter(array_intersect_key($v, array_flip(self::CHILD_FIELDS)), fn ($x) => ! self::blank($x));
        if ($v['unwell']) {
            $fields['health_status'] = 'needs_followup';
        }
        if (! $apply) {
            return;
        }
        $data = ['name_en' => $name, 'status' => 'pending', 'partner_id' => $partnerId] + $fields;
        if (! self::blank($v['guardian_name'])) {
            $data['guardian'] = array_filter(['name' => $v['guardian_name'], 'relation' => $v['guardian_relation']]);
        }
        $data['pay_type'] = '';   // not bank, not cash: chosen when a sponsor sheet lists this orphan
        $child = ChildRecords::create($data, $foundationId);
        $child->forceFill(['external_no' => $num])->save();
    }
}
