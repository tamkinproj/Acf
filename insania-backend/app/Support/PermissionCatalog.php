<?php

namespace App\Support;

use App\Models\User;

/**
 * Source of truth for permission keys, and the one place that answers "what can this user do".
 * (Same idea as the old system's catalog; the keys are now the foundation's, and the grants come from config/roles.php.)
 */
final class PermissionCatalog
{
    /** @return array<string, string> key => description */
    public static function permissionKeys(): array
    {
        return [
            'system.health' => 'See system health (no personal data)',
            'system.settings' => 'Change system settings',
            'site.edit' => 'Edit the public landing page draft (texts, photos, projects, team, news)',
            'site.publish' => 'Publish the public landing page',
            'system.api_keys' => 'Issue and revoke partner API keys',
            'system.updates' => 'Apply signed update patches and undo them',
            'system.first_admin' => 'Create the first Security Admin (only while none exists)',
            'users.manage' => 'Create users, assign roles and departments, reset passwords, end sessions',
            'audit.read' => 'Read the audit log',
            'children.view' => 'See children and their profiles',
            'children.create' => 'Register children',
            'children.update' => 'Edit children',
            'applications.review' => 'Review applications sent through the public form, and turn them into orphan records',
            'bank.verify' => 'Verify or un-verify a child\'s bank account',
            'documents.view' => 'See a child\'s documents',
            'documents.upload' => 'Upload a child\'s documents',
            'payments.view' => 'See monthly sponsorship payments',
            'payments.mark_cash' => 'Record that a cash payment was received',
            'projects.view' => 'See projects',
            'projects.manage' => 'Create and edit projects',
            'reports.view' => 'See report summaries',
            'reports.export' => 'Export reports',
            'family.view_own' => 'See your own family\'s information',
        ];
    }

    public static function isValidPermissionKey(string $key): bool
    {
        return array_key_exists($key, self::permissionKeys());
    }

    /** @return list<string> */
    public static function forUser(User $user): array
    {
        if (! $user->active) {
            return [];
        }

        $role = $user->role;
        $keys = config("roles.grants.$role", []);

        if ($role === 'staff' && $user->department_key) {
            $keys = array_merge($keys, config("roles.departments.{$user->department_key}.grants", []));
        }

        $keys = array_filter($keys, [self::class, 'isValidPermissionKey']);
        $off = self::switchedOffPermissions($role);

        return array_values(array_unique(array_filter($keys, fn ($k) => ! in_array($k, $off, true))));
    }

    /**
     * Permissions of the office features that are off for this role (config/features.php).
     * A switch is true (on for every role that has the feature), false (off), or a list of roles it is on for.
     */
    public static function switchedOffPermissions(?string $role = null): array
    {
        $off = [];
        foreach ((array) config('features.permissions', []) as $feature => $perms) {
            $sw = config("features.switches.$feature", false);
            $on = $sw === true || (is_array($sw) && $role !== null && in_array($role, $sw, true));
            if (! $on) {
                $off = array_merge($off, (array) $perms);
            }
        }

        return $off;
    }

    /** True when this user's role would have more permissions if every feature were on. */
    public static function hasSwitchedOff(User $user): bool
    {
        if (! $user->active) {
            return false;
        }
        $keys = config("roles.grants.{$user->role}", []);
        if ($user->role === 'staff' && $user->department_key) {
            $keys = array_merge($keys, config("roles.departments.{$user->department_key}.grants", []));
        }

        return (bool) array_intersect($keys, self::switchedOffPermissions($user->role));
    }

    public static function allows(User $user, string $permission): bool
    {
        return in_array($permission, self::forUser($user), true);
    }
}
