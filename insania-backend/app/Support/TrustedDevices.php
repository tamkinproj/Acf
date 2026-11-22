<?php

namespace App\Support;

use App\Models\TrustedDevice;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Trusted devices. At sign-in a person can choose "Trust this device": the app then keeps a long random secret and sends it with every
 * request (header X-Device-Token). The server keeps only its hash. A request that carries a valid secret is not slowed down by the
 * request limits, so a trusted phone does not run into "too many requests". The secret is useless without the password the person
 * typed when trusting the device, expires after 90 days, and is removed when the password changes, the account is switched off,
 * or the person removes the device. The limits on wrong passwords (account lock-outs) always apply.
 */
final class TrustedDevices
{
    public const HEADER = 'X-Device-Token';
    public const DAYS = 90;
    public const MAX_PER_USER = 10;

    private static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    private static function tokenOf(Request $request): ?string
    {
        $t = trim((string) $request->headers->get(self::HEADER, ''));

        return preg_match('/^[A-Za-z0-9]{40,80}$/', $t) ? $t : null;
    }

    /** The device row for this request, if it carries a valid secret (and, when a user is given, that user's). Remembered for a minute. */
    public static function device(Request $request, ?int $userId = null): ?TrustedDevice
    {
        $token = self::tokenOf($request);
        if (! $token) {
            return null;
        }
        $h = self::hash($token);
        $row = Cache::remember('trusted-device:'.$h, 60, fn () => TrustedDevice::where('token_hash', $h)->where('expires_at', '>', now())->first() ?: false);
        if (! $row || ($userId !== null && (int) $row->user_id !== $userId)) {
            return null;
        }

        return $row;
    }

    /** True when the request comes from a device its signed-in account trusts. */
    public static function trusted(Request $request): bool
    {
        $u = $request->user();

        return $u ? self::device($request, (int) $u->id) !== null : self::device($request) !== null;
    }

    /** Trusts the device the request comes from. Returns the secret, shown to the app once. */
    public static function create(Request $request, User $user, string $name): string
    {
        $token = Str::random(48);
        TrustedDevice::create(['user_id' => $user->id, 'token_hash' => self::hash($token), 'name' => mb_substr($name, 0, 80), 'last_ip' => $request->ip(),
            'last_used_at' => now(), 'expires_at' => now()->addDays(self::DAYS)]);
        // keep the newest few only
        $keep = TrustedDevice::where('user_id', $user->id)->orderByDesc('id')->limit(self::MAX_PER_USER)->pluck('id');
        foreach (TrustedDevice::where('user_id', $user->id)->whereNotIn('id', $keep)->get() as $o) {
            self::forget($o);
        }

        return $token;
    }

    /** A trusted device that signs in again stays trusted for another 90 days. */
    public static function renew(Request $request, User $user): void
    {
        $d = self::device($request, (int) $user->id);
        if ($d) {
            TrustedDevice::where('id', $d->id)->update(['last_used_at' => now(), 'last_ip' => $request->ip(), 'expires_at' => now()->addDays(self::DAYS)]);
            Cache::forget('trusted-device:'.$d->token_hash);
        }
    }

    public static function forget(TrustedDevice $d): void
    {
        Cache::forget('trusted-device:'.$d->token_hash);
        $d->delete();
    }

    /** Every trusted device of an account (password changed, account switched off, sessions ended). */
    public static function forgetAll(int $userId): void
    {
        foreach (TrustedDevice::where('user_id', $userId)->get() as $d) {
            self::forget($d);
        }
    }
}
