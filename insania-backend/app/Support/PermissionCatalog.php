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

        return array_values(array_unique(array_filter($keys, [self::class, 'isValidPermissionKey'])));
    }

    public static function allows(User $user, string $permission): bool
    {
        return in_array($permission, self::forUser($user), true);
    }
}
