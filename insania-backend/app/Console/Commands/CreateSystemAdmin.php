<?php

namespace App\Console\Commands;

use App\Models\Department;
use App\Models\Foundation;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

/**
 * The only way to create a System Admin (the API refuses to). Also creates the foundation and its departments the first time.
 */
class CreateSystemAdmin extends Command
{
    protected $signature = 'insania:create-system-admin {name} {email} {--foundation="Al Insania Charity Foundation Inc."} {--password= : Leave empty to be asked, so it stays out of shell history}';

    protected $description = 'Create the first System Admin (and the foundation, if none exists).';

    public function handle(): int
    {
        $password = $this->option('password') ?: $this->secret('Password (at least 12 characters, mixed case, with a number)');

        $v = Validator::make(['email' => $this->argument('email'), 'password' => $password], [
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => [Password::min(12)->mixedCase()->numbers()],
        ]);
        if ($v->fails()) {
            foreach ($v->errors()->all() as $e) {
                $this->error($e);
            }

            return self::FAILURE;
        }

        $foundation = Foundation::first() ?? Foundation::create(['name' => trim($this->option('foundation'), '"'), 'slug' => 'foundation']);
        foreach (config('roles.departments') as $key => $d) {
            Department::firstOrCreate(['foundation_id' => $foundation->id, 'key' => $key], ['name_en' => $d['name_en'], 'name_ar' => $d['name_ar']]);
        }

        User::create(['name' => $this->argument('name'), 'email' => $this->argument('email'), 'password' => $password, 'role' => 'system_admin',
            'foundation_id' => $foundation->id, 'active' => true]);

        $this->info('System Admin created.');

        return self::SUCCESS;
    }
}
