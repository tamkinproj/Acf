<?php

namespace App\Support;

use App\Models\Child;

/**
 * Names the way the Dar Al Ber form asks for them: first name, father's name, grandfather's name, family name,
 * in Arabic and in English, for the orphan, the mother and the direct responsible person.
 * The full name (name_ar, name_en, mother_name_ar, mother_name, responsible_name_ar, responsible_name_en) is always kept too,
 * joined from the parts; a record that only has a full name gets its parts by splitting it.
 */
final class NameParts
{
    public const PARTS = ['first', 'father', 'grand', 'family'];

    /** who => [language => the column that holds the full name] */
    public const WHO = [
        'child' => ['ar' => 'name_ar', 'en' => 'name_en'],
        'mother' => ['ar' => 'mother_name_ar', 'en' => 'mother_name'],
        'resp' => ['ar' => 'responsible_name_ar', 'en' => 'responsible_name_en'],
    ];

    public static function key(string $who, string $part, string $lang): string
    {
        return "{$who}_{$part}_{$lang}";
    }

    /** Every part column, e.g. child_first_ar. */
    public static function keys(): array
    {
        $out = [];
        foreach (self::WHO as $who => $langs) {
            foreach (array_keys($langs) as $lang) {
                foreach (self::PARTS as $p) {
                    $out[] = self::key($who, $p, $lang);
                }
            }
        }

        return $out;
    }

    /** "ALYUSEP NORON KASIM KALI" -> first, father, grandfather, family (everything after the third word is the family name). */
    public static function split(?string $full): array
    {
        $w = preg_split('/\s+/u', trim((string) $full), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return [$w[0] ?? null, $w[1] ?? null, $w[2] ?? null, count($w) > 3 ? implode(' ', array_slice($w, 3)) : null];
    }

    public static function join(array $parts): string
    {
        return trim(preg_replace('/\s+/u', ' ', implode(' ', array_filter(array_map(fn ($p) => trim((string) $p), $parts), fn ($p) => $p !== ''))));
    }

    /**
     * Before validating a request: the full names come from the parts, the guardian comes from the responsible person.
     * $child = the record being edited (its parts fill in what the request leaves out), or null for a new one.
     */
    public static function prepare(array $in, ?Child $child = null): array
    {
        foreach (self::WHO as $who => $langs) {
            foreach ($langs as $lang => $full) {
                $sent = array_intersect_key($in, array_flip(array_map(fn ($p) => self::key($who, $p, $lang), self::PARTS)));
                if ($sent) {
                    $parts = [];
                    foreach (self::PARTS as $p) {
                        $k = self::key($who, $p, $lang);
                        $parts[] = array_key_exists($k, $in) ? $in[$k] : ($child?->{$k});
                    }
                    $j = self::join($parts);
                    $in[$full] = $j !== '' ? $j : null;
                }
            }
        }
        if (empty($in['name_en']) && ! empty($in['name_ar'])) {
            $in['name_en'] = $in['name_ar'];          // the record is listed under the English name; fall back to Arabic
        }

        $respName = ! empty($in['responsible_name_ar']) ? $in['responsible_name_ar'] : ($in['responsible_name_en'] ?? null);
        $relation = $in['responsible_relation'] ?? null;
        if (isset($in['guardian']) && is_array($in['guardian'])) {            // the staff form: nested guardian
            if (empty($in['guardian']['name'])) {
                $in['guardian']['name'] = $respName ?: ($child?->guardian?->name ?: '-');
            }
            if (empty($in['guardian']['relation']) && $relation) {
                $in['guardian']['relation'] = $relation;
            }
        } elseif (! array_key_exists('guardian_name', $in) || $in['guardian_name'] === null || $in['guardian_name'] === '') {   // the public form: flat answers
            if ($respName) {
                $in['guardian_name'] = $respName;
            }
        }
        if ($relation && empty($in['guardian_relation']) && ! isset($in['guardian'])) {
            $in['guardian_relation'] = $relation;
        }

        return $in;
    }

    /** On every save of a record: parts and full names always agree. */
    public static function sync(Child $c): void
    {
        foreach (self::WHO as $who => $langs) {
            foreach ($langs as $lang => $full) {
                $cols = array_map(fn ($p) => self::key($who, $p, $lang), self::PARTS);
                $partsChanged = $c->isDirty($cols);
                $partsEmpty = collect($cols)->every(fn ($k) => trim((string) $c->{$k}) === '');
                if ($partsChanged) {
                    $j = self::join(array_map(fn ($k) => $c->{$k}, $cols));
                    if ($j !== '' || $full !== 'name_en') {
                        $c->{$full} = $j !== '' ? $j : null;
                    }
                } elseif (trim((string) $c->{$full}) !== '' && ($partsEmpty || $c->isDirty($full))) {
                    foreach (self::split($c->{$full}) as $i => $v) {
                        $c->{$cols[$i]} = $v;
                    }
                }
            }
        }
    }
}
