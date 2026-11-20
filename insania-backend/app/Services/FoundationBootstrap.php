<?php

namespace App\Services;

use App\Models\Department;
use App\Models\Foundation;
use App\Models\User;

/** Creates the foundation, its departments and the first System Admin. Shared by the web installer and the command. */
class FoundationBootstrap
{
    public function make(string $foundationName, string $adminName, string $adminEmail, string $adminPassword): User
    {
        $foundation = Foundation::first() ?? Foundation::create(['name' => $foundationName, 'slug' => 'foundation']);

        foreach (config('roles.departments') as $key => $d) {
            Department::firstOrCreate(['foundation_id' => $foundation->id, 'key' => $key], ['name_en' => $d['name_en'], 'name_ar' => $d['name_ar']]);
        }

        return User::updateOrCreate(['email' => $adminEmail], [
            'name' => $adminName, 'password' => $adminPassword, 'role' => 'system_admin',
            'foundation_id' => $foundation->id, 'active' => true,
        ]);
    }
}
