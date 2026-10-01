<?php

namespace App\Core\Access;

/**
 * Source of truth for permission keys (what a capability is called) - the
 * same split MuslimEdu's PermissionCatalog uses. WHICH roles hold a
 * capability is data (roles.permissions); adding a capability is a code change.
 * Modules call register() from their service provider.
 */
final class PermissionCatalog
{
    /** @var array<string,array{label:string,group:string}> */
    private static array $extra = [];

    /** @return array<string,array{label:string,group:string}> */
    public static function all(): array
    {
        return array_merge([
            'dashboard.view' => ['label' => 'View the dashboard', 'group' => 'General'],
            'sync.use' => ['label' => 'Synchronize this device', 'group' => 'Sync'],
            'sync.manage' => ['label' => 'View and resolve sync conflicts', 'group' => 'Sync'],
            'foundation.view' => ['label' => 'View the foundation profile', 'group' => 'Foundation'],
            'foundation.manage' => ['label' => 'Edit the foundation profile', 'group' => 'Foundation'],
            'settings.view' => ['label' => 'View system settings', 'group' => 'Foundation'],
            'settings.manage' => ['label' => 'Change system settings', 'group' => 'Foundation'],
            'locations.view' => ['label' => 'View locations', 'group' => 'Locations'],
            'locations.manage' => ['label' => 'Create and edit locations', 'group' => 'Locations'],
            'users.view' => ['label' => 'View users', 'group' => 'Access'],
            'users.manage' => ['label' => 'Create and manage users', 'group' => 'Access'],
            'roles.view' => ['label' => 'View roles and permissions', 'group' => 'Access'],
            'roles.manage' => ['label' => 'Edit role permissions', 'group' => 'Access'],
            'devices.view' => ['label' => 'View registered devices', 'group' => 'Devices'],
            'devices.manage' => ['label' => 'Register, rename and revoke devices', 'group' => 'Devices'],
            'audit.view' => ['label' => 'View the activity log', 'group' => 'Audit'],
        ], self::$extra);
    }

    /** @param array<string,array{label:string,group:string}> $permissions */
    public static function register(array $permissions): void
    {
        self::$extra = array_merge(self::$extra, $permissions);
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::all());
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }
}
