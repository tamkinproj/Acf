<?php

namespace App\Http\Middleware;

use App\Install\InstallState;
use App\Install\InstallStatus;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate in front of everything. Decides from FILES (not the database) whether
 * the system is installed, so it works before any database exists.
 *
 *  not installed / in progress / error -> only /install/* is reachable
 *  installed                           -> /install/* answers "already installed"
 *  corrupt                             -> every route shows the recovery screen
 *
 * While not installed it also pins session/cache to the file driver and
 * supplies a temporary APP_KEY, because the configured defaults (database
 * sessions, no key) cannot work yet.
 */
class EnsureInstalled
{
    public function __construct(private InstallState $state) {}

    public function handle(Request $request, Closure $next): Response
    {
        $status = $this->state->status();
        $isInstaller = $request->is('install', 'install/*');
        $wantsJson = $request->is('api/*') || $request->expectsJson();

        if ($status === InstallStatus::Corrupt) {
            return $wantsJson
                ? ApiResponse::error('INSTALL_CORRUPT', 'The installation state is damaged. See the recovery screen.', 503)
                : response()->view('install.recovery', ['reason' => $this->state->corruptReason()], 503);
        }

        if ($status === InstallStatus::Installed) {
            if ($isInstaller) {
                return $wantsJson
                    ? ApiResponse::error('ALREADY_INSTALLED', 'This system is already installed.', 403)
                    : response()->view('install.already', [], 403);
            }

            return $next($request);
        }

        // Not installed yet.
        // The shipped defaults point sessions/cache/queue at the (not yet existing) database.
        foreach (['session.driver' => ['database', 'file'], 'cache.default' => ['database', 'file'], 'queue.default' => ['database', 'sync']] as $key => [$bad, $safe]) {
            if (config($key) === $bad) {
                config([$key => $safe]);
            }
        }
        if (! config('app.key')) {
            config(['app.key' => $this->state->bootstrapKey()]);
        }

        if ($isInstaller || $request->is('up')) {
            return $next($request);
        }

        return $wantsJson
            ? ApiResponse::error('NOT_INSTALLED', 'The system has not been installed yet.', 503)
            : redirect('/install');
    }
}
