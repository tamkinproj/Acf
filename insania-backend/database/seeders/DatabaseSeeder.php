<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->warn('Demo data is never seeded in production. Create the first System Admin with: php artisan insania:create-system-admin');

            return;
        }

        $this->call(DemoSeeder::class);
    }
}
