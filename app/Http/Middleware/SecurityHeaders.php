<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Defensive headers on every response (pattern from MuslimEdu, tightened):
 * CSP is sent as a real header (MuslimEdu could only use <meta>, which cannot
 * carry frame-ancestors), HSTS only over HTTPS, API responses are never cached.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=()');
        $h->set('Content-Security-Policy',
            "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; "
            ."font-src 'self'; connect-src 'self'; worker-src 'self'; manifest-src 'self'; "
            ."frame-ancestors 'none'; base-uri 'none'; form-action 'self'");
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($request->is('api/*')) {
            $h->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
