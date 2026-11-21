<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * On top of the length and letter/number rules: no common passwords, no password that contains the person's own name, username
 * or email, no long runs of one character and no plain sequences (1234, abcd, qwer).
 */
class StrongPassword implements ValidationRule
{
    private const COMMON = [
        'password', 'passw0rd', 'p@ssw0rd', 'qwerty', 'letmein', 'welcome', 'admin', 'iloveyou', 'changeme', 'trustno1', 'monkey', 'dragon', 'football',
        'baseball', 'master', 'login', 'secret', 'sunshine', 'princess', 'whatever', 'abc123', 'bismillah', 'foundation', 'insania', 'alinsania',
        'orphan', 'aytam', 'pagadian', 'philippines', 'ramadan', 'demopass', 'temp1234', 'test1234', 'user1234',
    ];

    /** @param  list<string|null>  $avoid  texts of the person (name, email, username) the password must not contain */
    public function __construct(private array $avoid = [])
    {
    }

    /** @param  list<string|null>  $texts */
    public static function for(array $texts): self
    {
        return new self($texts);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $p = strtolower((string) $value);
        $flat = preg_replace('/[^a-z0-9]/', '', $p);
        foreach (self::COMMON as $w) {
            if (str_contains($flat, preg_replace('/[^a-z0-9]/', '', $w))) {
                $fail('This password is too easy to guess. Do not use common words or the foundation\'s name.');

                return;
            }
        }
        foreach ($this->avoid as $text) {
            foreach (preg_split('/[^a-z0-9]+/i', strtolower((string) $text)) ?: [] as $part) {
                if (strlen($part) >= 4 && str_contains($flat, $part)) {
                    $fail('The password must not contain your name, username or email.');

                    return;
                }
            }
        }
        if (preg_match('/(.)\1{3,}/', $p)) {
            $fail('Do not repeat the same character four times in a row.');

            return;
        }
        foreach (['0123', '1234', '2345', '3456', '4567', '5678', '6789', 'abcd', 'bcde', 'cdef', 'qwer', 'asdf', 'zxcv'] as $seq) {
            if (str_contains($p, $seq)) {
                $fail('Do not use plain sequences such as 1234 or abcd.');

                return;
            }
        }
    }
}
