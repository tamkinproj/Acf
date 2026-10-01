<?php

namespace App\Console\Commands;

use App\Core\Demo\DemoData;
use App\Install\InstallState;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/** Sample data for development and demonstrations. Never run this on a real installation. */
class FoundationDemo extends Command
{
    protected $signature = 'foundation:demo {--password= : password for every demo account (default: generated)} {--force : confirm this is NOT a real installation}';
    protected $description = 'Create a labelled "Demo Foundation" with sample programs, people, records and registrations (development only).';

    public function handle(InstallState $state, DemoData $demo): int
    {
        if (! $state->isInstalled()) {
            $this->error('Install the system first (foundation:install).');

            return self::FAILURE;
        }
        if (! $this->option('force')) {
            $this->error('This adds sample accounts and records. It is for development only: never run it on a real installation. Add --force to confirm.');

            return self::FAILURE;
        }
        $password = (string) ($this->option('password') ?: 'Demo-'.Str::random(10).'-1');

        try {
            $result = $demo->seed($password);
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Demo data created for "'.$result['foundation']->name.'".');
        foreach ($result['accounts'] as $role => $email) {
            $this->line(sprintf('  %-20s %s', $role, $email));
        }
        $this->line('  Password (all demo accounts): '.$result['password']);
        $this->line('  Device tokens (paste one when a browser asks to be registered): '.implode(' ', $result['device_tokens']));

        return self::SUCCESS;
    }
}
