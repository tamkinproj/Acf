<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\LoginThrottle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    // A real-looking bcrypt hash, so an unknown email takes as long to reject as a wrong password.
    private const DUMMY_HASH = '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:190'],
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

    public function preferences(Request $request)
    {
        return ApiResponse::ok($request->user()->toApi()['preferences']);
    }

    public function updatePreferences(Request $request)
    {
        $data = $request->validate([
            'reminders' => ['sometimes', 'boolean'],
            'show_amounts' => ['sometimes', 'boolean'],
        ]);

        $user = $request->user();
        $user->preferences = array_merge($user->toApi()['preferences'], $data);
        $user->save();

        return ApiResponse::updated($user->preferences);
    }
}
