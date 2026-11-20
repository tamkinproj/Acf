<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Adds the standard set of defensive response headers to every request -
 * mobile API calls and any remaining web/browser pages alike. None of
 * these change app behavior for the React Native client (it doesn't run
 * in a browser, so most of these are inert to it) - they matter for
 * anyone who opens the site directly in a browser.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-XSS-Protection', '0');
        // Tell browsers to only ever load this site over HTTPS for the next
        // year, including subdomains - only meaningful once you've
        // confirmed every page loads over HTTPS already (it does - the app
        // only runs over HTTPS).
        $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');

        return $response;
    }
}
