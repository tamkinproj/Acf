<?php

namespace App\Core\Access;

use App\Programs\ProgramModules;

/**
 * Role templates. A foundation gets its OWN copy of the foundation and program roles when it is created, and may
 * edit them (except the fixed ones). Nothing in the code is hard-wired to a role: it asks for permissions.
 */
final class RoleCatalog
{
    public const PLATFORM_ADMIN = 'platform_admin';
    public const FOUNDATION_ADMIN = 'foundation_admin';

    /** Roles whose permission set is "everything in their scope" and can never be edited (so nobody is locked out). */
    public const FIXED = [self::PLATFORM_ADMIN, self::FOUNDATION_ADMIN];

    /** @return array<string,array{name:string,description:string,scope:string,module:?string,permissions:list<string>|string}> '*' = every permission of the scope */
    public static function platformDefaults(): array
    {
        return [
            self::PLATFORM_ADMIN => [
                'name' => 'Platform Admin',
                'description' => 'Runs the platform: creates and manages foundations. Cannot see foundation records.',
                'scope' => PermissionCatalog::PLATFORM, 'module' => null, 'permissions' => '*',
            ],
        ];
    }

    /** Foundation-level and program-level templates, provisioned into every foundation. */
    public static function foundationDefaults(): array
    {
        $base = ['dashboard.view', 'sync.use', 'foundation.view', 'settings.view', 'locations.view', 'programs.view'];
        $roles = [
            self::FOUNDATION_ADMIN => [
                'name' => 'Foundation Admin',
                'description' => 'Controls the whole foundation: profile, users, roles, organizations and programs.',
                'scope' => PermissionCatalog::FOUNDATION, 'module' => null, 'permissions' => '*',
            ],
            'staff' => [
                'name' => 'Staff',
                'description' => 'Office staff who maintain foundation records.',
                'scope' => PermissionCatalog::FOUNDATION, 'module' => null,
                'permissions' => array_merge($base, ['locations.manage', 'sync.manage', 'organizations.view']),
            ],
            'field_worker' => [
                'name' => 'Field Worker',
                'description' => 'Works in the field, often offline, and records activity on site.',
                'scope' => PermissionCatalog::FOUNDATION, 'module' => null, 'permissions' => $base,
            ],
            'volunteer' => [
                'name' => 'Volunteer',
                'description' => 'Limited access to assigned activities.',
                'scope' => PermissionCatalog::FOUNDATION, 'module' => null, 'permissions' => ['dashboard.view', 'sync.use', 'foundation.view', 'locations.view'],
            ],
            'viewer' => [
                'name' => 'Viewer',
                'description' => 'Read-only access.',
                'scope' => PermissionCatalog::FOUNDATION, 'module' => null, 'permissions' => $base,
            ],
        ];

        foreach (ProgramModules::roleTemplates() as $key => $def) {
            $roles[$key] = [
                'name' => $def['name'], 'description' => $def['description'],
                'scope' => PermissionCatalog::PROGRAM, 'module' => $def['module'], 'permissions' => $def['permissions'],
            ];
        }

        return $roles;
    }

    /** @return list<string> */
    public static function resolve(string $key, string|array $permissions, string $scope): array
    {
        if ($permissions !== '*') {
            return $permissions;
        }

        return $scope === PermissionCatalog::PLATFORM
            ? PermissionCatalog::keys(PermissionCatalog::PLATFORM)
            : PermissionCatalog::keysIn([PermissionCatalog::FOUNDATION, PermissionCatalog::PROGRAM]);
    }

    /** All templates by key. */
    public static function all(): array
    {
        return self::platformDefaults() + self::foundationDefaults();
    }

    /** @return list<string> the default grants for a role template (used when a release adds permissions) */
    public static function permissionsFor(string $roleKey): array
    {
        $def = self::all()[$roleKey] ?? null;

        return $def ? self::resolve($roleKey, $def['permissions'], $def['scope']) : [];
    }
}
