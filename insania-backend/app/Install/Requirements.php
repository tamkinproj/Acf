<?php

namespace App\Install;

/** The checks shown before installing, so a missing PHP extension or a folder that cannot be written is clear up front. */
final class Requirements
{
    /** @return list<array{label: string, ok: bool, detail: string}> */
    public static function check(): array
    {
        $min = (string) config('install.min_php');
        $rows = [['label' => "PHP $min or newer", 'ok' => version_compare(PHP_VERSION, $min, '>='), 'detail' => 'This server runs PHP '.PHP_VERSION]];

        $needed = config('install.extensions');
        $needed[] = config('install.driver') === 'sqlite' ? 'pdo_sqlite' : 'pdo_mysql';
        foreach ($needed as $ext) {
            $ok = extension_loaded($ext);
            $rows[] = ['label' => "PHP extension: $ext", 'ok' => $ok, 'detail' => $ok ? 'Available' : 'Missing. Turn it on in hPanel, PHP Configuration.'];
        }

        $env = (string) config('install.env_path');
        $envDir = dirname($env);
        foreach ([
            'Folder storage' => storage_path(),
            'Folder bootstrap/cache' => base_path('bootstrap/cache'),
            'Folder for the settings file' => $envDir,
        ] as $label => $path) {
            $ok = is_dir($path) && is_writable($path);
            $rows[] = ['label' => $label.' can be written', 'ok' => $ok, 'detail' => $ok ? 'Yes' : 'No. Right-click the folder, Permissions, set 755 or 775.'];
        }
        if (is_file($env)) {
            $rows[] = ['label' => 'Settings file can be replaced', 'ok' => is_writable($env), 'detail' => is_writable($env) ? 'Yes' : 'No. Set the file permission to 644 or delete the old file.'];
        }

        return $rows;
    }

    public static function pass(): bool
    {
        foreach (self::check() as $row) {
            if (! $row['ok']) {
                return false;
            }
        }

        return true;
    }
}
