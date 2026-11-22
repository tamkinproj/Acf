<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * The sign-in and request limits the System Admin can change from System (values are kept in the settings table).
 * Each value has a safe range: nobody can switch the protection off completely by mistake. The config file keeps the defaults.
 */
final class SecurityLimits
{
    public const KEY = 'security_limits';

    /** name => [default, min, max, label] */
    public const FIELDS = [
        'attempts_per_minute' => [5, 3, 30],          // wrong passwords for one account from one address, per minute
        'lockout_attempts' => [10, 3, 50],            // wrong passwords for one account from any address before it is locked
        'lockout_minutes' => [15, 1, 1440],           // how long a locked account stays locked
        'login_per_minute' => [30, 5, 300],           // sign-in requests from one address per minute
        'api_per_minute_user' => [300, 60, 3000],     // requests per minute for a signed-in account
        'api_per_minute_ip' => [60, 20, 1000],        // requests per minute for an address that is not signed in
        'idle_minutes_privileged' => [60, 10, 480],   // a System, Security or President sign-in unused this long ends
        'idle_minutes_default' => [240, 10, 1440],    // any other sign-in unused this long ends
        'max_hours_privileged' => [8, 1, 24],         // a privileged sign-in never lasts longer than this
        'max_hours_default' => [12, 1, 72],           // any other sign-in never lasts longer than this
    ];

    /** @return array<string, int> */
    public static function all(): array
    {
        return Cache::remember('security-limits', 30, function () {
            $saved = (array) json_decode((string) Setting::withoutGlobalScopes()->where('key', self::KEY)->orderBy('id')->value('value'), true);
            $out = [];
            foreach (self::FIELDS as $name => [$default]) {
                $out[$name] = isset($saved[$name]) ? (int) $saved[$name] : $default;
            }

            return $out;
        });
    }

    public static function get(string $name): int
    {
        return self::all()[$name];
    }

    /** Checks and stores new values. Missing names keep their value. */
    public static function save(int $foundationId, array $values): array
    {
        $rules = [];
        foreach (self::FIELDS as $name => [, $min, $max]) {
            $rules[$name] = ['sometimes', 'integer', "min:$min", "max:$max"];
        }
        $validator = validator($values, $rules);
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        $merged = array_merge(self::all(), array_map('intval', array_intersect_key($values, self::FIELDS)));
        if ($merged['lockout_attempts'] < $merged['attempts_per_minute']) {
            // a lock-out must come after the per-minute limit, or the lock-out would never be the thing that stops a guesser
            throw ValidationException::withMessages(['lockout_attempts' => ['Must be at least the attempts per minute.']]);
        }
        Setting::withoutGlobalScopes()->updateOrCreate(['foundation_id' => $foundationId, 'key' => self::KEY], ['value' => json_encode($merged)]);
        Cache::forget('security-limits');

        return $merged;
    }
}
