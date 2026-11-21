<?php

namespace App\Support;

use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Keyed login throttle + enumeration guard (Phase F1).
 *
 * In the login controller:
 *
 *   if ($blocked = LoginThrottle::blocked($request)) { return $blocked; }
 *   if (! Auth::attempt($credentials)) {
 *       LoginThrottle::hit($request);
 *       return LoginThrottle::genericFailure();   // never reveal which field
 *   }
 *   LoginThrottle::clear($request);
 */
final class LoginThrottle
{
    public const MAX_ATTEMPTS = 5;
    public const DECAY_SECONDS = 60;

    public static function key(object $request): string
    {
        $identifier = Str::lower((string) ($request->input('login') ?? $request->input('email') ?? $request->input('username') ?? ''));

        return 'login:'.sha1($identifier.'|'.$request->ip());
    }

    /** One account, from ANY address: a guessing attack spread over many phones is stopped too. */
    public static function accountKey(object $request): string
    {
        $identifier = Str::lower(trim((string) ($request->input('login') ?? $request->input('email') ?? $request->input('username') ?? '')));

        return 'login-acct:'.sha1($identifier);
    }

    public static function blocked(object $request): ?\Illuminate\Http\JsonResponse
    {
        $max = (int) config('security.lockout.attempts', 10);
        foreach ([[self::key($request), self::MAX_ATTEMPTS], [self::accountKey($request), $max]] as [$key, $limit]) {
            if (RateLimiter::tooManyAttempts($key, $limit)) {
                $seconds = RateLimiter::availableIn($key);

                return ApiResponse::error(
                    'THROTTLED',
                    'Too many login attempts. Try again in '.$seconds.' seconds.',
                    429
                )->header('Retry-After', (string) $seconds);
            }
        }

        return null;
    }

    /** True when this failure is the one that locks the account (so it can be written to the audit log once). */
    public static function hit(object $request): bool
    {
        RateLimiter::hit(self::key($request), self::DECAY_SECONDS);
        RateLimiter::hit(self::accountKey($request), (int) config('security.lockout.minutes', 15) * 60);

        return RateLimiter::attempts(self::accountKey($request)) === (int) config('security.lockout.attempts', 10);
    }

    public static function clear(object $request): void
    {
        RateLimiter::clear(self::key($request));
        RateLimiter::clear(self::accountKey($request));
    }

    /** Identical response for unknown account and wrong password. */
    public static function genericFailure(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::error('INVALID_CREDENTIALS', 'The provided credentials are incorrect.', 401);
    }
}
