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
        CoreEntities::register($this->app->make(SyncRegistry::class));

        if (! $this->app->runningInConsole() && $this->app->make(InstallState::class)->isInstalled()) {
            $this->app->make(SettingsService::class)->applyRuntime();
        }
    }
}
