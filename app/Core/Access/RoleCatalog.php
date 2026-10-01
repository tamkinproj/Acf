<?php

namespace App\Core\Access;

/** Default (system) roles and their initial grants. Admin-editable afterwards, except Super Admin. */
final class RoleCatalog
{
    public const SUPER_ADMIN = 'super_admin';

    /** @return array<string,array{name:string,description:string,permissions:list<string>|string}> '*' = every permission */
    public static function defaults(): array
    {
        $base = ['dashboard.view', 'sync.use', 'foundation.view', 'settings.view', 'locations.view'];

        return [
            self::SUPER_ADMIN => [
                'name' => 'Super Admin',
                'description' => 'Full control of the system, including roles and permissions.',
                'permissions' => '*',
            ],
            'foundation_admin' => [
                'name' => 'Foundation Admin',
                'description' => 'Runs the foundation day to day: users, settings, locations, devices, audit.',
                'permissions' => array_values(array_diff(PermissionCatalog::keys(), ['roles.manage'])),
            ],
            'staff' => [
                'name' => 'Staff',
                'description' => 'Office staff who maintain foundation records.',
                'permissions' => array_merge($base, ['locations.manage', 'sync.manage']),
            ],
            'field_worker' => [
                'name' => 'Field Worker',
                'description' => 'Works in the field, often offline, and records activity on site.',
                'permissions' => $base,
            ],
            'volunteer' => [
                'name' => 'Volunteer',
                'description' => 'Limited access to assigned activities.',
                'permissions' => ['dashboard.view', 'sync.use', 'foundation.view', 'settings.view', 'locations.view'],
            ],
            'viewer' => [
                'name' => 'Viewer',
                'description' => 'Read-only access.',
                'permissions' => ['dashboard.view', 'sync.use', 'foundation.view', 'settings.view', 'locations.view'],
            ],
        ];
    }

    /** @return list<string> */
    public static function permissionsFor(string $roleKey): array
    {
        $perms = self::defaults()[$roleKey]['permissions'] ?? [];

        return $perms === '*' ? PermissionCatalog::keys() : $perms;
    }
}
