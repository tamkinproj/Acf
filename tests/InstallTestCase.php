<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * For tests that drive the installer itself. Deliberately NOT RefreshDatabase: the installer creates
 * and switches to its own database, exactly as it would on a real server, then everything is removed.
 */
abstract class InstallTestCase extends BaseTestCase
{
    protected string $installDir;
    protected string $envFile;
    protected string $dbName;
    protected string $dbFile;

    protected function setUp(): void
    {
        parent::setUp();

        $tmp = sys_get_temp_dir().'/fdn-inst-'.bin2hex(random_bytes(5));
        mkdir($tmp, 0775, true);
        $this->installDir = $tmp.'/state';
        $this->envFile = $tmp.'/.env';
        $this->dbName = 'test_'.bin2hex(random_bytes(4));
        $this->dbFile = storage_path("app/db/{$this->dbName}.sqlite");

        config([
            'foundation.install_path' => $this->installDir,
            'foundation.env_file' => $this->envFile,
            'foundation.install_id' => null,
            'foundation.installer_require_token' => false,
        ]);
        mkdir($this->installDir, 0775, true);
    }

    protected function tearDown(): void
    {
        \Illuminate\Support\Facades\DB::disconnect();
        $root = dirname($this->installDir);
        foreach (glob(storage_path('app/private/foundation/logo-*.png')) ?: [] as $logo) {
            @unlink($logo);
        }
        @unlink($this->dbFile);
        $this->rrmdir($root);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        foreach (glob($dir.'/{,.}[!.]*', GLOB_BRACE) ?: [] as $f) {
            is_dir($f) ? $this->rrmdir($f) : @unlink($f);
        }
        @rmdir($dir);
    }

    /** Walk the wizard up to (not including) the final run. */
    protected function completeWizard(array $overrides = []): void
    {
        $this->post('/install/requirements')->assertRedirect('/install/database');
        $this->post('/install/database', ['driver' => 'sqlite', 'sqlite_name' => $this->dbName, 'action' => 'save'])->assertRedirect('/install/system');
        $this->post('/install/system', ($overrides['system'] ?? []) + [
            'app_name' => 'Al-Noor Foundation System', 'app_url' => 'https://foundation.example.com', 'timezone' => 'Asia/Manila',
            'locale' => 'en',
        ])->assertRedirect('/install/admin');
        $this->post('/install/admin', ($overrides['admin'] ?? []) + [
            'name' => 'Aisha Santos', 'email' => 'Aisha@Example.test', 'admin_password' => 'Sup3r-secret-pass', 'admin_password_confirmation' => 'Sup3r-secret-pass',
        ])->assertRedirect('/install/review');
    }
}
