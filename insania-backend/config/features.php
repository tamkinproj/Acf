<?php

/**
 * Office features, each with an on/off switch.
 *
 * A switch is false (off), true (on for every role that has the feature), or a list of roles it is on for,
 * e.g. ['president_admin'] to turn a feature on for the President Admin first.
 *
 * A feature that is OFF is gone for everyone: its menu, screens and dashboard cards disappear, and the server
 * refuses every request for it (403), whatever the role. Nothing is deleted: the records stay in the database and
 * the feature comes back exactly as it was when its switch is set to true (a small signed patch).
 *
 * The System Admin's own tools (system health, settings, partner keys, updates, creating and changing the
 * President Admin) are not office features and are never switched off here. Everyone can always sign in and
 * change their own password.
 */
return [

    'switches' => [
        'children' => false,       // Aytam: children's records, bank account checks, documents
        'payments' => false,       // monthly sponsorship payments, cash received
        'projects' => false,       // projects and their progress
        'reports' => false,        // report summaries and exports
        'website' => false,        // editing and publishing the public landing page (visitors still see what was published)
        'accounts' => ['president_admin'],  // ON for the President Admin: creating every other account (Security Admin, Admin Maintenance,
                                            // staff by department), changing roles, resetting passwords, ending sessions.
                                            // The Security Admin's account tools stay off until decided.
        'activity_log' => false,   // reading the activity (audit) log
        'family' => false,         // a family's own account
    ],

    // Which permissions each feature brings. A permission listed here exists only while its feature is on.
    'permissions' => [
        'children' => ['children.view', 'children.create', 'children.update', 'bank.verify', 'documents.view', 'documents.upload'],
        'payments' => ['payments.view', 'payments.mark_cash'],
        'projects' => ['projects.view', 'projects.manage'],
        'reports' => ['reports.view', 'reports.export'],
        'website' => ['site.edit', 'site.publish'],
        'accounts' => ['users.manage'],
        'activity_log' => ['audit.read'],
        'family' => ['family.view_own'],
    ],
];
