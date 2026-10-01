<?php

namespace App\Http\Controllers\Api;

use App\Core\Audit\Auditor;
use App\Core\Settings\SettingsCatalog;
use App\Core\Users\PasswordPolicy;
use App\Core\Users\SessionRevoker;
use App\Http\Controllers\Controller;
use App\Models\Foundation;
use App\Models\User;
use App\Support\ApiResponse;
use App\Sync\DeviceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /** A valid bcrypt hash of a random string: unknown accounts still pay for a full hash check (no timing oracle). */
    private const DUMMY_HASH = '$2y$12$niVXSkFKkJtXV/B6kNykrOrcGB0H/frG1M8aQlpmCU67U435Wa.cC';

    public function __construct(private Auditor $auditor, private SessionRevoker $sessions) {}

    /** Primes the CSRF cookie/token for SPA-style clients. */
    public function csrf(Request $request): JsonResponse
    {
        return ApiResponse::ok(['csrf_token' => $request->session()->token()]);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'string', 'max:190'], 'password' => ['required', 'string', 'max:200']]);

        // Throttle per (email, IP): slows guessing without letting one attacker lock out the real user from elsewhere.
        $key = 'login:'.sha1(Str::lower($data['email']).'|'.$request->ip());
        $max = (int) config('foundation.auth.login_max_attempts');
        if (RateLimiter::tooManyAttempts($key, $max)) {
            $wait = RateLimiter::availableIn($key);

            return ApiResponse::error('THROTTLED', "Too many login attempts. Try again in {$wait} seconds.", 429)->header('Retry-After', (string) $wait);
        }

        $user = User::query()->where('email', Str::lower($data['email']))->first();
        // One generic failure for unknown email, wrong password and disabled account, and always run a hash check.
        $valid = Hash::check($data['password'], $user->password ?? self::DUMMY_HASH);

        if (! $user || ! $valid || ! $user->isActive()) {
            RateLimiter::hit($key, (int) config('foundation.auth.login_decay_seconds'));
            $this->auditor->record('auth.login_failed', 'Failed login attempt', 'users', $user?->getKey(), null, ['email' => Str::limit($data['email'], 120)], $user);

            return ApiResponse::error('INVALID_CREDENTIALS', 'The provided credentials are incorrect.', 401);
        }

        RateLimiter::clear($key);
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_device_id' => app(DeviceContext::class)->deviceId()])->save();
        $this->auditor->record('auth.login', 'Signed in', 'users', $user->getKey(), actor: $user);

        return ApiResponse::ok($this->me($user));
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user) {
            $this->auditor->record('auth.logout', 'Signed out', 'users', $user->getKey(), actor: $user);
        }
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ApiResponse::ok(null);
    }

    public function show(Request $request): JsonResponse
    {
        return ApiResponse::ok($this->me($request->user()));
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'locale' => ['nullable', Rule::in(SettingsCatalog::LOCALES)],
        ]);
        $user->update($data);

        return ApiResponse::ok($this->me($user->fresh()));
    }

    public function changePassword(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', PasswordPolicy::rule()],
        ]);

        if (! Hash::check($data['current_password'], $user->password)) {
            return ApiResponse::error('INVALID_CREDENTIALS', 'The current password is incorrect.', 422, ['current_password' => ['The current password is incorrect.']]);
        }
        if (Str::lower($data['password']) === Str::lower($user->email)) {
            return ApiResponse::error('WEAK_PASSWORD', 'The password must not be your email address.', 422);
        }

        $user->forceFill(['password' => $data['password'], 'must_change_password' => false])->save();
        $this->sessions->revokeAll($user->getKey(), $request->session()->getId());
        $request->session()->regenerate();
        $this->auditor->record('auth.password_changed', 'Changed own password', 'users', $user->getKey(), actor: $user);

        return ApiResponse::ok(null);
    }

    public function sessions(Request $request): JsonResponse
    {
        return ApiResponse::ok($this->sessions->list($request->user()->getKey(), $request->session()->getId()));
    }

    public function revokeSession(Request $request, string $handle): JsonResponse
    {
        return $this->sessions->revokeByHandle($request->user()->getKey(), $handle, $request->session()->getId())
            ? ApiResponse::ok(null)
            : ApiResponse::error('NOT_FOUND', 'Session not found.', 404);
    }

    /** Everything a client needs right after sign-in (also cached by the offline shell). */
    private function me(User $user): array
    {
        $user->loadMissing('role');
        $foundation = Foundation::current();

        return [
            'user' => [
                'id' => $user->getKey(), 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone,
                'locale' => $user->locale, 'status' => $user->status, 'must_change_password' => $user->must_change_password,
                'role' => ['id' => $user->role_id, 'key' => $user->role?->key, 'name' => $user->role?->name],
            ],
            'permissions' => $user->permissionKeys(),
            'foundation' => $foundation ? ['id' => $foundation->getKey(), 'name' => $foundation->name, 'short_name' => $foundation->short_name, 'logo_hash' => $foundation->logo_hash] : null,
            'session' => ['idle_lock_minutes' => (int) app(\App\Core\Settings\SettingsService::class)->get('security.idle_lock_minutes')],
        ];
    }
}
