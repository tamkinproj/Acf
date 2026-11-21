<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\LoginThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    // A real-looking bcrypt hash, so an unknown email takes as long to reject as a wrong password.
    private const DUMMY_HASH = '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email:rfc,filter', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        if ($blocked = LoginThrottle::blocked($request)) {
            return $blocked;
        }

        $user = User::where('email', $data['email'])->first();
        $ok = Hash::check($data['password'], $user?->password ?? self::DUMMY_HASH) && $user && $user->active;

        if (! $ok) {
            LoginThrottle::hit($request);
            Audit::record('login.failed', $request, 'user', $user?->id, [], $user?->id, $user?->foundation_id);

            return LoginThrottle::genericFailure();
        }

        LoginThrottle::clear($request);
        $token = $user->createToken($data['device_name'] ?? 'staff-app')->plainTextToken;
        Audit::record('login', $request, 'user', $user->id, [], $user->id, $user->foundation_id);

        return ApiResponse::ok(['token' => $token, 'user' => $user->toApi()]);
    }

    public function me(Request $request)
    {
        return ApiResponse::ok($request->user()->toApi());
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return ApiResponse::deleted('Signed out.');
    }

    /** Anyone changes their own password. Needs the current one; every other device is signed out, this one stays. */
    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'max:200', Password::min(12)->mixedCase()->numbers()],
        ]);
        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            Audit::record('password.change_failed', $request, 'user', $user->id);

            return ApiResponse::error('WRONG_PASSWORD', 'The current password is not right.', 422, ['current_password' => ['The current password is not right.']]);
        }
        if (Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('VALIDATION_ERROR', 'Choose a password you have not used just now.', 422, ['password' => ['Choose a password you have not used just now.']]);
        }

        $user->update(['password' => $data['password']]);
        $user->tokens()->where('id', '!=', $user->currentAccessToken()?->id)->delete();
        Audit::record('password.changed', $request, 'user', $user->id);

        return ApiResponse::deleted('Password changed. Other devices were signed out.');
    }

    public function preferences(Request $request)
    {
        return ApiResponse::ok($request->user()->toApi()['preferences']);
    }

    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'reminders' => ['sometimes', 'boolean'],
            'show_amounts' => ['sometimes', 'boolean'],
            'setup_seen' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->preferences = array_merge($user->toApi()['preferences'], $data);
        $user->save();

        return ApiResponse::updated($user->preferences);
    }
}
