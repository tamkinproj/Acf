<?php

namespace App\Install;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Validated database settings from the wizard, plus connection testing.
 *
 * Every value is validated here (not just in the form request) because the
 * same object feeds the .env writer; nothing user-supplied reaches .env or a
 * DSN without passing through this class.
 */
final class DatabaseConfig
{
    private function __construct(
        public readonly string $driver,        // mysql | sqlite
        public readonly string $host,
        public readonly int $port,
        public readonly string $database,
        public readonly string $username,
        public readonly string $password,
    ) {}

    public static function mysql(string $host, int|string $port, string $database, string $username, string $password): self
    {
        if (! preg_match('/^[A-Za-z0-9]([A-Za-z0-9.\-]{0,251}[A-Za-z0-9])?$|^\[[0-9a-fA-F:]+\]$/', $host) && ! filter_var($host, FILTER_VALIDATE_IP)) {
            throw new InvalidArgumentException('Database host is not a valid hostname or IP address.');
        }
        $port = (int) $port;
        if ($port < 1 || $port > 65535) {
            throw new InvalidArgumentException('Database port must be between 1 and 65535.');
        }
        if (! preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $database)) {
            throw new InvalidArgumentException('Database name may only contain letters, numbers, underscores and hyphens (max 64).');
        }
        self::assertCredential('Database username', $username, 80, required: true);
        self::assertCredential('Database password', $password, 200, required: false);

        return new self('mysql', $host, $port, $database, $username, $password);
    }

    /** SQLite lives in storage/app/db/<name>.sqlite - only a bare file name is accepted (no path traversal). */
    public static function sqlite(string $name): self
    {
        if (! preg_match('/^[A-Za-z0-9_\-]{1,64}$/', $name)) {
            throw new InvalidArgumentException('SQLite database name may only contain letters, numbers, underscores and hyphens.');
        }

        return new self('sqlite', '', 0, storage_path("app/db/{$name}.sqlite"), '', '');
    }

    public static function fromArray(array $a): self
    {
        return ($a['driver'] ?? 'mysql') === 'sqlite'
            ? self::sqlite((string) ($a['sqlite_name'] ?? 'foundation'))
            : self::mysql((string) ($a['host'] ?? ''), $a['port'] ?? 3306, (string) ($a['database'] ?? ''), (string) ($a['username'] ?? ''), (string) ($a['password'] ?? ''));
    }

    private static function assertCredential(string $label, string $value, int $max, bool $required): void
    {
        if ($required && $value === '') {
            throw new InvalidArgumentException("{$label} is required.");
        }
        if (strlen($value) > $max || preg_match('/[\x00-\x1F\x7F]/', $value)) {
            throw new InvalidArgumentException("{$label} contains invalid characters or is too long.");
        }
    }

    /** Laravel connection array for runtime use. */
    public function connection(): array
    {
        if ($this->driver === 'sqlite') {
            return [
                'driver' => 'sqlite',
                'database' => $this->database,
                'prefix' => '',
                'foreign_key_constraints' => true,
            ];
        }

        return [
            'driver' => 'mysql',
            'host' => $this->host,
            'port' => $this->port,
            'database' => $this->database,
            'username' => $this->username,
            'password' => $this->password,
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
            'options' => [\PDO::ATTR_TIMEOUT => 5],
        ];
    }

    /** Switch the running application onto this database. */
    public function activate(): void
    {
        config([
            "database.connections.{$this->driver}" => $this->connection(),
            'database.default' => $this->driver,
        ]);
        DB::purge($this->driver);
    }

    /** @return array{ok:bool,message:string} Never leaks the password or raw driver DSN. */
    public function test(): array
    {
        if ($this->driver === 'mysql' && ! extension_loaded('pdo_mysql')) {
            return ['ok' => false, 'message' => 'The pdo_mysql PHP extension is not installed.'];
        }
        if ($this->driver === 'sqlite') {
            if (! extension_loaded('pdo_sqlite')) {
                return ['ok' => false, 'message' => 'The pdo_sqlite PHP extension is not installed.'];
            }
            $dir = dirname($this->database);
            if (! is_dir($dir) && ! @mkdir($dir, 0775, true)) {
                return ['ok' => false, 'message' => 'Cannot create the SQLite storage folder (storage/app/db). Check permissions.'];
            }
            if (! is_writable($dir)) {
                return ['ok' => false, 'message' => 'The SQLite storage folder (storage/app/db) is not writable.'];
            }
            // Laravel's SQLite connector requires the file to exist; an empty file is a valid empty database.
            if (! is_file($this->database) && @touch($this->database) === false) {
                return ['ok' => false, 'message' => 'Cannot create the SQLite database file.'];
            }
            @chmod($this->database, 0640);
        }

        $name = '_install_test_'.bin2hex(random_bytes(3));
        config(["database.connections.{$name}" => $this->connection()]);
        try {
            $pdo = DB::connection($name)->getPdo();
            if ($this->driver === 'mysql') {
                $version = (string) $pdo->getAttribute(\PDO::ATTR_SERVER_VERSION);

                return ['ok' => true, 'message' => "Connection successful (server {$version})."];
            }

            return ['ok' => true, 'message' => 'SQLite database is ready.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => self::friendlyError($e)];
        } finally {
            DB::purge($name);
            config(["database.connections.{$name}" => null]);
        }
    }

    private static function friendlyError(\Throwable $e): string
    {
        $m = $e->getMessage();

        return match (true) {
            str_contains($m, '[1045]') => 'Access denied. Check the database username and password.',
            str_contains($m, '[1049]') => 'The database does not exist. Create an empty database first, then try again.',
            str_contains($m, '[2002]'), str_contains($m, 'getaddrinfo') => 'Cannot reach the database server. Check the host and port.',
            str_contains($m, '[1044]') => 'This user is not allowed to use that database.',
            default => 'Could not connect to the database. Check the host, port, name and credentials.',
        };
    }

    /** True when the target database already contains tables (installer refuses to overwrite). */
    public function hasExistingTables(): bool
    {
        $name = '_install_probe_'.bin2hex(random_bytes(3));
        config(["database.connections.{$name}" => $this->connection()]);
        try {
            $schema = DB::connection($name)->getSchemaBuilder();

            return count($schema->getTableListing()) > 0;
        } finally {
            DB::purge($name);
            config(["database.connections.{$name}" => null]);
        }
    }
}
