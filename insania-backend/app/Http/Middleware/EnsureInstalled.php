<?php

namespace App\Http\Middleware;

use App\Install\InstallState;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

/** Until the web installer has finished, the API answers one clear JSON message instead of failing on a missing database. */
class EnsureInstalled
{
    public function handle(Request $request, Closure $next)
    {
        if (InstallState::installed() || $request->is('install', 'install/*', 'upgrade', 'upgrade/*', 'api/ping')) {
            return $next($request);
        }

        return ApiResponse::error('NOT_INSTALLED', 'The system is not installed yet. Open /install on the server.', 503);
    }
}
