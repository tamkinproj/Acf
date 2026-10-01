<?php

namespace App\Install;

use App\Core\Access\RoleCatalog;
use App\Core\Access\RoleSeeder;
use App\Core\Audit\Auditor;
use App\Core\Settings\SettingsService;
use App\Models\Role;
use App\Models\SystemState;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Runs the installation. Order matters and every step is safe to repeat:
 *
 *   1 directories   2 .env   3 connect + guard   4 migrate   5 seed (ONE transaction)   6 finalize
 *
 * The lock file is written last, so a crash at any earlier point leaves the system
 * "not installed / error" and the wizard can simply be re-run; a half-finished install is never
 * mistaken for a finished one, and nothing is ever dropped or reset automatically.
 */
class Installer
{
    private ?string $secretToScrub = null;

    public function __construct(
        private InstallState $state,
        private InstallLog $log,
        private Requirements $requirements,
        private TenantContext $tenant,
    ) {}

    /** @return array{ok:bool,error:?string,summary:?array<string,string>} */
    public function run(): array
    {
        $handle = $this->state->acquireRunLock();
        if (! $handle) {
            return ['ok' => false, 'error' => 'Another installation run is already in progress.', 'summary' => null];
        }

        $step = 'start';
        try {
            if ($this->state->isInstalled()) {
                throw new RuntimeException('This system is already installed.');
            }
            $data = $this->state->data();
            $input = $this->collect($data);
            $this->secretToScrub = $input['database']->password;
            $installId = $data['install_id'] ?? (string) Str::uuid7();

            $this->state->put(['status' => 'in_progress', 'install_id' => $installId, 'started_at' => $data['started_at'] ?? now()->toIso8601String(), 'error' => null]);
            $this->log->add('install', 'started', 'Installation started', ['version' => config('foundation.version')]);

            $step = 'requirements';
            if (! $this->requirements->passes()) {
                throw new RuntimeException('System requirements are not met. Go back to the requirements step.');
            }

            $step = 'directories';
            $this->directories();

            $step = 'configuration';
            $this->writeEnv($input, includeInstallId: null);
            $this->log->add($step, 'ok', 'Configuration file written');

            $step = 'database';
            $input['database']->activate();
            $touched = (bool) ($data['db_touched'] ?? false);
            if (! $touched && $input['database']->hasExistingTables()) {
                throw new RuntimeException('The selected database is not empty. Use an empty database; the installer never overwrites existing data.');
            }
            $this->state->put(['db_touched' => true]);

            $step = 'migrations';
            Artisan::call('migrate', ['--force' => true, '--database' => $input['database']->driver]);
            $this->log->databaseReady();
            $this->log->add($step, 'ok', 'Database structure created');

            $step = 'seed';
            $summary = $this->tenant->asSystem(fn () => DB::transaction(fn () => $this->seed($input, $installId)));
            $this->log->add($step, 'ok', 'Platform role, settings and administrator created');

            $step = 'finalize';
            $this->writeEnv($input, includeInstallId: $installId);
            config(['foundation.install_id' => $installId]);
            try {
                $this->state->writeLock($installId, config('foundation.version'));
            } catch (\Throwable $e) {
                $this->writeEnv($input, includeInstallId: null);   // roll the marker back: never leave a half-lock
                config(['foundation.install_id' => null]);
                throw $e;
            }
            $this->log->add($step, 'ok', 'Installation lock created');
            $this->cleanup();

            return ['ok' => true, 'error' => null, 'summary' => $summary];
        } catch (\Throwable $e) {
            $message = $this->scrub($e->getMessage());
            $this->state->put(['status' => 'error', 'error' => ['step' => $step, 'message' => $message, 'at' => now()->toIso8601String()]]);
            $this->log->add($step, 'failed', $message, ['exception' => class_basename($e)]);

            return ['ok' => false, 'error' => "Installation failed at step '{$step}': {$message}", 'summary' => null];
        } finally {
            $this->state->releaseRunLock($handle);
        }
    }

    // ---- steps -----------------------------------------------------------

    /** @return array{database:DatabaseConfig,system:array,admin:array} */
    private function collect(array $data): array
    {
        foreach (['database', 'system', 'admin'] as $required) {
            if (empty($data[$required])) {
                throw new RuntimeException("The '{$required}' step has not been completed.");
            }
        }
        $db = $data['database'];
        $db['password'] = $this->state->secret('db_password_enc') ?? '';
        $adminPassword = $this->state->secret('admin_password_enc');
        if (! $adminPassword) {
            throw new RuntimeException('The administrator password is missing. Complete the administrator step again.');
        }

        return [
            'database' => DatabaseConfig::fromArray($db),
            'system' => $data['system'],
            'admin' => $data['admin'] + ['password' => $adminPassword],
        ];
    }

