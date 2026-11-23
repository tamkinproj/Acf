<?php

namespace App\Support\Pdf;

/**
 * Turns a line of text into what is drawn, left to right: Arabic letters take their joined shapes (start, middle,
 * end, alone, and lam-alef), and right-to-left words are put in display order while numbers and Latin words keep theirs.
 */
final class Arabic
{
    /** letter => [first presentation form, how many forms: 1 alone, 2 alone+end, 4 alone+end+start+middle] */
    private const FORMS = [
        0x0621 => [0xFE80, 1], 0x0622 => [0xFE81, 2], 0x0623 => [0xFE83, 2], 0x0624 => [0xFE85, 2], 0x0625 => [0xFE87, 2],
        0x0626 => [0xFE89, 4], 0x0627 => [0xFE8D, 2], 0x0628 => [0xFE8F, 4], 0x0629 => [0xFE93, 2], 0x062A => [0xFE95, 4],
        0x062B => [0xFE99, 4], 0x062C => [0xFE9D, 4], 0x062D => [0xFEA1, 4], 0x062E => [0xFEA5, 4], 0x062F => [0xFEA9, 2],
        0x0630 => [0xFEAB, 2], 0x0631 => [0xFEAD, 2], 0x0632 => [0xFEAF, 2], 0x0633 => [0xFEB1, 4], 0x0634 => [0xFEB5, 4],
        0x0635 => [0xFEB9, 4], 0x0636 => [0xFEBD, 4], 0x0637 => [0xFEC1, 4], 0x0638 => [0xFEC5, 4], 0x0639 => [0xFEC9, 4],
        0x063A => [0xFECD, 4], 0x0641 => [0xFED1, 4], 0x0642 => [0xFED5, 4], 0x0643 => [0xFED9, 4], 0x0644 => [0xFEDD, 4],
        0x0645 => [0xFEE1, 4], 0x0646 => [0xFEE5, 4], 0x0647 => [0xFEE9, 4], 0x0648 => [0xFEED, 2], 0x0649 => [0xFEEF, 2],
        0x064A => [0xFEF1, 4], 0x067E => [0xFB56, 4], 0x0686 => [0xFB7A, 4], 0x0698 => [0xFB8A, 2], 0x06A4 => [0xFB6A, 4],
        0x06A9 => [0xFB8E, 4], 0x06AF => [0xFB92, 4], 0x06CC => [0xFBFC, 4],
    ];

    /** lam + this alef => [alone, end] ligature */
    private const LAM_ALEF = [0x0622 => [0xFEF5, 0xFEF6], 0x0623 => [0xFEF7, 0xFEF8], 0x0625 => [0xFEF9, 0xFEFA], 0x0627 => [0xFEFB, 0xFEFC]];

    private const MIRROR = [0x28 => 0x29, 0x29 => 0x28, 0x5B => 0x5D, 0x5D => 0x5B, 0x7B => 0x7D, 0x7D => 0x7B, 0x3C => 0x3E, 0x3E => 0x3C, 0xAB => 0xBB, 0xBB => 0xAB];

    public static function isMark(int $c): bool
    {
        return ($c >= 0x0610 && $c <= 0x061A) || ($c >= 0x064B && $c <= 0x065F) || $c === 0x0670 || ($c >= 0x06D6 && $c <= 0x06DC) || ($c >= 0x06DF && $c <= 0x06E4) || $c === 0x06E7 || $c === 0x06E8 || ($c >= 0x06EA && $c <= 0x06ED);
    }

    private static function joinType(int $c): string
    {
        if (isset(self::FORMS[$c])) {
            return [1 => 'U', 2 => 'R', 4 => 'D'][self::FORMS[$c][1]];
        }
        if ($c === 0x0640) {
            return 'C';
        }

        return self::isMark($c) ? 'T' : 'U';
    }

