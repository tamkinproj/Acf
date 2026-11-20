<?php

namespace App\Support;

use App\Models\SiteImage;

/**
 * Turns whatever the editor sends into a clean draft, or says what is wrong.
 * Everything is plain text (the landing page shows it with textContent, never as HTML), lengths are capped,
 * links must be https, numbers are whole numbers, and photo ids must be this foundation's own uploads.
 */
final class SiteContentRules
{
    /** The content the page shows today: every text, number and single value at its default, lists empty. */
    public static function defaults(): array
    {
        $c = config('site');
        $out = ['texts' => [], 'singles' => [], 'numbers' => [], 'images' => [], 'projects' => [], 'team' => [], 'news' => []];
        foreach ($c['texts'] as $k => $f) {
            $out['texts'][$k] = ['en' => $f['en'], 'ar' => $f['ar']];
        }
        foreach ($c['singles'] as $k => $f) {
            $out['singles'][$k] = $f['default'];
        }
        foreach ($c['numbers'] as $k => $f) {
            $out['numbers'][$k] = $f['default'];
        }
        foreach ($c['images'] as $k => $f) {
            $out['images'][$k] = null;
        }

        return $out;
    }

    /** @return array{0: array, 1: array<string, string>} cleaned content and errors (path => message) */
    public static function clean(array $in, int $foundationId): array
    {
        $c = config('site');
        $out = self::defaults();
        $err = [];

        if (strlen((string) json_encode($in)) > $c['limits']['max_json_bytes']) {
            return [$out, ['content' => 'This is too large to save.']];
        }

        foreach ($c['texts'] as $k => $f) {
            foreach (['en', 'ar'] as $l) {
                $v = self::text($in['texts'][$k][$l] ?? $out['texts'][$k][$l], $f['max'], $f['long']);
                $out['texts'][$k][$l] = $v;
            }
        }
        foreach ($c['singles'] as $k => $f) {
            $v = self::text($in['singles'][$k] ?? $f['default'], 200, false);
            if ($v !== '' && $f['type'] === 'url' && ! preg_match('#^https://[^\s<>"\']{3,190}$#i', $v)) {
                $err["singles.$k"] = 'Use a full address starting with https://';
            } elseif ($v !== '' && $f['type'] === 'email' && ! filter_var($v, FILTER_VALIDATE_EMAIL)) {
                $err["singles.$k"] = 'Enter a valid email address.';
            } elseif ($v !== '' && $f['type'] === 'phone' && ! preg_match('/^\+?[0-9 ()\-]{5,30}$/', $v)) {
                $err["singles.$k"] = 'Use digits, spaces and + ( ) - only.';
            }
            $out['singles'][$k] = $v;
        }
        foreach ($c['numbers'] as $k => $f) {
            $n = $in['numbers'][$k] ?? $f['default'];
            if (! is_numeric($n) || $n < 0 || $n > 10000000 || (int) $n != $n) {
                $err["numbers.$k"] = 'Enter a whole number from 0 to 10,000,000.';
                $n = $f['default'];
            }
            $out['numbers'][$k] = (int) $n;
        }

        $ids = [];
        $image = function (mixed $id, string $path) use (&$err, &$ids): ?int {
            if ($id === null || $id === '' || $id === false) {
                return null;
            }
            if (! is_numeric($id)) {
                $err[$path] = 'Unknown photo.';

                return null;
            }
            $ids[(int) $id] = true;

            return (int) $id;
        };

        foreach ($c['images'] as $k => $f) {
            $out['images'][$k] = $image($in['images'][$k] ?? null, "images.$k");
        }

        $list = function (string $name, array $fields) use ($in, $c, &$err, $image): array {
            $rows = $in[$name] ?? [];
            if (! is_array($rows)) {
                $err[$name] = 'Invalid list.';

                return [];
            }
            if (count($rows) > $c['lists'][$name]['max']) {
                $err[$name] = 'Too many items (maximum '.$c['lists'][$name]['max'].').';
                $rows = array_slice($rows, 0, $c['lists'][$name]['max']);
            }
            $out = [];
            foreach (array_values($rows) as $i => $r) {
                if (! is_array($r)) {
                    continue;
                }
                $row = ['visible' => ! array_key_exists('visible', $r) || (bool) $r['visible'], 'photo' => $image($r['photo'] ?? null, "$name.$i.photo")];
                foreach ($fields as $key => [$max, $long]) {
                    $row[$key] = self::text($r[$key] ?? '', $max, $long);
                }
                if ($name === 'projects') {
                    $row['category'] = in_array($r['category'] ?? '', $c['categories'], true) ? $r['category'] : 'orphan';
                }
                $out[] = $row;
            }

            return $out;
        };

        $out['projects'] = $list('projects', ['title_en' => [150, false], 'title_ar' => [150, false], 'location_en' => [150, false], 'location_ar' => [150, false],
            'year' => [4, false], 'beneficiaries' => [9, false], 'sponsor_en' => [100, false], 'sponsor_ar' => [100, false]]);
        foreach ($out['projects'] as $i => &$p) {
            if ($p['year'] !== '' && ! preg_match('/^\d{4}$/', $p['year'])) {
                $err["projects.$i.year"] = 'Use a 4-digit year.';
            }
            if ($p['beneficiaries'] !== '' && ! ctype_digit($p['beneficiaries'])) {
                $err["projects.$i.beneficiaries"] = 'Whole number only.';
            }
            if ($p['title_en'] === '' && $p['title_ar'] === '') {
                $err["projects.$i.title_en"] = 'Give the project a title.';
            }
        }
        unset($p);

        $out['team'] = $list('team', ['name_en' => [100, false], 'name_ar' => [100, false], 'role_en' => [100, false], 'role_ar' => [100, false]]);
        foreach ($out['team'] as $i => $m) {
            if ($m['name_en'] === '' && $m['name_ar'] === '') {
                $err["team.$i.name_en"] = 'Give the person a name.';
            }
        }

        $out['news'] = $list('news', ['title_en' => [150, false], 'title_ar' => [150, false], 'summary_en' => [600, true], 'summary_ar' => [600, true], 'date' => [10, false]]);
        foreach ($out['news'] as $i => $n) {
            if ($n['title_en'] === '' && $n['title_ar'] === '') {
                $err["news.$i.title_en"] = 'Give the item a title.';
            }
            if ($n['date'] !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $n['date'])) {
                $err["news.$i.date"] = 'Use the format YYYY-MM-DD.';
            }
        }

        if ($ids) {
            $found = SiteImage::withoutGlobalScopes()->where('foundation_id', $foundationId)->whereIn('id', array_keys($ids))->pluck('id')->all();
            foreach (array_diff(array_keys($ids), $found) as $missing) {
                $err['images'] = "Photo #$missing is not one of your uploads.";
            }
        }

        return [$out, $err];
    }

    /** Plain text only: no control characters, no tags, trimmed, capped. Line breaks kept only in long fields. */
    private static function text(mixed $v, int $max, bool $long): string
    {
        $v = is_scalar($v) ? (string) $v : '';
        $v = str_replace(["\r\n", "\r"], "\n", $v);
        $v = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $v) ?? '';
        $v = strip_tags($v);
        if (! $long) {
            $v = preg_replace('/\s*\n\s*/', ' ', $v) ?? '';
        }

        return mb_substr(trim($v), 0, $max);
    }
}