    private function directories(): void
    {
        foreach ($this->requirements->writableDirs() as $label => $dir) {
            if (! $this->requirements->ensureWritable($dir)) {
                throw new RuntimeException("The folder {$label} is not writable.");
            }
        }
        foreach (['framework/views', 'logs', 'app/private/foundation', 'app/db'] as $sub) {
            $this->requirements->ensureWritable(storage_path($sub));
        }
    }

    private function writeEnv(array $in, ?string $includeInstallId): void
    {
        $sys = $in['system'];
        $db = $in['database'];
        $values = [
            'APP_NAME' => $sys['app_name'],
            'APP_ENV' => 'production',
            'APP_KEY' => config('app.key'),
            'APP_DEBUG' => false,
            'APP_URL' => $sys['app_url'],
            // Storage is ALWAYS UTC so timestamps mean the same thing on every device and survive a change of
            // display timezone. The chosen timezone lives in the `app.timezone` setting, for display only.
            'APP_TIMEZONE' => 'UTC',
            'APP_LOCALE' => $sys['locale'],
            'APP_FALLBACK_LOCALE' => 'en',
            'DB_CONNECTION' => $db->driver,
            'SESSION_DRIVER' => 'database',
            'SESSION_LIFETIME' => 120,
            'SESSION_COOKIE' => 'foundation_session',   // never collides with another app on the same domain
            // Scope cookies (session AND the XSRF-TOKEN cookie) to the folder the app lives in, so another
            // application on the same domain neither receives them nor overwrites them.
            'SESSION_PATH' => self::cookiePath($sys['app_url']),
            'SESSION_SECURE_COOKIE' => str_starts_with($sys['app_url'], 'https://'),
            'CACHE_STORE' => 'file',
            'QUEUE_CONNECTION' => 'sync',
            'LOG_CHANNEL' => 'daily',
            'LOG_LEVEL' => 'warning',
        ];
        if ($db->driver === 'mysql') {
            $values += ['DB_HOST' => $db->host, 'DB_PORT' => $db->port, 'DB_DATABASE' => $db->database, 'DB_USERNAME' => $db->username, 'DB_PASSWORD' => $db->password];
        } else {
            $values += ['DB_DATABASE' => $db->database];
        }
        if ($includeInstallId) {
            $values['FOUNDATION_INSTALL_ID'] = $includeInstallId;
        }

        (new EnvWriter)->write($values);
    }

    /** @return array<string,string> safe-to-display summary */
    private function seed(array $in, string $installId): array
    {
        $sys = $in['system'];
        $role = app(RoleSeeder::class)->ensurePlatform();
        app(SettingsService::class)->ensureDefaults([
            'app.name' => $sys['app_name'],
            'app.timezone' => $sys['timezone'],
            'app.locale' => $sys['locale'],
        ], null, platform: true);

        $a = $in['admin'];
        $admin = User::query()->whereNull('foundation_id')->where('role_id', $role->id)->first() ?? User::create([
            'foundation_id' => null, 'name' => $a['name'], 'email' => $a['email'], 'password' => $a['password'],
            'role_id' => $role->id, 'status' => 'active', 'must_change_password' => false,
        ]);

        foreach ([
            'installed_at' => now()->toIso8601String(), 'version' => config('foundation.version'),
            'schema_version' => (int) config('foundation.schema_version'), 'install_id' => $installId,
        ] as $key => $value) {
            SystemState::put($key, $value);
        }
        app(RoleSeeder::class)->markSeen();

        app(Auditor::class)->record('system.installed', 'Platform installed', 'system', null, null,
            ['version' => config('foundation.version')], $admin);

        return [
            'platform' => $sys['app_name'],
            'administrator' => $admin->name,
            'version' => (string) config('foundation.version'),
        ];
    }

    private function cleanup(): void
    {
        $this->state->forget('db_password_enc', 'admin_password_enc');
        $this->state->put(['status' => 'installed', 'completed_at' => now()->toIso8601String()]);
        @unlink($this->state->path('token'));
        @unlink($this->state->path('app.key'));
    }

    /** "https://example.com/acf" -> "/acf"; a domain root -> "/". */
    public static function cookiePath(string $appUrl): string
    {
        $path = '/'.trim((string) parse_url($appUrl, PHP_URL_PATH), '/');

        return preg_match('#^/[A-Za-z0-9/_\-.~%]*$#', $path) ? $path : '/';
    }

    private function scrub(string $message): string
    {
        return $this->secretToScrub ? str_replace($this->secretToScrub, '********', $message) : $message;
    }
}
