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

    public static function blocked(object $request): ?\Illuminate\Http\JsonResponse
    {
        if (! RateLimiter::tooManyAttempts(self::key($request), self::MAX_ATTEMPTS)) {
            return null;
        }

        $seconds = RateLimiter::availableIn(self::key($request));

        return ApiResponse::error(
            'THROTTLED',
            'Too many login attempts. Try again in '.$seconds.' seconds.',
            429
        )->header('Retry-After', (string) $seconds);
    }

    public static function hit(object $request): void
    {
        RateLimiter::hit(self::key($request), self::DECAY_SECONDS);
    }

    public static function clear(object $request): void
    {
        RateLimiter::clear(self::key($request));
    }

    /** Identical response for unknown account and wrong password. */
    public static function genericFailure(): \Illuminate\Http\JsonResponse
    {
        return ApiResponse::error('INVALID_CREDENTIALS', 'The provided credentials are incorrect.', 401);
    }
}
