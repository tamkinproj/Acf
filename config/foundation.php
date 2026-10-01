<?php

return [

    /*
    | Product identity and versioning. `version` is the application release;
    | `schema_version` is bumped whenever a release ships migrations, and is
    | stored in system_state at install/upgrade time so a code/DB mismatch is
    | detectable (see `php artisan foundation:status`).
    */
    'name' => 'Foundation Management System',
    'version' => '1.0.0',
    'schema_version' => 1,

    /*
    | Where installer state lives. Deliberately a plain directory (not the
    | database) so the app can tell "installed / not installed" before any
    | database exists. Never web-accessible: it lives under storage/.
    */
    'install_path' => env('FOUNDATION_INSTALL_PATH', storage_path('app/install')),

    /*
    | Set by the installer in .env. Used to cross-check the install lock so a
    | missing or swapped lock file is detected as corruption, not ignored.
    */
    'install_id' => env('FOUNDATION_INSTALL_ID'),

    // Where the installer writes configuration. Overridable so tests never touch a real .env.
    'env_file' => env('FOUNDATION_ENV_FILE', base_path('.env')),

    // Force the access token even for loopback requests (set true on any server reachable through a proxy).
    'installer_require_token' => (bool) env('FOUNDATION_INSTALLER_REQUIRE_TOKEN', false),

    'deployment_models' => ['central', 'local_server', 'standalone'],

    'device' => [
        'types' => ['office', 'field', 'mobile', 'server', 'other'],
        'code_prefix' => 'FOUNDATION-DEVICE-',
        'token_prefix' => 'fdt_',
        // A device is shown as "online" if it synced within this many minutes.
        'online_window_minutes' => 15,
    ],

    'sync' => [
        // Max changes accepted per push request.
        'max_push_batch' => 200,
        // Max changes returned per pull page.
        'pull_page_size' => 500,
        'max_pull_page_size' => 1000,
        // Rows newer than this are withheld from pull so a slow, concurrently
        // committing transaction with a lower `seq` can't be skipped by a
        // client whose cursor already moved past it.
        'settle_seconds' => (int) env('FOUNDATION_SYNC_SETTLE_SECONDS', 2),
    ],

    'auth' => [
        'password_min_length' => 10,
        'login_max_attempts' => 5,
        'login_decay_seconds' => 60,
    ],

    'uploads' => [
        'logo_max_kb' => 2048,
        'logo_max_pixels' => 4000,
    ],
];
