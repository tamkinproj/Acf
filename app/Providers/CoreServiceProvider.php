<?php

namespace App\Providers;

use App\Core\Audit\Auditor;
use App\Core\Locations\LocationService;
use App\Core\Settings\SettingsService;
use App\Install\InstallState;
use App\Sync\ChangeContext;
use App\Sync\ChangeFeed;
use App\Sync\DeviceContext;
use App\Sync\Entities\CoreEntities;
use App\Modules\Aytam\AytamModule;
use App\Programs\ProgramModules;
use App\Sync\SyncRegistry;
use App\Tenancy\FoundationUserProvider;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Cache\RateLimiter;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
        $this->app->singleton(InstallState::class);
        $this->app->singleton(DeviceContext::class);
        $this->app->singleton(ChangeContext::class);
        $this->app->singleton(SyncRegistry::class);
        $this->app->singleton(ChangeFeed::class);
        $this->app->singleton(Auditor::class);
        $this->app->singleton(SettingsService::class);
        $this->app->singleton(LocationService::class);
    }

    public function boot(): void
    {
        // Lazily: building the rate limiter binds it to the cache store, and on a fresh deployment that store
        // (database) does not exist until the installer gate has switched to the file store for this request.
        $this->callAfterResolving(RateLimiter::class, fn (RateLimiter $limiter) => $this->rateLimiters($limiter));
        CoreEntities::register($this->app->make(SyncRegistry::class));
        ProgramModules::register(new AytamModule);
        Auth::provider('foundation-eloquent', fn ($app, array $config) => new FoundationUserProvider($app['hash'], $config['model']));

        // The login cookie must be scoped to the folder the site is ACTUALLY served from. A stored value can be wrong
        // (typo in the URL entered during setup, site moved or renamed), and then the browser never sends the cookie back.
        if (! $this->app->runningInConsole()) {
            config(['session.path' => self::sessionPathFor($this->app->make('request'))]);
        }
    }

    /** "/acr" for https://example.org/acr/..., "/" for a domain root. Derived from the request, never from stored config. */
    public static function sessionPathFor(Request $request): string
    {
        $base = '/'.trim($request->getBasePath(), '/');

        return preg_match('#^/[A-Za-z0-9/_\-.~%]*$#', $base) ? $base : '/';
    }

    /**
     * Named limiters, each with its OWN counter. (The numeric `throttle:N,1` shorthand shares one counter
     * per client across every route that uses it, so unrelated screens would eat each other's budget.)
     */
    private function rateLimiters(\Illuminate\Cache\RateLimiter $limiter): void
    {
        $byIp = fn (Request $r) => $r->ip();
        $byActor = fn (Request $r) => $r->user()?->getAuthIdentifier() ?? $r->ip();
        $byDevice = fn (Request $r) => $r->attributes->get('device')?->getKey() ?? $byActor($r);

        $limiter->for('installer-token', fn (Request $r) => Limit::perMinute(20)->by($byIp($r)));
        $limiter->for('installer-database', fn (Request $r) => Limit::perMinute(40)->by($byIp($r)));
        $limiter->for('installer-run', fn (Request $r) => Limit::perMinute(10)->by($byIp($r)));
        $limiter->for('login', fn (Request $r) => Limit::perMinute(30)->by($byIp($r)));   // per-account limit lives in AuthController
        $limiter->for('password', fn (Request $r) => Limit::perMinute(10)->by($byActor($r)));
        $limiter->for('credentials', fn (Request $r) => Limit::perMinute(10)->by($byActor($r)));
        $limiter->for('upload', fn (Request $r) => Limit::perMinute(10)->by($byActor($r)));
        $limiter->for('sync-pull', fn (Request $r) => Limit::perMinute(240)->by($byDevice($r)));
        $limiter->for('sync-push', fn (Request $r) => Limit::perMinute(240)->by($byDevice($r)));
    }
}
