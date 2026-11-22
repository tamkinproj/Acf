<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\SecurityLimits;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The technical side. Everything here is about the system itself: nothing returned or accepted is a child's or a family's data. */
class SystemController extends Controller
{
    public function health()
    {
        $db = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $db = false;
        }

        return ApiResponse::ok([
            'status' => $db ? 'ok' : 'degraded',
            'checks' => ['database' => $db, 'storage_writable' => is_writable(storage_path())],
            'versions' => ['php' => PHP_VERSION, 'laravel' => app()->version()],
            'environment' => app()->environment(),
            'time' => now()->toIso8601String(),
        ]);
    }

    /** A short list of the safety settings of this server: green or needs attention. No personal data. */
    public function security()
    {
        $env = base_path('.env');
        $inWeb = is_file($env) && str_starts_with((string) realpath($env), (string) realpath(public_path()));
        $checks = [
            ['key' => 'https', 'ok' => str_starts_with((string) config('app.url'), 'https://')],
            ['key' => 'debug_off', 'ok' => ! config('app.debug')],
            ['key' => 'production', 'ok' => app()->environment('production')],
            ['key' => 'app_key', 'ok' => (string) config('app.key') !== ''],
            ['key' => 'env_private', 'ok' => ! $inWeb],
            ['key' => 'cors_own_site', 'ok' => ! in_array('*', (array) config('cors.allowed_origins'), true)],
            ['key' => 'update_key', 'ok' => trim((string) config('updates.public_key')) !== ''],
            ['key' => 'no_demo_accounts', 'ok' => ! \App\Models\User::withoutGlobalScopes()->where('email', 'like', '%.test')->exists()],
            ['key' => 'strong_limits', 'ok' => \App\Support\SecurityLimits::get('idle_minutes_privileged') <= 120 && \App\Support\SecurityLimits::get('max_hours_privileged') <= 12],
        ];

        return ApiResponse::ok([
            'checks' => $checks,
            'limits' => ['idle_minutes' => ['privileged' => SecurityLimits::get('idle_minutes_privileged'), 'default' => SecurityLimits::get('idle_minutes_default')], 'max_hours' => ['privileged' => SecurityLimits::get('max_hours_privileged'), 'default' => SecurityLimits::get('max_hours_default')], 'lockout' => ['attempts' => SecurityLimits::get('lockout_attempts'), 'minutes' => SecurityLimits::get('lockout_minutes')]],
            'attention' => count(array_filter($checks, fn ($c) => ! $c['ok'])),
        ]);
    }

    /** The limits and the lock-outs the System Admin can change (each within a safe range). */
    public function limits()
    {
        return ApiResponse::ok(['values' => SecurityLimits::all(), 'ranges' => collect(SecurityLimits::FIELDS)->map(fn ($f) => ['default' => $f[0], 'min' => $f[1], 'max' => $f[2]])]);
    }

    public function updateLimits(Request $request)
    {
        $data = $request->validate(['admin_password' => ['required', 'string', 'max:200']]);
        if (! Hash::check($data['admin_password'], $request->user()->password)) {
            Audit::record('limits.change_denied', $request);

            return ApiResponse::error('WRONG_PASSWORD', 'Your own password is not right.', 422, ['admin_password' => ['Your own password is not right.']]);
        }
        $values = $request->validate(collect(SecurityLimits::FIELDS)->mapWithKeys(fn ($f, $k) => [$k => ['sometimes', 'integer', 'min:'.$f[1], 'max:'.$f[2]]])->all());
        $before = SecurityLimits::all();
        $after = SecurityLimits::save((int) $request->user()->foundation_id, $values);
        Audit::record('limits.changed', $request, null, null, ['changed' => array_keys(array_diff_assoc($after, $before))]);

        return ApiResponse::ok(['values' => $after]);
    }

    public function settings()
    {
        return ApiResponse::ok(Setting::pluck('value', 'key'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'org_name' => ['sometimes', 'string', 'max:150'],
            'currency' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
            'reminders_default' => ['sometimes', 'boolean'],
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key, 'foundation_id' => $request->user()->foundation_id], ['value' => is_bool($value) ? (string) (int) $value : $value]);
        }

        Audit::record('settings.updated', $request, null, null, ['keys' => array_keys($data)]);

        return ApiResponse::updated(Setting::pluck('value', 'key'));
    }
}
