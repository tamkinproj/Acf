<?php

namespace App\Support;

/** Usernames: lowercase letters, digits, dot, underscore and hyphen; 3 to 30 characters; start with a letter or digit. */
final class Usernames
{
    public const PATTERN = '/^[a-z0-9][a-z0-9._-]{2,29}$/';

    public static function valid(string $u): bool
    {
        return (bool) preg_match(self::PATTERN, $u);
    }

    public static function normalise(?string $u): string
    {
        return strtolower(trim((string) $u));
    }

    /** A free username built from a name or an email's first part ("Amina Hassan" -> "amina.hassan"). $taken says whether a name is used. */
    public static function suggest(string $base, callable $taken): string
    {
        $b = strtolower((string) preg_replace('/[^a-z0-9._-]+/i', '.', trim(explode('@', $base)[0])));
        $b = trim((string) preg_replace('/[._-]{2,}/', '.', $b), '._-');
        $b = substr($b, 0, 24);
        if (strlen($b) < 3) {
            $b = str_pad($b, 4, 'user');
        }
        $candidate = $b;
        for ($i = 2; $taken($candidate); $i++) {
            $candidate = $b.$i;
        }

        return $candidate;
    }
}
