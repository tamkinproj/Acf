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
            'partners.manage' => 'Pause and resume partners (there is no delete)',
            'system.cloudflare' => 'Switch the website\'s Cloudflare protections on and off',
            'system.api_keys' => 'Issue and revoke partner API keys',
            'system.updates' => 'Apply signed update patches and undo them',
            'system.first_admin' => 'Create the first Security Admin (only while none exists)',
            'users.manage' => 'Create users, assign roles and departments, reset passwords, end sessions',
            'audit.read' => 'Read the audit log',
            'files.all' => 'Open every file uploaded in the offices and download a backup of all files and records',
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
            'chat.use' => 'Use the office chat (General group and one-to-one chats) and share cards into it',
            'orphans.accounts' => 'Create sign-in accounts for orphans and reset their passwords',
            'education.view' => 'See the Education department: Alimni teachers, institutions, curriculum',
            'education.manage' => 'Add and edit Alimni teachers, contracts, institutions and curriculum',
            'work.view' => 'See what staff do in the app: active time, last seen, what they looked at and did (work tracker)',
            'attendance.clock' => 'Clock in and out with a face photo (for staff who are office employees)',
            'attendance.manage' => 'Attendance: employees, corrections, leaves, holidays and the office board',
            'attendance.salary' => 'See employee salaries, deductions and the monthly payroll',
            'education.salary' => 'See and record the monthly salaries (sweldo) of the Alimni teachers',
            'family.view_own' => 'See your own family\'s information',
        ];
    }

    /** Every permission the President may tick for a staff account. @return list<string> */
    public static function grantable(): array
    {
        return array_values(array_unique(array_merge(...array_values(config('roles.access_groups', [])))));
    }

    /** What a department gives its staff before the President changes anything. @return list<string> */
    public static function departmentDefaults(?string $dept): array
    {
        return array_values(array_intersect(config("roles.departments.$dept.grants", []), self::grantable()));
    }

    /** The ticked permissions of a staff account (the department's access with the President's changes), for the Accounts screen. */
    public static function ticked(User $user): array
    {
        return array_values(array_intersect(self::forUser($user), self::grantable()));
    }

    /**
     * Saves the ticks of a staff account as the difference from the department's usual access.
     * Nothing is stored when the ticks are exactly the department's.
     */
    public static function setTicked(User $user, array $ticked, ?string $dept = null): array
    {
        $g = self::grantable();
        $want = array_values(array_intersect($ticked, $g));
        $base = self::departmentDefaults($dept ?? $user->department_key);
        $add = array_values(array_diff($want, $base));
        $remove = array_values(array_diff($base, $want));
        $user->access_overrides = ($add || $remove) ? ['add' => $add, 'remove' => $remove] : null;

        return ['added' => $add, 'removed' => $remove];
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

        if ($role === 'staff') {
            $o = $user->access_overrides ?? [];
            $grantable = self::grantable();
            $keys = array_merge($keys, array_intersect((array) ($o['add'] ?? []), $grantable));
            $keys = array_diff($keys, array_intersect((array) ($o['remove'] ?? []), $grantable));
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
