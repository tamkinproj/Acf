<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/** Sensitive changes ask for the signed-in person's own password again (a phone left unlocked cannot make a back door). */
final class StepUp
{
    public static function check(Request $request): ?JsonResponse
    {
        $actor = $request->user();
        $key = 'stepup:'.$actor->id;
        if (RateLimiter::tooManyAttempts($key, (int) config('security.step_up.attempts', 5))) {
            return ApiResponse::error('THROTTLED', 'Too many wrong passwords. Try again in '.RateLimiter::availableIn($key).' seconds.', 429);
        }
        $given = (string) $request->input('confirm_password', '');
        if ($given === '' || ! Hash::check($given, $actor->password)) {
            RateLimiter::hit($key, (int) config('security.step_up.minutes', 5) * 60);
            Audit::record('stepup.failed', $request, 'user', $actor->id);

            return ApiResponse::error('WRONG_PASSWORD', 'Type your own password to confirm this change.', 422, ['confirm_password' => ['Type your own password to confirm this change.']]);
        }
        RateLimiter::clear($key);

        return null;
    }
}
