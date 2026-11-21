<?php

/**
 * Sign-in and session safety. Every value can be changed here (a small signed patch).
 *
 * "Privileged" roles can change who has access, see all money and orphan records, or run the system: they get the strictest limits.
 */
return [
    'privileged_roles' => ['system_admin', 'security_admin', 'president_admin'],

    // A sign-in that is not used for this long is ended (the person signs in again; unsent changes on the phone are kept).
    'idle_minutes' => ['privileged' => 60, 'default' => 240],

    // A sign-in never lasts longer than this, however busy.
    'max_hours' => ['privileged' => 8, 'default' => 12],

    // Wrong passwords for ONE account, from any address, before that account is locked for a while.
    'lockout' => ['attempts' => 10, 'minutes' => 15],

    // Asking for the person's own password again before account changes (new accounts, roles, switching off, email, password resets).
    'step_up' => ['attempts' => 5, 'minutes' => 5],
];
