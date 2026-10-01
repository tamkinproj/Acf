<?php

namespace App\Core\Access;

use App\Models\Role;
use App\Models\SystemState;
use App\Tenancy\TenantContext;

/**
 * Creates role sets and keeps them current as releases add permissions.
 *
 *  - ensurePlatform():            the platform administrator role (once)
 *  - ensureForFoundation($id):    a foundation's own copy of the foundation and program roles (idempotent)
 *  - upgrade():                   adds ONLY permissions that did not exist at the previous release, so a new module's
 *                                 permissions reach the default roles without re-granting what an administrator removed
 */
class RoleSeeder
{
    public function __construct(private TenantContext $tenant) {}

    public function ensurePlatform(): Role
    {
        return $this->tenant->asSystem(function () {
            $def = RoleCatalog::platformDefaults()[RoleCatalog::PLATFORM_ADMIN];

            return Role::query()->whereNull('foundation_id')->where('key', RoleCatalog::PLATFORM_ADMIN)->first()
                ?? Role::create([
                    'foundation_id' => null, 'key' => RoleCatalog::PLATFORM_ADMIN, 'name' => $def['name'],
                    'description' => $def['description'], 'is_system' => true, 'scope' => $def['scope'],
                    'permissions' => RoleCatalog::resolve(RoleCatalog::PLATFORM_ADMIN, $def['permissions'], $def['scope']),
                ]);
        });
    }

    /** @return array<string,Role> by key */
    public function ensureForFoundation(string $foundationId): array
    {
        return $this->tenant->asSystem(function () use ($foundationId) {
            $out = [];
            foreach (RoleCatalog::foundationDefaults() as $key => $def) {
                $out[$key] = Role::query()->where('foundation_id', $foundationId)->where('key', $key)->first()
                    ?? Role::create([
                        'foundation_id' => $foundationId, 'key' => $key, 'name' => $def['name'], 'description' => $def['description'],
                        'is_system' => true, 'scope' => $def['scope'], 'module' => $def['module'],
                        'permissions' => RoleCatalog::resolve($key, $def['permissions'], $def['scope']),
                    ]);
            }

            return $out;
        });
    }

    /** @return list<string> newly introduced permission keys */
    public function upgrade(): array
    {
        $seen = (array) SystemState::get('permissions_seen', []);
        $new = array_values(array_diff(PermissionCatalog::keys(), $seen));

        $this->tenant->asSystem(function () use ($new) {
            // Roles introduced by a release (a new module) appear in every existing foundation.
            foreach (\App\Models\Foundation::query()->pluck('id') as $foundationId) {
                $this->ensureForFoundation($foundationId);
            }
            $this->ensurePlatform();

            if ($new === []) {
                return;
            }
            foreach (Role::query()->where('is_system', true)->get() as $role) {
                $grant = array_values(array_intersect($new, RoleCatalog::permissionsFor($role->key)));
                if ($grant !== []) {
                    $role->permissions = array_values(array_unique(array_merge($role->permissions ?? [], $grant)));
                    $role->save();
                }
            }
        });
        SystemState::put('permissions_seen', PermissionCatalog::keys());

        return $new;
    }

    public function markSeen(): void
    {
        SystemState::put('permissions_seen', PermissionCatalog::keys());
    }
}
