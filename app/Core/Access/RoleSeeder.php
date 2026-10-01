<?php

namespace App\Core\Access;

use App\Models\Role;
use App\Models\SystemState;

/**
 * Creates the default roles at install, and on upgrade adds ONLY permissions
 * that did not exist at the previous release - so a new module's permissions
 * reach the default roles without re-granting anything an administrator
 * deliberately removed.
 */
class RoleSeeder
{
    public function seed(): void
    {
        foreach (RoleCatalog::defaults() as $key => $def) {
            Role::firstOrCreate(
                ['key' => $key],
                ['name' => $def['name'], 'description' => $def['description'], 'is_system' => true, 'permissions' => RoleCatalog::permissionsFor($key)],
            );
        }
        SystemState::put('permissions_seen', PermissionCatalog::keys());
    }

    /** @return list<string> newly introduced permission keys */
    public function upgrade(): array
    {
        $seen = (array) SystemState::get('permissions_seen', []);
        $new = array_values(array_diff(PermissionCatalog::keys(), $seen));
        if ($new === []) {
            return [];
        }

        foreach (Role::query()->where('is_system', true)->get() as $role) {
            $grant = array_values(array_intersect($new, RoleCatalog::permissionsFor($role->key)));
            if ($grant !== []) {
                $role->permissions = array_values(array_unique(array_merge($role->permissions ?? [], $grant)));
                $role->save();
            }
        }
        SystemState::put('permissions_seen', PermissionCatalog::keys());

        return $new;
    }
}
