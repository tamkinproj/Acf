<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Intentionally empty. This system never ships default accounts or passwords: all essential data
 * (roles, settings, the foundation profile, the first administrator, the installation device) is
 * created by the setup wizard, with the administrator choosing their own password. Future releases
 * add data through `php artisan foundation:upgrade`, not seeders.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
