<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Runs on every authenticated request: a disabled/removed user loses access immediately
 * (not at next login), and a user flagged must_change_password can do nothing but change it.
 */
class EnsureAccountUsable
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }
        if (! $user->isActive()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return ApiResponse::error('ACCOUNT_DISABLED', 'This account is disabled.', 401);
        }
        if ($user->must_change_password && ! $request->is('api/auth/password', 'api/auth/logout', 'api/auth/me')) {
            return ApiResponse::error('PASSWORD_CHANGE_REQUIRED', 'You must change your password before continuing.', 403);
        }

        return $next($request);
    }
}
