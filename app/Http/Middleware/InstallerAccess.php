<?php

namespace App\Http\Middleware;

use App\Install\InstallState;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Protects the installer from a "first visitor wins" takeover on a public URL.
 *
 * Proof of access = knowing the one-time token stored in storage/app/install/token, which only
 * someone with file access to the server (the person deploying it) can read. Requests coming
 * directly from the loopback interface with no proxy headers (a standalone/local install) skip it.
 * The token is deleted when installation completes.
 */
class InstallerAccess
{
    public function __construct(private InstallState $state) {}

    public function handle(Request $request, Closure $next, string $area = 'install'): Response
    {
        if ($this->isDirectLoopback($request) || $request->session()->get('installer_authorized') === true) {
            return $next($request);
        }

        if ($request->is("{$area}/token")) {
            return $next($request);
        }

        return redirect("/{$area}/token");
    }

    /** Verify a submitted token. Throttled so it cannot be brute-forced. */
    public function attempt(Request $request, string $submitted): bool
    {
        $key = 'install-token:'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return false;
        }
        $ok = hash_equals($this->state->token(), trim($submitted));
        if ($ok) {
            RateLimiter::clear($key);
            $request->session()->regenerate();
            $request->session()->put('installer_authorized', true);
        } else {
            RateLimiter::hit($key, 300);
        }

        return $ok;
    }

    public function isDirectLoopback(Request $request): bool
    {
        $remote = (string) $request->server('REMOTE_ADDR');
        $proxied = $request->headers->has('X-Forwarded-For') || $request->headers->has('Forwarded') || $request->headers->has('X-Real-IP');

        return in_array($remote, ['127.0.0.1', '::1'], true) && ! $proxied && ! config('foundation.installer_require_token');
    }
}
