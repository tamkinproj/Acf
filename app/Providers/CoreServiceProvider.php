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
use App\Sync\SyncRegistry;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class CoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
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
        $this->rateLimiters();
        CoreEntities::register($this->app->make(SyncRegistry::class));

        if (! $this->app->runningInConsole() && $this->app->make(InstallState::class)->isInstalled()) {
            $this->app->make(SettingsService::class)->applyRuntime();
        }
    }

    /**
     * Named limiters, each with its OWN counter. (The numeric `throttle:N,1` shorthand shares one counter
     * per client across every route that uses it, so unrelated screens would eat each other's budget.)
     */
    private function rateLimiters(): void
    {
        $byIp = fn (Request $r) => $r->ip();
        $byActor = fn (Request $r) => $r->user()?->getAuthIdentifier() ?? $r->ip();
        $byDevice = fn (Request $r) => $r->attributes->get('device')?->getKey() ?? $byActor($r);

        RateLimiter::for('installer-token', fn (Request $r) => Limit::perMinute(20)->by($byIp($r)));
        RateLimiter::for('installer-database', fn (Request $r) => Limit::perMinute(40)->by($byIp($r)));
        RateLimiter::for('installer-run', fn (Request $r) => Limit::perMinute(10)->by($byIp($r)));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(30)->by($byIp($r)));   // per-account limit lives in AuthController
        RateLimiter::for('password', fn (Request $r) => Limit::perMinute(10)->by($byActor($r)));
        RateLimiter::for('credentials', fn (Request $r) => Limit::perMinute(10)->by($byActor($r)));
        RateLimiter::for('upload', fn (Request $r) => Limit::perMinute(10)->by($byActor($r)));
        RateLimiter::for('sync-pull', fn (Request $r) => Limit::perMinute(240)->by($byDevice($r)));
        RateLimiter::for('sync-push', fn (Request $r) => Limit::perMinute(240)->by($byDevice($r)));
    }
}
