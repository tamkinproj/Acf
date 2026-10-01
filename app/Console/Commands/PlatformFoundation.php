<?php

namespace App\Console\Commands;

use App\Core\Platform\FoundationProvisioner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/** Create a foundation and its first Foundation Admin from the command line (development, scripts, recovery). */
class PlatformFoundation extends Command
{
    protected $signature = 'platform:foundation {name} {--admin-name=} {--admin-email=} {--admin-password= : omit to generate a temporary one} {--short-name=} {--country=}';
    protected $description = 'Create a foundation with its first Foundation Admin.';

    public function handle(FoundationProvisioner $provisioner): int
    {
        $v = Validator::make([
            'name' => $this->argument('name'), 'admin_name' => $this->option('admin-name'), 'admin_email' => $this->option('admin-email'),
        ], ['name' => ['required', 'string', 'max:200'], 'admin_name' => ['required', 'string', 'max:150'], 'admin_email' => ['required', 'email:rfc']]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $m) {
                $this->error($m);
            }

            return self::FAILURE;
        }
        $result = $provisioner->create(
            ['name' => $this->argument('name'), 'short_name' => $this->option('short-name'), 'country' => $this->option('country')],
            ['name' => $this->option('admin-name'), 'email' => $this->option('admin-email'), 'password' => $this->option('admin-password') ?: null],
        );
        $this->info("Foundation created: {$result['foundation']->name} ({$result['foundation']->slug})");
        $this->line("Administrator: {$result['admin']->email}");
        if ($result['temporary_password']) {
            $this->line("Temporary password (shown once): {$result['temporary_password']}");
        }

        return self::SUCCESS;
    }
}
