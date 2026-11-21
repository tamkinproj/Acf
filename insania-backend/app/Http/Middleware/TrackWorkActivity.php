<?php

namespace App\Http\Middleware;

use App\Services\WorkTracker;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Notes that a signed-in staff member used the app. Runs after the answer is sent and can never break a request. */
class TrackWorkActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $user = $request->user();
            if (! $user || ! in_array($user->role, ['staff', 'president_admin'], true) || ! $user->foundation_id || ! WorkTracker::counts($request, $response->getStatusCode())) {
                return;
            }
            WorkTracker::record($user->id, (int) $user->foundation_id, $request->path(), ! $request->isMethod('GET') && ! $request->isMethod('HEAD'));
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
