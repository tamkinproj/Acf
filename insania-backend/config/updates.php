<?php

/**
 * In-app updates (System Admin > Updates, and /upgrade as a fallback).
 * A patch is a zip plus a signature file. The server only accepts a patch signed with the PRIVATE key that the owner keeps
 * offline; the matching PUBLIC key is below. Without the private key nobody can change the server's code, even with a stolen password.
 */
return [

    // ECDSA P-256 public key (PEM). Replace only together with a new private key.
    'public_key' => <<<'PEM'
-----BEGIN PUBLIC KEY-----
MFkwEwYHKoZIzj0CAQYIKoZIzj0DAQcDQgAEkqZ74P8pXJrsYY31TcpvSxjEshs+
24d9sKmv4WiQkAOJUQw0snEEYru3Du3vQ1rZydkOnQBQm2o1ayRNk2Jg+Q==
-----END PUBLIC KEY-----
PEM,

    // Where updates keep their working files (incoming uploads, backups, history).
    'dir' => storage_path('app/updates'),

    // The website folder (next to insania_app on Hostinger). Patch files under public_html/ are written here.
    'public_path' => realpath(base_path('../public_html')) ?: null,

    // Only these places may be changed by a patch. Anything else (.env, storage, vendor libraries, ...) is refused.
    'allowed' => [
        'insania_app' => ['app/', 'config/', 'routes/', 'resources/', 'database/migrations/', 'database/seeders/', 'vendor/composer/', 'BUILD', 'README.md', 'HOSTINGER.md'],
        'public_html' => ['app/', 'server/index.php', 'server/.htaccess', 'index.html', 'site.js', '.htaccess'],
    ],

    'keep_backups' => 10,
    'max_files' => 2000,
    'max_bytes' => 60 * 1024 * 1024,
];