    private static function bidiClass(int $c): string
    {
        if (($c >= 0x0600 && $c <= 0x06FF && ! ($c >= 0x0660 && $c <= 0x0669) && ! ($c >= 0x06F0 && $c <= 0x06F9)) || ($c >= 0x0750 && $c <= 0x077F) || ($c >= 0xFB50 && $c <= 0xFDFF) || ($c >= 0xFE70 && $c <= 0xFEFF) || ($c >= 0x0590 && $c <= 0x05FF)) {
            return self::isMark($c) ? 'NSM' : 'R';
        }
        if ($c >= 0x30 && $c <= 0x39) {
            return 'EN';
        }
        if (($c >= 0x0660 && $c <= 0x0669) || ($c >= 0x06F0 && $c <= 0x06F9)) {
            return 'AN';
        }
        if ($c === 0x20 || $c === 0x09 || $c === 0xA0) {
            return 'WS';
        }
        if (in_array($c, [0x2B, 0x2D], true)) {
            return 'ES';
        }
        if (in_array($c, [0x2C, 0x2E, 0x3A, 0x2F], true)) {
            return 'CS';
        }
        if (in_array($c, [0x23, 0x24, 0x25, 0xB0], true)) {
            return 'ET';
        }
        if (($c >= 0x41 && $c <= 0x5A) || ($c >= 0x61 && $c <= 0x7A) || ($c >= 0xC0 && $c <= 0x24F)) {
            return 'L';
        }
        if ($c < 0x80 || ($c >= 0x2000 && $c <= 0x2BFF)) {
            return 'ON';
        }

        return 'L';
    }

    /** @return int[] */
    public static function codepoints(string $s): array
    {
        $out = [];
        foreach (mb_str_split($s, 1, 'UTF-8') as $ch) {
            $out[] = mb_ord($ch, 'UTF-8');
        }

        return $out;
    }

    /** Does this text start (first strong letter) right to left? */
    public static function isRtl(string $s, bool $default = true): bool
    {
        foreach (self::codepoints($s) as $c) {
            $b = self::bidiClass($c);
            if ($b === 'R') {
                return true;
            }
            if ($b === 'L') {
                return false;
            }
        }

        return $default;
    }

