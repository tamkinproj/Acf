<?php

namespace App\Console\Commands;

use App\Core\Access\RoleSeeder;
use App\Core\Settings\SettingsService;
use App\Install\InstallState;
use App\Models\SystemState;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * The upgrade path for future releases: run new migrations, add settings keys and role permissions
 * introduced since the installed version, then record the new version. Administrators never edit
 * production tables by hand.
 */
class FoundationUpgrade extends Command
{
    protected $signature = 'foundation:upgrade';
    protected $description = 'Apply pending migrations and release data for the installed version.';

    public function handle(InstallState $state): int
    {
        if (! $state->isInstalled()) {
            $this->error('The system is not installed (or its state is damaged). Run foundation:status.');

            return self::FAILURE;
        }

        $from = SystemState::get('version', 'unknown');
        $this->line("Upgrading from {$from} to ".config('foundation.version'));

        Artisan::call('migrate', ['--force' => true], $this->output);
        $settings = app(SettingsService::class)->ensureDefaults();
        $perms = app(RoleSeeder::class)->upgrade();

        SystemState::put('version', config('foundation.version'));
        SystemState::put('schema_version', (int) config('foundation.schema_version'));

        $this->info("Done. {$settings} new setting(s), ".count($perms).' new permission(s) granted to default roles.');

        return self::SUCCESS;
    }
}
