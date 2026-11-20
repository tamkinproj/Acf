<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use App\Support\PermissionCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fine-grained gate. Usage: ->middleware(['auth:sanctum', 'permission:children.view'])
 * Several keys mean "any of these". Fails closed: unknown keys, inactive accounts and anonymous callers are refused.
 */
class RequirePermission
{
    public function handle(Request $request, Closure $next, string ...$any): Response
    {
        $user = $request->user();

        if ($user === null) {
            return ApiResponse::error('UNAUTHENTICATED', 'Authentication required.', 401);
        }

        $held = PermissionCatalog::forUser($user);

        foreach ($any as $key) {
            if (PermissionCatalog::isValidPermissionKey($key) && in_array($key, $held, true)) {
                return $next($request);
            }
        }

        return ApiResponse::error('FORBIDDEN', 'This account is not allowed to do that.', 403);
    }
}
