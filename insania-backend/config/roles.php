<?php

/**
 * Who can do what. This file is the single reference for the role matrix agreed for the foundation:
 *
 *   TECHNICAL roles   System Admin, Admin Maintenance, Security Admin
 *                     They run the system. None of them can open a child's record or money details.
 *   OFFICE roles      President Admin   - every office feature (all departments)
 *                     Staff             - only the features of their own department
 *                     User              - a family's own information (an Aytam family account)
 *
 * The role name is stored on users.role. Permission keys are defined in App\Support\PermissionCatalog.
 * Adding a capability is a code change; deciding who holds it is a change in this file (reviewed, tested).
 */
return [

    // Which attribute(s) on the signed-in user EnsureRole reads the role from.
    'attributes' => ['role'],

    // Alternative spellings accepted by ->middleware('role:...'). Kept from the old system's EnsureRole.
    'aliases' => [
        'president' => 'president_admin',
        'sysadmin' => 'system_admin',
        'family' => 'user',
    ],

    'roles' => [
        'system_admin' => ['label' => 'System Admin', 'kind' => 'technical'],
        'admin_maintenance' => ['label' => 'Admin Maintenance', 'kind' => 'technical'],
        'security_admin' => ['label' => 'Security Admin', 'kind' => 'technical'],
        'president_admin' => ['label' => 'President Admin', 'kind' => 'office'],
        'staff' => ['label' => 'Staff (by department)', 'kind' => 'office'],
        'user' => ['label' => 'User (family)', 'kind' => 'family'],
    ],

    // Permissions held by each role. Staff get theirs from their department (below).
    'grants' => [
        'system_admin' => ['system.health', 'system.settings', 'system.api_keys', 'system.updates', 'system.first_admin'],
        'admin_maintenance' => ['system.health'],
        'security_admin' => ['users.manage', 'audit.read'],
        'president_admin' => [
            'children.view', 'children.create', 'children.update', 'bank.verify',
            'documents.view', 'documents.upload',
            'payments.view', 'payments.mark_cash',
            'projects.view', 'projects.manage',
            'reports.view', 'reports.export',
            'site.edit', 'site.publish',
            'users.manage', 'audit.read',      // the President creates every other account
        ],
        'staff' => [],
        'user' => ['family.view_own'],
    ],

    // Departments a staff member can belong to, and what each one may do.
    'departments' => [
        'aytam' => [
            'name_en' => 'Aytam (orphan care)', 'name_ar' => 'الأيتام',
            'grants' => ['children.view', 'children.create', 'children.update', 'bank.verify', 'documents.view', 'documents.upload'],
        ],
        'finance' => [
            'name_en' => 'Finance', 'name_ar' => 'المالية',
            'grants' => ['payments.view', 'payments.mark_cash', 'reports.view', 'reports.export'],
        ],
        'projects' => [
            'name_en' => 'Projects', 'name_ar' => 'المشاريع',
            'grants' => ['projects.view', 'projects.manage'],
        ],
        'relief' => ['name_en' => 'Relief', 'name_ar' => 'الإغاثة', 'grants' => []],
        'teaching' => ['name_en' => 'Teaching', 'name_ar' => 'التعليم', 'grants' => []],
        'hr' => ['name_en' => 'HR', 'name_ar' => 'الموارد البشرية', 'grants' => []],
        'administration' => ['name_en' => 'Administration', 'name_ar' => 'الإدارة', 'grants' => []],
    ],

    // Roles that may be handed out on the Accounts screen (by the President Admin, or a Security Admin once their accounts
    // feature is on). System Admin and President Admin are deliberately not on this list: only the installer makes the
    // System Admin, and only the System Admin makes and changes the President Admin.
    'assignable_by_security_admin' => ['admin_maintenance', 'security_admin', 'staff', 'user'],
];
