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
        'children' => ['staff', 'president_admin'],   // orphan records, profile pages, bank account checks, documents. Staff: only the Aytam
                                                     // department holds these permissions. The President holds them too.
        'applications' => ['staff', 'president_admin'], // the public application form's review queue and the orphan photos (Aytam + President)
        'payments' => ['staff', 'president_admin'],   // monthly sponsorship payments, cash received (Finance department + President)
        'projects' => ['staff', 'president_admin'],   // projects and their progress (Projects department + President)
        'reports' => ['staff', 'president_admin'],    // report summaries and exports (Finance department + President)
        'website' => ['president_admin'],             // editing and publishing the public landing page (President only)
        'accounts' => ['president_admin'],            // creating every other account, changing roles, resetting passwords, ending sessions.
                                                      // The Security Admin's account tools stay off until decided.
        'activity_log' => ['president_admin'],        // reading the activity (audit) log (President only)
        'chat' => ['staff', 'president_admin'],        // the office chat: General group and one-to-one chats (President + all staff; never the system roles)
        'family' => false,                            // a family's own account: off for now
    ],

    // The public application form (alinsania.com/apply): true = people can send applications, false = the form says it is closed.
    'public_apply' => true,

    // Which permissions each feature brings. A permission listed here exists only while its feature is on.
    'permissions' => [
        'children' => ['children.view', 'children.create', 'children.update', 'bank.verify', 'documents.view', 'documents.upload'],
        'applications' => ['applications.review'],
        'payments' => ['payments.view', 'payments.mark_cash'],
        'projects' => ['projects.view', 'projects.manage'],
        'reports' => ['reports.view', 'reports.export'],
        'website' => ['site.edit', 'site.publish'],
        'accounts' => ['users.manage'],
        'activity_log' => ['audit.read'],
        'chat' => ['chat.use'],
        'family' => ['family.view_own'],
    ],
];
