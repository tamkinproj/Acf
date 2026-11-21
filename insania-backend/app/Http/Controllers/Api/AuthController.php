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
            // no control characters or line breaks in what is typed as the email or username
            'login' => ['required_without:email', 'nullable', 'string', 'max:190', 'regex:/^[^\x00-\x1F\x7F]*$/'],
            'email' => ['required_without:login', 'nullable', 'string', 'max:190', 'regex:/^[^\x00-\x1F\x7F]*$/'],   // older copies of the app still send "email"
            'password' => ['required', 'string', 'max:200'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);

        if ($blocked = LoginThrottle::blocked($request)) {
            return $blocked;
        }

        // an email or a username: they never overlap because a username has no @
        $id = trim((string) ($data['login'] ?? $data['email']));
        $user = str_contains($id, '@') ? User::where('email', $id)->first() : User::where('username', \App\Support\Usernames::normalise($id))->first();
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

    /** Anyone changes their own username (the name used to sign in, next to the email). */
    public function updateProfile(Request $request)
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:30']]);
        $u = \App\Support\Usernames::normalise($data['username']);
        $user = $request->user();
        if (! \App\Support\Usernames::valid($u)) {
            return ApiResponse::error('VALIDATION_ERROR', 'Use 3 to 30 letters, digits, dots, hyphens or underscores.', 422, ['username' => ['Use 3 to 30 letters, digits, dots, hyphens or underscores.']]);
        }
        if (User::where('username', $u)->where('id', '!=', $user->id)->exists()) {
            return ApiResponse::error('VALIDATION_ERROR', 'That username is taken.', 422, ['username' => ['That username is taken.']]);
        }
        $user->update(['username' => $u]);
        Audit::record('profile.username', $request, 'user', $user->id);

        return ApiResponse::updated($user->fresh()->toApi());
    }

    /** Anyone sets their own profile picture. Private file, re-saved and shrunk; other signed-in people of the foundation can see it. */
    public function setAvatar(Request $request)
    {
        $request->validate(['avatar' => ['required', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp']]);
        $user = $request->user();
        $saved = \App\Support\PrivateImages::store($request->file('avatar'), "avatars/{$user->foundation_id}", 360, 4096);
        if (is_string($saved)) {
            return ApiResponse::error('BAD_PHOTO', $saved, 422, ['avatar' => [$saved]]);
        }
        $old = $user->avatar_path;
        $user->forceFill(['avatar_path' => $saved['path'], 'avatar_v' => time()])->save();
        if ($old) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($old);
        }
        Audit::record('profile.avatar', $request, 'user', $user->id);

        return ApiResponse::updated($user->fresh()->toApi());
    }

    public function removeAvatar(Request $request)
    {
        $user = $request->user();
        if ($user->avatar_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($user->avatar_path);
        }
        $user->forceFill(['avatar_path' => null, 'avatar_v' => null])->save();

        return ApiResponse::updated($user->fresh()->toApi());
    }

    /** A person's picture, for any signed-in person of the same foundation (chat, accounts, the top bar). */
    public function avatar(Request $request, User $user)
    {
        if ($user->foundation_id !== $request->user()->foundation_id || ! $user->avatar_path || ! \Illuminate\Support\Facades\Storage::disk('local')->exists($user->avatar_path)) {
            return ApiResponse::notFound('No picture.');
        }

        return response(\Illuminate\Support\Facades\Storage::disk('local')->get($user->avatar_path), 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => 'private, max-age=86400', 'X-Content-Type-Options' => 'nosniff']);
    }
}
