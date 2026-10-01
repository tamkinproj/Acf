<?php

namespace App\Install;

/**
 * System requirement checks shown in step 1 of the wizard.
 * Each check: ['key','label','ok','required','detail'] - `detail` tells the
 * administrator what to fix when a check fails.
 */
class Requirements
{
    public const MIN_PHP = '8.3.0';

    public const EXTENSIONS = ['mbstring', 'openssl', 'json', 'ctype', 'tokenizer', 'xml', 'fileinfo', 'pdo'];

    /** @return list<array{key:string,label:string,ok:bool,required:bool,detail:string}> */
    public function checks(): array
    {
        $checks = [];

        $checks[] = $this->check('php', 'PHP '.self::MIN_PHP.' or newer', version_compare(PHP_VERSION, self::MIN_PHP, '>='),
            'Running PHP '.PHP_VERSION.'. Upgrade PHP to '.self::MIN_PHP.' or newer.');

        foreach (self::EXTENSIONS as $ext) {
            $checks[] = $this->check("ext-{$ext}", "PHP extension: {$ext}", extension_loaded($ext),
                "Enable the '{$ext}' PHP extension in your hosting control panel or php.ini.");
        }

        $checks[] = $this->check('ext-gd', 'PHP extension: gd (logo processing)', extension_loaded('gd'),
            "Enable the 'gd' extension, or the foundation logo cannot be uploaded.", required: false);

        $checks[] = $this->check('ext-zip', 'PHP extension: zip (Excel import)', extension_loaded('zip') && extension_loaded('xmlreader'),
            "Enable the 'zip' and 'xmlreader' extensions, or Excel (.xlsx) files cannot be imported. CSV files still work.", required: false);

        $mysql = extension_loaded('pdo_mysql');
        $sqlite = extension_loaded('pdo_sqlite');
        $checks[] = $this->check('db-driver', 'Database driver (pdo_mysql or pdo_sqlite)', $mysql || $sqlite,
            'Enable pdo_mysql (MySQL/MariaDB) or pdo_sqlite (single-device installs).');
        $checks[] = $this->check('db-mysql', 'MySQL / MariaDB driver (pdo_mysql)', $mysql,
            'Only needed if you will use MySQL or MariaDB.', required: false);
        $checks[] = $this->check('db-sqlite', 'SQLite driver (pdo_sqlite)', $sqlite,
            'Only needed for standalone SQLite installs.', required: false);

        $exposed = $this->appFolderIsPublic();
        $checks[] = $this->check('private-app', 'Application folder is outside the public web folder', ! $exposed,
            'The application folder is inside your website\'s public folder, so anyone could download its files, including the database password the installer will write. '
            .'Move the application folder (foundation_app) OUT of public_html, next to it, and leave only the public files in the web folder.');

        foreach ($this->writableDirs() as $label => $dir) {
            $ok = $this->ensureWritable($dir);
            $checks[] = $this->check('dir-'.md5($dir), "Writable: {$label}", $ok,
                "Make '{$dir}' writable by the web server user (e.g. chmod 775).");
        }

        $envFile = base_path('.env');
        $envOk = is_file($envFile) ? is_writable($envFile) : is_writable(base_path());
        $checks[] = $this->check('env', 'Configuration file (.env) can be written', $envOk,
            'The web server must be able to create or modify the .env file in the application root.');

        $checks[] = $this->check('key', 'Application key available', (bool) config('app.key'),
            'The installer generates a temporary key automatically; if this fails, storage/app/install is not writable.');

        $upload = $this->iniBytes(ini_get('upload_max_filesize'));
        $post = $this->iniBytes(ini_get('post_max_size'));
        $checks[] = $this->check('uploads', 'File uploads enabled (>= 2 MB)', (bool) ini_get('file_uploads') && $upload >= 2 * 1024 * 1024 && $post >= 2 * 1024 * 1024,
            'Raise upload_max_filesize and post_max_size to at least 2M, and enable file_uploads.', required: false);

        $driver = (string) config('session.driver');
        $checks[] = $this->check('session', 'Session storage', in_array($driver, ['file', 'database', 'array', 'cookie'], true),
            "Unsupported session driver '{$driver}'.");

        return $checks;
    }

    /**
     * True when the folder holding .env/vendor/storage lives under the web server's document root.
     * (Normal layouts keep it OUTSIDE: the document root is public/ or public_html/ only.) Skipped when
     * there is no web document root, e.g. on the command line.
     */
    public function appFolderIsPublic(): bool
    {
        $docRoot = (string) request()->server('DOCUMENT_ROOT', '');
        $docRoot = $docRoot !== '' ? realpath($docRoot) : false;
        $app = realpath(base_path());
        if ($docRoot === false || $app === false) {
            return false;
        }
        $docRoot = rtrim($docRoot, '/\\').DIRECTORY_SEPARATOR;

        return str_starts_with(rtrim($app, '/\\').DIRECTORY_SEPARATOR, $docRoot);
    }

    public function passes(): bool
    {
        foreach ($this->checks() as $check) {
            if ($check['required'] && ! $check['ok']) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string,string> */
    public function writableDirs(): array
    {
        return [
            'storage/' => storage_path(),
            'storage/app' => storage_path('app'),
            'storage/app/install' => config('foundation.install_path'),
            'storage/app/private' => storage_path('app/private'),
            'storage/framework' => storage_path('framework'),
            'storage/framework/sessions' => storage_path('framework/sessions'),
            'storage/framework/cache' => storage_path('framework/cache'),
            'storage/logs' => storage_path('logs'),
            'bootstrap/cache' => base_path('bootstrap/cache'),
        ];
    }

    /** Create the directory if absent, then test it is writable. */
    public function ensureWritable(string $dir): bool
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return is_dir($dir) && is_writable($dir);
    }

    private function check(string $key, string $label, bool $ok, string $detail, bool $required = true): array
    {
        return compact('key', 'label', 'ok', 'required', 'detail');
    }

    private function iniBytes(string|false $value): int
    {
        if ($value === false || $value === '') {
            return 0;
        }
        $n = (int) $value;

        return match (strtolower(substr(trim($value), -1))) {
            'g' => $n * 1024 ** 3,
            'm' => $n * 1024 ** 2,
            'k' => $n * 1024,
            default => $n,
        };
    }
}
