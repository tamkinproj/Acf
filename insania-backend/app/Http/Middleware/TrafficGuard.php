<?php

namespace App\Http\Middleware;

use App\Install\InstallState;
use App\Services\Traffic;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The first thing every request meets: a blocked address is refused here, before sign-in or anything else.
 * After the answer is sent, the request is counted for the System Admin's Security page (Traffic::record).
 * Before the system is installed it does nothing (there is no database yet), and it never breaks a request.
 */
class TrafficGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        if (InstallState::installed()) {
            try {
                if (Traffic::refused($request)) {
                    $request->attributes->set('traffic_blocked', true);

                    return ApiResponse::error('BLOCKED', 'Access from this address is blocked. If this is a mistake, contact the System Admin.', 403);
                }
            } catch (\Throwable) {
                // a problem reading the block list must not lock everyone out
            }
        }

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if (! InstallState::installed()) {
            return;
        }
        try {
            Traffic::record($request, $response);
        } catch (\Throwable) {
            // counting is best effort
        }
    }
}
