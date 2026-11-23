<?php

namespace App\Support\Pdf;

/** What the filler needs from a TrueType font already inside the form: which glyph draws a character, and how wide it is. */
final class TrueType
{
    public int $upm = 1000;

    public int $ascent = 800;

    public int $descent = -200;

    /** @var array<int, int> Unicode code point => glyph id */
    public array $cmap = [];

    /** @var array<int, int> glyph id => advance width in font units */
    public array $adv = [];

    public int $numGlyphs = 0;

    private string $bin = '';

    private array $loca = [];

    private int $glyf = -1;

    public static function parse(string $b): self
    {
        $t = new self();
        $n = self::u16($b, 4);
        $tables = [];
        for ($i = 0; $i < $n; $i++) {
            $o = 12 + $i * 16;
            $tables[substr($b, $o, 4)] = [self::u32($b, $o + 8), self::u32($b, $o + 12)];
        }
        foreach (['head', 'hhea', 'hmtx', 'maxp', 'cmap'] as $need) {
            if (! isset($tables[$need])) {
                throw new \RuntimeException('The font in the form is missing its '.$need.' table.');
            }
        }
        $t->upm = self::u16($b, $tables['head'][0] + 18) ?: 1000;
        $t->numGlyphs = self::u16($b, $tables['maxp'][0] + 4);
        $h = $tables['hhea'][0];
        $t->ascent = self::s16($b, $h + 4);
        $t->descent = self::s16($b, $h + 6);
        $metrics = self::u16($b, $h + 34);
        $m = $tables['hmtx'][0];
        $last = 0;
        for ($g = 0; $g < $t->numGlyphs; $g++) {
            if ($g < $metrics) {
                $last = self::u16($b, $m + $g * 4);
            }
            $t->adv[$g] = $last;
        }
        $t->readCmap($b, $tables['cmap'][0]);
        if (isset($tables['loca'], $tables['glyf'])) {
            $long = self::s16($b, $tables['head'][0] + 50) === 1;
            $lo = $tables['loca'][0];
            for ($g = 0; $g <= $t->numGlyphs; $g++) {
                $t->loca[$g] = $long ? self::u32($b, $lo + $g * 4) : self::u16($b, $lo + $g * 2) * 2;
            }
            $t->glyf = $tables['glyf'][0];
            $t->bin = $b;
        }

        return $t;
    }

    private function readCmap(string $b, int $base): void
    {
        $n = self::u16($b, $base + 2);
        $best = null;
        $score = -1;
        for ($i = 0; $i < $n; $i++) {
            $o = $base + 4 + $i * 8;
            $pid = self::u16($b, $o);
            $eid = self::u16($b, $o + 2);
            $off = $base + self::u32($b, $o + 4);
            $fmt = self::u16($b, $off);
            $s = match (true) {
                $pid === 3 && $eid === 10 && $fmt === 12 => 4,
                $pid === 0 && $fmt === 12 => 3,
                $pid === 3 && $eid === 1 && $fmt === 4 => 2,
                $pid === 0 && $fmt === 4 => 1,
                default => -1,
            };
            if ($s > $score) {
                [$score, $best] = [$s, $off];
            }
        }
        if ($best === null) {
            throw new \RuntimeException('The font in the form has no Unicode character map.');
        }
        if (self::u16($b, $best) === 12) {
            $groups = self::u32($b, $best + 12);
            for ($i = 0; $i < $groups; $i++) {
                $o = $best + 16 + $i * 12;
                [$s, $e, $g] = [self::u32($b, $o), self::u32($b, $o + 4), self::u32($b, $o + 8)];
                for ($c = $s; $c <= $e && $c - $s < 70000; $c++) {
                    $this->cmap[$c] = $g + $c - $s;
                }
            }

            return;
        }
        $seg = self::u16($b, $best + 6) >> 1;
        $ends = $best + 14;
        $starts = $ends + $seg * 2 + 2;
        $deltas = $starts + $seg * 2;
        $ranges = $deltas + $seg * 2;
        for ($i = 0; $i < $seg; $i++) {
            $end = self::u16($b, $ends + $i * 2);
            $start = self::u16($b, $starts + $i * 2);
            $delta = self::s16($b, $deltas + $i * 2);
            $ro = self::u16($b, $ranges + $i * 2);
            for ($c = $start; $c <= $end && $c !== 0xFFFF; $c++) {
                if ($ro === 0) {
                    $g = ($c + $delta) & 0xFFFF;
                } else {
                    $at = $ranges + $i * 2 + $ro + ($c - $start) * 2;
                    $g = $at + 1 < strlen($b) ? self::u16($b, $at) : 0;
                    if ($g !== 0) {
                        $g = ($g + $delta) & 0xFFFF;
                    }
                }
                if ($g !== 0) {
                    $this->cmap[$c] = $g;
                }
            }
        }
    }

    /** The box a glyph's outline fills [xMin, yMin, xMax, yMax] in font units, or null (empty glyph, or not known). */
    public function bbox(int $gid): ?array
    {
        if ($this->glyf < 0 || ! isset($this->loca[$gid + 1]) || $this->loca[$gid + 1] <= $this->loca[$gid]) {
            return null;
        }
        $o = $this->glyf + $this->loca[$gid];

        return [self::s16($this->bin, $o + 2), self::s16($this->bin, $o + 4), self::s16($this->bin, $o + 6), self::s16($this->bin, $o + 8)];
    }

    /** Width of a glyph in thousandths of the font size (the unit PDF uses). */
    public function width(int $gid): float
    {
        return ($this->adv[$gid] ?? 0) * 1000 / $this->upm;
    }

    private static function u16(string $b, int $o): int
    {
        return (ord($b[$o] ?? "\0") << 8) | ord($b[$o + 1] ?? "\0");
    }

    private static function s16(string $b, int $o): int
    {
        $v = self::u16($b, $o);

        return $v >= 0x8000 ? $v - 0x10000 : $v;
    }

    private static function u32(string $b, int $o): int
    {
        return (self::u16($b, $o) << 16) | self::u16($b, $o + 2);
    }
}
