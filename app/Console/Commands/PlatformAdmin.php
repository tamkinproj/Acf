<?php

namespace App\Console\Commands;

use App\Core\Access\RoleSeeder;
use App\Core\Users\PasswordPolicy;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

/** Create a Platform Admin, or set a new password for an existing one (recovery without a web page). */
class PlatformAdmin extends Command
{
    protected $signature = 'platform:admin {email} {name? : required when creating} {--password= : or env PLATFORM_ADMIN_PASSWORD, or prompt}';
    protected $description = 'Create a Platform Admin, or reset the password of an existing one.';

    public function handle(TenantContext $tenant, RoleSeeder $roles): int
    {
        $email = strtolower((string) $this->argument('email'));
        $password = (string) ($this->option('password') ?: getenv('PLATFORM_ADMIN_PASSWORD') ?: '');
        if ($password === '' && $this->input->isInteractive()) {
            $password = (string) $this->secret('New password');
        }
        $v = Validator::make(['email' => $email, 'password' => $password], ['email' => ['required', 'email:rfc'], 'password' => ['required', 'string', PasswordPolicy::rule()]]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $m) {
                $this->error($m);
            }

            return self::FAILURE;
        }

        return $tenant->asSystem(function () use ($email, $password, $roles) {
            $role = $roles->ensurePlatform();
            $user = User::withTrashed()->whereRaw('lower(email) = ?', [$email])->first();
            if ($user && $user->foundation_id !== null) {
                $this->error('That email belongs to a foundation user.');

                return self::FAILURE;
            }
            if ($user) {
                $user->restore();
                $user->forceFill(['password' => $password, 'status' => 'active', 'must_change_password' => false])->save();
                $this->info("Password updated for {$user->email}.");

                return self::SUCCESS;
            }
            $name = (string) $this->argument('name');
            if ($name === '') {
                $this->error('A name is required to create a new Platform Admin.');

                return self::FAILURE;
            }
            User::create(['foundation_id' => null, 'name' => $name, 'email' => $email, 'password' => $password, 'role_id' => $role->getKey(), 'status' => 'active']);
            $this->info("Platform Admin created: {$email}");

            return self::SUCCESS;
        });
    }
}
