<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/** An account made for someone else (an orphan) must choose its own password before anything else works. */
class PasswordChangeRequired
{
    private const ALLOWED = ['api/me', 'api/me/password', 'api/logout'];

    public function handle(Request $request, Closure $next)
    {
        $u = $request->user();
        if ($u && $u->must_change_password && ! in_array(trim($request->path(), '/'), self::ALLOWED, true)) {
            return ApiResponse::error('PASSWORD_CHANGE_REQUIRED', 'Choose your own password first.', 403);
        }

        return $next($request);
    }
}
