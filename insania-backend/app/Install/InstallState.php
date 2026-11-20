<?php

namespace App\Install;

/**
 * What the installer remembers, as plain files (no database yet at that point).
 *   token           one-time secret the person must read from the server's files to prove they control it
 *   installing      an install started but did not finish (so it may be resumed)
 *   installed.lock  installation finished; the installer is switched off for good
 */
final class InstallState
{
    public static function dir(): string
    {
        $dir = (string) config('install.state_dir');

        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function installed(): bool
    {
        return is_file(self::dir().'/installed.lock') || self::heal();
    }

    /**
     * Re-uploading the program can remove the small "installed" marker while the settings file and the database stay.
     * If the settings file is there and its database already holds a System Admin, the system IS installed: put the marker back.
     * This only ever switches the installer OFF, never on.
     */
    private static function heal(): bool
    {
        static $failed = [];   // one look per request is enough (and per state folder, so tests stay independent)
        $key = self::dir().'|'.config('install.env_path');
        if (isset($failed[$key]) || ! is_file((string) config('install.env_path'))) {
            return false;
        }

        try {
            if (\Illuminate\Support\Facades\Schema::hasTable('users') && \Illuminate\Support\Facades\DB::table('users')->where('role', 'system_admin')->exists()) {
                self::markInstalled();
                self::clearInstalling();

                return true;
            }
        } catch (\Throwable) {
        }

        return $failed[$key] = false;
    }

    public static function markInstalled(): void
    {
        file_put_contents(self::dir().'/installed.lock', date('c'));
    }

    public static function installing(): bool
    {
        return is_file(self::dir().'/installing');
    }

    public static function markInstalling(): void
    {
        file_put_contents(self::dir().'/installing', date('c'));
    }

    public static function clearInstalling(): void
    {
        @unlink(self::dir().'/installing');
    }

    /** Creates the token file the first time it is needed. */
    public static function token(): string
    {
        $file = self::dir().'/token';

        if (! is_file($file) || trim((string) file_get_contents($file)) === '') {
            file_put_contents($file, bin2hex(random_bytes(16)));
            @chmod($file, 0600);
        }

        return trim((string) file_get_contents($file));
    }

    public static function tokenMatches(?string $given): bool
    {
        return is_string($given) && $given !== '' && hash_equals(self::token(), trim($given));
    }

    public static function clearToken(): void
    {
        @unlink(self::dir().'/token');
    }

    /** Where to tell the person to look, in words they can find in File Manager. */
    public static function tokenHint(): string
    {
        return 'storage/app/install/token';
    }
}
