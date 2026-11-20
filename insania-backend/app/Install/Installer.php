<?php

namespace App\Install;

use App\Services\FoundationBootstrap;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/** Does the actual installation: database check, tables, first System Admin, settings file, lock. */
final class Installer
{
    /** Points the app at the database the person typed in, for this request only. */
    public static function useConnection(array $in): void
    {
        $driver = config('install.driver');
        $conn = $driver === 'sqlite'
            ? ['driver' => 'sqlite', 'database' => $in['db_name'], 'prefix' => '', 'foreign_key_constraints' => true]
            : [
                'driver' => 'mysql', 'host' => $in['db_host'], 'port' => $in['db_port'] ?: '3306', 'database' => $in['db_name'],
                'username' => $in['db_user'], 'password' => $in['db_pass'] ?? '', 'charset' => 'utf8mb4', 'collation' => 'utf8mb4_unicode_ci',
                'prefix' => '', 'strict' => true, 'engine' => null,
            ];

        config(['database.connections.install' => $conn, 'database.default' => 'install']);
        DB::purge('install');
    }

    /** @return string|null a plain-language problem, or null when the database can be used */
    public static function databaseProblem(): ?string
    {
        try {
            DB::connection('install')->getPdo();
        } catch (\Throwable $e) {
            return 'Could not connect to the database. Check the host, name, user and password. (Never shown here: the password.)';
        }

        if (Schema::connection('install')->hasTable('users') && ! InstallState::installing()) {
            return 'This database already has tables in it. Use a new, empty database so nothing is overwritten.';
        }

        return null;
    }

    public static function run(array $in): void
    {
        InstallState::markInstalling();

        $key = 'base64:'.base64_encode(random_bytes(32));
        config(['app.key' => $key]);

        Artisan::call('migrate', ['--force' => true, '--database' => 'install']);

        (new FoundationBootstrap)->make($in['foundation'], $in['admin_name'], $in['admin_email'], $in['admin_password']);

        self::writeEnv($in, $key);

        InstallState::markInstalled();
        InstallState::clearInstalling();
        InstallState::rotateToken();
    }

    /** @return string|null a plain-language problem, or null when this database really holds an installed system */
    public static function reconnectProblem(): ?string
    {
        try {
            DB::connection('install')->getPdo();
        } catch (\Throwable) {
            return 'Could not connect to the database. Check the host, name, user and password.';
        }
        $c = DB::connection('install');
        if (! Schema::connection('install')->hasTable('users') || ! $c->table('users')->where('role', 'system_admin')->exists()) {
            return 'This database does not hold an installed system (no System Admin found). Use the normal install form above with an empty database instead.';
        }

        return null;
    }

    /** Writes the settings file and the installed marker again for a database that is already installed. Touches no data. */
    public static function reconnect(array $in): void
    {
        $key = 'base64:'.base64_encode(random_bytes(32));       // nothing stored depends on the key: sign-in tokens are hashed
        self::writeEnv($in, $key);
        InstallState::markInstalled();
        InstallState::clearInstalling();
        InstallState::rotateToken();
    }

    public static function writeEnv(array $in, string $key): void
    {
        $driver = config('install.driver');
        $lines = [
            'APP_NAME' => self::q($in['foundation']),
            'APP_ENV' => 'production',
            'APP_KEY' => $key,
            'APP_DEBUG' => 'false',
            'APP_URL' => self::q($in['app_url']),
            'LOG_CHANNEL' => 'stack',
            'LOG_LEVEL' => 'error',
            'DB_CONNECTION' => $driver,
        ];
        if ($driver === 'sqlite') {
            $lines['DB_DATABASE'] = self::q($in['db_name']);
        } else {
            $lines += ['DB_HOST' => self::q($in['db_host'] ?? 'localhost'), 'DB_PORT' => self::q(($in['db_port'] ?? '') ?: '3306'), 'DB_DATABASE' => self::q($in['db_name']),
                'DB_USERNAME' => self::q($in['db_user'] ?? ''), 'DB_PASSWORD' => self::q($in['db_pass'] ?? '')];
        }
        $lines += ['CACHE_DRIVER' => 'file', 'SESSION_DRIVER' => 'file', 'QUEUE_CONNECTION' => 'sync', 'FILESYSTEM_DISK' => 'local',
            'SANCTUM_EXPIRATION' => '720', 'CORS_ALLOWED_ORIGINS' => self::q(($in['cors'] ?? '') ?: '*')];

        $text = '';
        foreach ($lines as $k => $v) {
            $text .= "$k=$v\n";
        }

        $path = (string) config('install.env_path');
        file_put_contents($path, $text, LOCK_EX);
        @chmod($path, 0640);
    }

    /** Quotes a value for the settings file so spaces, #, $ and quotes cannot change its meaning. */
    private static function q(string $v): string
    {
        $v = str_replace(["\r", "\n"], '', $v);

        return '"'.str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $v).'"';
    }

    public static function upgrade(): string
    {
        Artisan::call('migrate', ['--force' => true]);
        foreach (['config:clear', 'route:clear', 'view:clear', 'cache:clear'] as $c) {
            try {
                Artisan::call($c);
            } catch (\Throwable) {
            }
        }
        InstallState::rotateToken();

        return trim(Artisan::output());
    }

    public static function pending(): int
    {
        $m = app('migrator');
        $files = $m->getMigrationFiles($m->paths() + [database_path('migrations')]);
        $ran = $m->getRepository()->repositoryExists() ? $m->getRepository()->getRan() : [];

        return count(array_diff(array_keys($files), $ran));
    }
}
