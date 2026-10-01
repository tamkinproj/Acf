<?php

namespace App\Modules\Aytam;

/**
 * Turns "Ahmad  bin Abdullah" and "AHMAD BIN ABDULLAH" and "Ahmád bin Abdullah" into the same comparable string, so
 * duplicate detection is not defeated by capitals, accents, punctuation or word order. Arabic is kept as Arabic
 * (vowel marks and elongation removed) - names are never transliterated or guessed.
 */
final class NameNormalizer
{
    public static function name(?string ...$parts): string
    {
        return implode(' ', self::tokens(...$parts));
    }

    /** @return list<string> sorted, de-duplicated words */
    public static function tokens(?string ...$parts): array
    {
        $text = mb_strtolower(trim(implode(' ', array_filter($parts, fn ($p) => $p !== null && trim($p) !== ''))));
        if (class_exists(\Normalizer::class)) {
            $text = \Normalizer::normalize($text, \Normalizer::FORM_D) ?: $text;
        }
        $text = preg_replace('/\p{Mn}+/u', '', $text) ?? $text;          // accents, Arabic vowel marks
        $text = str_replace(['ـ', 'أ', 'إ', 'آ', 'ى', 'ة'], ['', 'ا', 'ا', 'ا', 'ي', 'ه'], $text);   // tatweel and common Arabic letter variants
        $text = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text) ?? $text;   // punctuation -> space
        $words = array_values(array_unique(array_filter(explode(' ', $text), fn ($w) => $w !== '')));
        sort($words);

        return $words;
    }

    /** 0..1 similarity of two names: share of words in common, with a small tolerance for typos in single words. */
    public static function similarity(string $a, string $b): float
    {
        $x = array_values(array_filter(explode(' ', $a)));
        $y = array_values(array_filter(explode(' ', $b)));
        if ($x === [] || $y === []) {
            return 0.0;
        }
        $matched = 0.0;
        $pool = $y;
        foreach ($x as $word) {
            $best = 0.0;
            $bestKey = null;
            foreach ($pool as $key => $other) {
                $score = $word === $other ? 1.0 : self::wordScore($word, $other);
                if ($score > $best) {
                    [$best, $bestKey] = [$score, $key];
                }
            }
            if ($bestKey !== null && $best >= 0.8) {
                $matched += $best;
                unset($pool[$bestKey]);
            }
        }

        $dice = (2 * $matched) / (count($x) + count($y));
        // One name entirely inside the other ("Ahmad Abdullah" / "Ahmad bin Abdullah") is a strong match, not a partial one.
        $shorter = min(count($x), count($y));
        $containment = $shorter >= 2 ? 0.95 * ($matched / $shorter) : 0.0;

        return min(1.0, max($dice, $containment));
    }

    private static function wordScore(string $a, string $b): float
    {
        $len = max(mb_strlen($a), mb_strlen($b));
        if ($len < 4) {
            return 0.0;   // short words must match exactly: one typo changes the name
        }
        $distance = levenshtein(self::ascii($a), self::ascii($b));

        return max(0.0, 1 - $distance / $len);
    }

    /** levenshtein() works on bytes; map each character to a stable single byte-safe token. */
    private static function ascii(string $s): string
    {
        return mb_check_encoding($s, 'ASCII') ? $s : implode('', array_map(fn ($c) => chr(33 + (mb_ord($c) % 90)), mb_str_split($s)));
    }
}
