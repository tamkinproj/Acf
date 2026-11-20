<?php

/**
 * Settings for the web installer (/install) and the web upgrade page (/upgrade).
 * They exist because many hosts (Hostinger with only File Manager, for example) give no terminal.
 */
return [

    // The database type the installer sets up. Real servers use MySQL / MariaDB; tests switch this to sqlite.
    'driver' => 'mysql',

    // Where the installer keeps its one-time token and its "installed" lock.
    'state_dir' => storage_path('app/install'),

    // Where the installer writes the finished configuration.
    'env_path' => base_path('.env'),

    'min_php' => '8.1.0',

    // PHP extensions the app needs (the database one is added from 'driver').
    'extensions' => ['mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo'],
];
