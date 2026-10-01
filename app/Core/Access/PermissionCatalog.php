<?php

namespace App\Core\Access;

/**
 * Source of truth for permission keys (what a capability is called). WHICH roles hold a capability is data
 * (roles.permissions); adding a capability is a code change. Every permission has a scope:
 *
 *   platform    only platform administrators can ever hold it
 *   foundation  applies across one foundation
 *   program     applies inside one program (granted by a program role, or by a foundation role for every program)
 *
 * Program modules (Aytam, Relief, ...) register their own permissions through register().
 */
final class PermissionCatalog
{
    public const PLATFORM = 'platform';
    public const FOUNDATION = 'foundation';
    public const PROGRAM = 'program';

    /** @var array<string,array{label:string,group:string,scope:string}> */
    private static array $extra = [];

    /** @return array<string,array{label:string,group:string,scope:string}> */
    public static function all(): array
    {
        $f = fn (string $label, string $group) => ['label' => $label, 'group' => $group, 'scope' => self::FOUNDATION];
        $p = fn (string $label, string $group) => ['label' => $label, 'group' => $group, 'scope' => self::PLATFORM];

        return array_merge([
            'platform.view' => $p('View the platform dashboard, foundations and activity', 'Platform'),
            'platform.foundations.manage' => $p('Create, edit, activate, deactivate and suspend foundations', 'Platform'),
            'platform.users.manage' => $p('Manage platform administrators', 'Platform'),
            'platform.settings.manage' => $p('Change platform settings', 'Platform'),

            'dashboard.view' => $f('View the dashboard', 'General'),
            'sync.use' => $f('Synchronize this device', 'Sync'),
            'sync.manage' => $f('View and resolve sync conflicts', 'Sync'),
            'foundation.view' => $f('View the foundation profile', 'Foundation'),
            'foundation.update' => $f('Edit the foundation profile', 'Foundation'),
            'settings.view' => $f('View settings', 'Foundation'),
            'settings.manage' => $f('Change settings', 'Foundation'),
            'locations.view' => $f('View places', 'Places'),
            'locations.manage' => $f('Create and edit places', 'Places'),
            'users.view' => $f('View users', 'Access'),
            'users.create' => $f('Create users', 'Access'),
            'users.update' => $f('Edit users, reset passwords and assign program access', 'Access'),
            'users.deactivate' => $f('Deactivate, reactivate and remove users', 'Access'),
            'roles.view' => $f('View roles and permissions', 'Access'),
            'roles.manage' => $f('Create roles and edit their permissions', 'Access'),
            'devices.view' => $f('View registered devices', 'Devices'),
            'devices.manage' => $f('Register, rename and revoke devices', 'Devices'),
            'audit.view' => $f('View the activity log', 'Audit'),
            'organizations.view' => $f('View organizations', 'Organizations'),
            'organizations.create' => $f('Create organizations', 'Organizations'),
            'organizations.update' => $f('Edit and deactivate organizations', 'Organizations'),
            'programs.view' => $f('View all programs', 'Programs'),
            'programs.create' => $f('Create programs', 'Programs'),
            'programs.update' => $f('Edit programs, configuration and program teams', 'Programs'),
            'programs.activate' => $f('Activate, deactivate and archive programs', 'Programs'),
        ], self::$extra);
    }

    /** @param array<string,array{label:string,group:string,scope?:string}> $permissions */
    public static function register(array $permissions): void
    {
        foreach ($permissions as $key => $def) {
            self::$extra[$key] = $def + ['scope' => self::FOUNDATION];
        }
    }

    /** @return list<string> */
    public static function keys(?string $scope = null): array
    {
        $all = self::all();

        return array_keys($scope === null ? $all : array_filter($all, fn ($p) => $p['scope'] === $scope));
    }

    /** @param list<string> $scopes */
    public static function keysIn(array $scopes): array
    {
        return array_keys(array_filter(self::all(), fn ($p) => in_array($p['scope'], $scopes, true)));
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function scopeOf(string $key): ?string
    {
        return self::all()[$key]['scope'] ?? null;
    }
}
