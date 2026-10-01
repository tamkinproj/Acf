<?php

namespace App\Install;

use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

/**
 * File-backed installation state.
 *
 * Why files, not the database: the installer must be able to answer "am I
 * installed?" before any database exists, and must keep answering correctly
 * if the database is down or wiped. The files live under storage/ (never
 * web-served):
 *
 *   state.json      wizard answers + progress (DB password stored encrypted)
 *   installed.lock  written last; signed with APP_KEY; its presence = INSTALLED
 *   token           one-time installer access token (see InstallerAccess)
 *   app.key         ephemeral APP_KEY used until the installer writes .env
 *
 * The lock's install_id must match FOUNDATION_INSTALL_ID in .env. A lock
 * without a matching .env id (or an .env id without a lock) is CORRUPT and
 * produces a recovery screen - the installer never "fixes" it by re-running.
 */
class InstallState
{
    private ?string $corruptReason = null;

    public function path(string $file = ''): string
    {
        $base = rtrim((string) config('foundation.install_path'), '/\\');

        return $file === '' ? $base : $base.DIRECTORY_SEPARATOR.$file;
    }

    public function status(): InstallStatus
    {
        $this->corruptReason = null;
        $envId = config('foundation.install_id');

        if (is_file($this->path('installed.lock'))) {
            $lock = $this->lock();

            if ($lock === null) {
                return $this->corrupt('The installation lock file is unreadable, altered, or was signed with a different application key.');
            }
            if (! $envId || ! hash_equals((string) $lock['install_id'], (string) $envId)) {
                return $this->corrupt('The installation lock does not match this environment configuration (.env).');
            }

            return InstallStatus::Installed;
        }

        if ($envId) {
            return $this->corrupt('This environment is configured as installed but the installation lock file is missing.');
        }

        return match ($this->data()['status'] ?? null) {
            'in_progress' => InstallStatus::InProgress,
            'error' => InstallStatus::Error,
            default => InstallStatus::NotInstalled,
        };
    }

    public function corruptReason(): ?string
    {
        return $this->corruptReason;
    }

    private function corrupt(string $reason): InstallStatus
    {
        $this->corruptReason = $reason;

        return InstallStatus::Corrupt;
    }

    public function isInstalled(): bool
    {
        return $this->status() === InstallStatus::Installed;
    }

    // ---- wizard data --------------------------------------------------

    /** @return array<string,mixed> */
    public function data(): array
    {
        return $this->readJson('state.json') ?? [];
    }

    /** Merge top-level keys into state.json. */
    public function put(array $values): void
    {
        $this->ensureDir();
        $this->writeAtomic('state.json', json_encode(array_replace($this->data(), $values), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function forget(string ...$keys): void
    {
        $data = $this->data();
        foreach ($keys as $key) {
            unset($data[$key]);
        }
        $this->ensureDir();
        $this->writeAtomic('state.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function putSecret(string $key, string $value): void
    {
        $this->put([$key => Crypt::encryptString($value)]);
    }

    public function secret(string $key): ?string
    {
        $raw = $this->data()[$key] ?? null;
        if (! is_string($raw) || $raw === '') {
            return null;
        }
        try {
            return Crypt::decryptString($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    // ---- lock ---------------------------------------------------------

    /** @return array{install_id:string,version:string,installed_at:string,sig:string}|null null when absent or invalid */
    public function lock(): ?array
    {
        $lock = $this->readJson('installed.lock');
        if (! is_array($lock) || ! isset($lock['install_id'], $lock['version'], $lock['installed_at'], $lock['sig'])) {
            return null;
        }

        return hash_equals($this->sign($lock['install_id'], $lock['version'], $lock['installed_at']), (string) $lock['sig']) ? $lock : null;
    }

    public function writeLock(string $installId, string $version): void
    {
        $at = now()->toIso8601String();
        $this->writeAtomic('installed.lock', json_encode([
            'install_id' => $installId,
            'version' => $version,
            'installed_at' => $at,
            'sig' => $this->sign($installId, $version, $at),
        ], JSON_PRETTY_PRINT));
        @chmod($this->path('installed.lock'), 0440);
    }

    private function sign(string $installId, string $version, string $at): string
    {
        return hash_hmac('sha256', "{$installId}|{$version}|{$at}", (string) config('app.key'));
    }

    // ---- installer access token ----------------------------------------

    public function token(): string
    {
        $file = $this->path('token');
        if (! is_file($file)) {
            $this->ensureDir();
            $this->writeAtomic('token', Str::random(32));
            @chmod($file, 0600);
        }

        return trim((string) file_get_contents($file));
    }

    // ---- pre-install APP_KEY ------------------------------------------

    /** Returns an APP_KEY usable before .env exists, creating one if needed. */
    public function bootstrapKey(): string
    {
        $file = $this->path('app.key');
        if (! is_file($file)) {
            $this->ensureDir();
            $this->writeAtomic('app.key', 'base64:'.base64_encode(random_bytes(32)));
            @chmod($file, 0600);
        }

        return trim((string) file_get_contents($file));
    }

    // ---- exclusive run lock ---------------------------------------------

    /** @return resource|null a held flock handle, or null if another run holds it */
    public function acquireRunLock()
    {
        $this->ensureDir();
        $handle = fopen($this->path('run.lock'), 'c');
        if ($handle && flock($handle, LOCK_EX | LOCK_NB)) {
            return $handle;
        }
        if ($handle) {
            fclose($handle);
        }

        return null;
    }

    public function releaseRunLock($handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    // ---- io -----------------------------------------------------------

    public function ensureDir(): void
    {
        if (! is_dir($this->path())) {
            @mkdir($this->path(), 0775, true);
        }
    }

    private function readJson(string $file): ?array
    {
        $full = $this->path($file);
        if (! is_file($full)) {
            return null;
        }
        $decoded = json_decode((string) @file_get_contents($full), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function writeAtomic(string $file, string $contents): void
    {
        $full = $this->path($file);
        $tmp = $full.'.'.bin2hex(random_bytes(4)).'.tmp';
        if (file_put_contents($tmp, $contents, LOCK_EX) === false || ! @rename($tmp, $full)) {
            @unlink($tmp);
            throw new \RuntimeException("Cannot write installer file [{$file}]. Check that the storage directory is writable.");
        }
    }
}
