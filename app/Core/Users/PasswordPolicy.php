<?php

namespace App\Core\Users;

use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class PasswordPolicy
{
    /** Strength only checks local rules - the haveibeenpwned check is deliberately off: this system must work offline. */
    public static function rule(): Password
    {
        return Password::min((int) config('foundation.auth.password_min_length'))->letters()->numbers();
    }

    /** One-time password for admin-created / reset accounts. The user must change it on first login. */
    public static function generateTemporary(): string
    {
        do {
            $password = Str::password(14, symbols: false);
        } while (! preg_match('/[A-Za-z]/', $password) || ! preg_match('/\d/', $password));

        return $password;
    }
}
