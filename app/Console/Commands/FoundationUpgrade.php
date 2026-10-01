<?php

namespace App\Console\Commands;

use App\Install\InstallState;
use App\Install\Upgrader;
use Illuminate\Console\Command;

/**
 * The upgrade path for future releases (the same code the token-protected web page runs): new migrations, data changes,
 * settings keys and role permissions introduced since the installed version. Administrators never edit production
 * tables by hand.
 */
class FoundationUpgrade extends Command
{
    protected $signature = 'foundation:upgrade';
    protected $description = 'Apply pending migrations and release data for the installed version.';

    public function handle(InstallState $state, Upgrader $upgrader): int
    {
        if (! $state->isInstalled()) {
            $this->error('The system is not installed (or its state is damaged). Run foundation:status.');

            return self::FAILURE;
        }
        $this->line('Upgrading from '.($upgrader->installedVersion() ?? 'unknown').' to '.config('foundation.version'));
        $result = $upgrader->run();

        $this->info("Done. {$result['roles_migrated']} role change(s), {$result['permissions_added']} new permission(s) granted to default roles.");
        if ($result['needs_platform_admin']) {
            $this->warn('There is no Platform Admin yet. Create one with: php artisan platform:admin you@example.org "Your Name"');
        }

        return self::SUCCESS;
    }
}
