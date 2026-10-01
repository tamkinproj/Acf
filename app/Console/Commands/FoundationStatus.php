<?php

namespace App\Console\Commands;

use App\Install\InstallState;
use App\Models\Device;
use App\Models\Role;
use App\Models\SystemState;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class FoundationStatus extends Command
{
    protected $signature = 'foundation:status';
    protected $description = 'Show installation state and check the lock, .env and database agree (read-only; never repairs).';

    public function handle(InstallState $state): int
    {
        $status = $state->status();
        $this->line('Install state : '.$status->value);
        if ($reason = $state->corruptReason()) {
            $this->error($reason);
        }
        $this->line('Code version  : '.config('foundation.version').' (schema '.config('foundation.schema_version').')');

        if ($status->value !== 'installed') {
            return $status->value === 'corrupt' ? self::FAILURE : self::SUCCESS;
        }

        $problems = [];
        try {
            DB::connection()->getPdo();
            $dbId = SystemState::get('install_id');
            $this->line('Database      : '.DB::connection()->getDriverName().' — reachable');
            $this->line('Installed at  : '.SystemState::get('installed_at'));
            $this->line('DB version    : '.SystemState::get('version').' (schema '.SystemState::get('schema_version').')');
            $this->line(sprintf('Users %d · Roles %d · Devices %d', User::count(), Role::count(), Device::count()));
            if ($dbId !== $state->lock()['install_id']) {
                $problems[] = 'The database install_id does not match the lock file (the lock or database may belong to a different installation).';
            }
            if ((int) SystemState::get('schema_version') !== (int) config('foundation.schema_version')) {
                $problems[] = 'Schema version differs from the code. Run: php artisan foundation:upgrade';
            }
        } catch (\Throwable $e) {
            $problems[] = 'Cannot read the database: '.class_basename($e);
        }

        foreach ($problems as $p) {
            $this->error($p);
        }

        return $problems ? self::FAILURE : self::SUCCESS;
    }
}
