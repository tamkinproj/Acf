<?php

namespace App\Services\OrphanReports;

use App\Models\OrphanReport;

/**
 * Keeps the written texts different from orphan to orphan in the same year: first a different mix of written
 * variants than any other orphan, and never exactly the same words.
 */
final class ReportTexts
{
    public const KINDS = ['changes', 'comment', 'message'];

    /** @var array<string, array<string, true>> per kind: texts already used */
    private array $texts = [];

    /** @var array<string, array<string, true>> per kind: variant mixes already used */
    private array $codes = [];

    /** What other orphans' saved reports of this year (in the partner the request works in) already say. */
    public static function forYear(int $year, ?int $exceptChild = null): self
    {
        $t = new self();
        $rows = OrphanReport::query()->where('year', $year)->when($exceptChild, fn ($q) => $q->where('child_id', '!=', $exceptChild))->get(['changes', 'comment', 'message', 'codes']);
        foreach ($rows as $r) {
            foreach (self::KINDS as $k) {
                if ($r->{$k}) {
                    $t->texts[$k][self::key($r->{$k})] = true;
                }
            }
            foreach (explode('|', (string) $r->codes) as $code) {
                if ($code !== '') {
                    $t->codes[$code[0]][$code] = true;
                }
            }
        }

        return $t;
    }

    /**
     * A text for one orphan that no other orphan of the year has. $from: which attempt to start from (0 the first time,
     * higher for "write another one").
     *
     * @return array{0:string, 1:string, 2:int} text, variant code, attempt used
     */
    public function make(string $kind, array $input, int $childId, int $year, int $from = 0): array
    {
        $best = null;
        for ($v = $from; $v < $from + 400; $v++) {
            [$text, $code] = ReportPhrases::make($kind, $input, crc32("{$childId}|{$year}|{$kind}|{$v}"));
            $newText = ! isset($this->texts[$kind][self::key($text)]);
            if ($newText && ! isset($this->codes[$code[0]][$code])) {
                $best = [$text, $code, $v];
                break;
            }
            if ($newText && $best === null && $v >= $from + 60) {   // every mix is taken already: different words are enough
                $best = [$text, $code, $v];
                break;
            }
        }
        $best ??= [$text, $code, $v];   // so many orphans that nothing is left: the last one written
        $this->take($kind, $best[0], $best[1]);

        return $best;
    }

    /** Marks a text as used (one saved for this orphan, so the next orphan avoids it too). */
    public function take(string $kind, ?string $text, ?string $code = null): void
    {
        if ($text) {
            $this->texts[$kind][self::key($text)] = true;
        }
        if ($code) {
            $this->codes[$code[0]][$code] = true;
        }
    }

    public function taken(string $kind, string $text): bool
    {
        return isset($this->texts[$kind][self::key($text)]);
    }

    private static function key(string $s): string
    {
        return md5(preg_replace('/\s+/u', ' ', trim($s)));
    }
}