    /**
     * One line, ready to draw left to right: a list of clusters (a letter and the marks over it), each a list of
     * [code point to draw, the original text it stands for]. $has says whether the font can draw a code point.
     *
     * @return array<int, array<int, array{0:int, 1:string}>>
     */
    public static function visual(string $line, callable $has, bool $defaultRtl = true): array
    {
        $cps = self::codepoints($line);
        $n = count($cps);
        $prevJoin = function (int $i) use ($cps): bool {
            for ($k = $i - 1; $k >= 0; $k--) {
                $t = self::joinType($cps[$k]);
                if ($t === 'T') {
                    continue;
                }

                return $t === 'D' || $t === 'C';
            }

            return false;
        };
        $nextIdx = function (int $i) use ($cps, $n): ?int {
            for ($k = $i + 1; $k < $n; $k++) {
                if (self::joinType($cps[$k]) !== 'T') {
                    return $k;
                }
            }

            return null;
        };
        // 1. shape, in reading order, into clusters
        $clusters = [];
        for ($i = 0; $i < $n; $i++) {
            $c = $cps[$i];
            $t = self::joinType($c);
            if ($t === 'T' && $clusters) {
                $clusters[count($clusters) - 1]['g'][] = [$c, mb_chr($c, 'UTF-8')];

                continue;
            }
            $draw = $c;
            $text = mb_chr($c, 'UTF-8');
            $j = $nextIdx($i);
            $next = $j === null ? null : $cps[$j];
            if ($c === 0x0644 && $next !== null && isset(self::LAM_ALEF[$next]) && $has(self::LAM_ALEF[$next][0])) {
                $lig = self::LAM_ALEF[$next][$prevJoin($i) ? 1 : 0];
                $g = [[$has($lig) ? $lig : self::LAM_ALEF[$next][0], $text.mb_chr($next, 'UTF-8')]];
                for ($k = $i + 1; $k < $j; $k++) {   // marks between lam and alef
                    $g[] = [$cps[$k], mb_chr($cps[$k], 'UTF-8')];
                }
                $clusters[] = ['g' => $g, 'b' => 'R'];
                $i = $j;

                continue;
            }
            if ($t === 'D' || $t === 'R') {
                $pj = $prevJoin($i);
                $nj = $t === 'D' && $next !== null && in_array(self::joinType($next), ['D', 'R', 'C'], true);
                $form = $t === 'R' ? ($pj ? 1 : 0) : ($pj && $nj ? 3 : ($pj ? 1 : ($nj ? 2 : 0)));
                $want = self::FORMS[$c][0] + $form;
                if ($form > 0 && $has($want)) {
                    $draw = $want;
                }
            }
            $clusters[] = ['g' => [[$draw, $text]], 'b' => self::bidiClass($c)];
        }
        if (! $clusters) {
            return [];
        }
        // 2. direction of the line, from its first strong letter
        $rtl = $defaultRtl;
        foreach ($clusters as $cl) {
            if ($cl['b'] === 'R' || $cl['b'] === 'L') {
                $rtl = $cl['b'] === 'R';
                break;
            }
        }
        $base = $rtl ? 1 : 0;
        $m = count($clusters);
        $cls = array_column($clusters, 'b');
        // 3. numbers and their separators (simplified Unicode bidi rules W1-W7)
        $lastStrong = $rtl ? 'R' : 'L';
        for ($i = 0; $i < $m; $i++) {
            if ($cls[$i] === 'NSM') {
                $cls[$i] = $i ? $cls[$i - 1] : ($rtl ? 'R' : 'L');
            }
            if ($cls[$i] === 'R' || $cls[$i] === 'L') {
                $lastStrong = $cls[$i];
            } elseif ($cls[$i] === 'EN') {
                $cls[$i] = $lastStrong === 'R' ? 'AN' : ($lastStrong === 'L' ? 'L' : 'EN');
            }
        }
        for ($i = 1; $i < $m - 1; $i++) {
            $p = $cls[$i - 1];
            $q = $cls[$i + 1];
            if (($cls[$i] === 'ES' || $cls[$i] === 'CS') && $p === $q && ($p === 'EN' || ($p === 'AN' && $cls[$i] === 'CS') || ($p === 'L' && ! $rtl))) {
                $cls[$i] = $p;
            }
        }
        for ($i = 0; $i < $m; $i++) {
            if ($cls[$i] === 'ET' && (($cls[$i - 1] ?? '') === 'AN' || ($cls[$i + 1] ?? '') === 'AN' || ($cls[$i - 1] ?? '') === 'EN' || ($cls[$i + 1] ?? '') === 'EN')) {
                $cls[$i] = ($cls[$i - 1] ?? '') === 'AN' || ($cls[$i + 1] ?? '') === 'AN' ? 'AN' : 'EN';
            }
        }
        // 4. neutrals take the direction around them, else the line's (N1, N2)
        $dir = fn (string $c) => $c === 'L' ? 'L' : (in_array($c, ['R', 'AN', 'EN'], true) ? 'R' : null);
        for ($i = 0; $i < $m; $i++) {
            if ($dir($cls[$i]) !== null) {
                continue;
            }
            $s = $i;
            while ($i < $m && $dir($cls[$i]) === null) {
                $i++;
            }
            $before = $s > 0 ? $dir($cls[$s - 1]) : ($rtl ? 'R' : 'L');
            $after = $i < $m ? $dir($cls[$i]) : ($rtl ? 'R' : 'L');
            $res = $before === $after ? $before : ($rtl ? 'R' : 'L');
            for ($k = $s; $k < $i; $k++) {
                $cls[$k] = $res;
            }
            $i--;
        }
        // 5. levels, then reverse runs from the highest level down
        $lv = [];
        foreach ($cls as $i => $c) {
            $lv[$i] = $base === 0 ? ($c === 'R' ? 1 : (($c === 'AN' || $c === 'EN') ? 2 : 0)) : ($c === 'R' ? 1 : 2);
        }
        $order = range(0, $m - 1);
        for ($level = max($lv); $level >= 1; $level--) {
            for ($i = 0; $i < $m; $i++) {
                if ($lv[$order[$i]] < $level) {
                    continue;
                }
                $s = $i;
                while ($i < $m && $lv[$order[$i]] >= $level) {
                    $i++;
                }
                $slice = array_reverse(array_slice($order, $s, $i - $s));
                array_splice($order, $s, $i - $s, $slice);
            }
        }
        $out = [];
        foreach ($order as $idx) {
            $g = $clusters[$idx]['g'];
            if ($lv[$idx] % 2 === 1 && isset(self::MIRROR[$g[0][0]])) {
                $g[0][0] = self::MIRROR[$g[0][0]];
            }
            $out[] = $g;
        }

        return $out;
    }
}
