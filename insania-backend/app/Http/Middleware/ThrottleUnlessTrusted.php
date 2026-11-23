<?php

namespace App\Http\Middleware;

use App\Support\TrustedDevices;
use Closure;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

/**
 * Every request limit in the app ("throttle:..."), except for a trusted device: a phone or computer its owner chose to trust after
 * signing in is never slowed down, whatever the System Admin sets the limits to. The limits on wrong passwords (account lock-outs,
 * confirming a change with the password) are separate and always apply.
 */
class ThrottleUnlessTrusted extends ThrottleRequests
{
    public function handle($request, Closure $next, $maxAttempts = 60, $decayMinutes = 1, $prefix = ''): Response
    {
        if (TrustedDevices::trusted($request)) {
            return $next($request);
        }

        // passed on exactly as received: Laravel tells a named limit ("api") from a number by how many arguments it gets
        return parent::handle(...func_get_args());
    }
}
