<?php

namespace Tests\Feature;

use App\Install\InstallState;
use App\Install\InstallStatus;
use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Foundation;
use App\Models\InstallationLog;
use App\Models\Setting;
use App\Models\SystemState;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\InstallTestCase;

class InstallerTest extends InstallTestCase
{
    public function test_full_wizard_installs_a_working_system_and_locks_itself(): void
    {
        $this->get('/install')->assertOk()->assertSee('Start Setup');
        $this->get('/install/requirements')->assertOk()->assertSee('System requirements')->assertSee('PHP 8.3.0 or newer');

        $this->completeWizard();
        $this->get('/install/review')->assertOk()->assertSee('Al-Noor Foundation')->assertSee('Aisha Santos')->assertDontSee('Sup3r-secret-pass');

        $this->post('/install/run')
            ->assertOk()
            ->assertSee('Installation Complete')->assertSee('Al-Noor Foundation')->assertSee('Aisha Santos')->assertSee('Go to Dashboard')
            ->assertDontSee('Sup3r-secret-pass');

        // ---- state & lock ----
        $state = app(InstallState::class);
        $this->assertSame(InstallStatus::Installed, $state->status());
        $this->assertFileExists($this->installDir.'/installed.lock');
        $this->assertFileDoesNotExist($this->installDir.'/token');
        $this->assertStringNotContainsString('Sup3r-secret-pass', file_get_contents($this->installDir.'/state.json'));
        $this->assertArrayNotHasKey('admin_password_enc', $state->data());

        // ---- .env ----
        $env = file_get_contents($this->envFile);
        $this->assertStringContainsString('FOUNDATION_INSTALL_ID=', $env);
        $this->assertStringContainsString('DB_CONNECTION=sqlite', $env);
        $this->assertStringContainsString('SESSION_COOKIE=foundation_session', $env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('APP_TIMEZONE=UTC', $env, 'storage is always UTC; the chosen timezone is a display setting');
        $this->assertStringNotContainsString('Sup3r-secret-pass', $env);
        $this->assertSame(0600, fileperms($this->envFile) & 0777);

        // ---- data ----
        $admin = User::query()->with('role')->sole();
        $this->assertSame('aisha@example.test', $admin->email);
        $this->assertSame('super_admin', $admin->role->key);
        $this->assertTrue(Hash::check('Sup3r-secret-pass', $admin->password));
        $this->assertNotSame('Sup3r-secret-pass', $admin->password);

        $device = Device::query()->sole();
        $this->assertMatchesRegularExpression('/^FOUNDATION-DEVICE-[A-Z2-9]{8}$/', $device->device_code);
        $this->assertTrue($device->is_primary);
        $this->assertNull($device->token_hash, 'the installer device stays unclaimed until the admin claims it');
        $this->assertSame('Main Office', $device->name);

        $foundation = Foundation::current();
        $this->assertSame('Al-Noor Foundation', $foundation->name);
        $this->assertNotNull($foundation->logo_path);
        $this->assertFileExists(storage_path('app/private/'.$foundation->logo_path));

        $this->assertSame('Asia/Manila', Setting::where('key', 'app.timezone')->value('value'));
        $this->assertSame('Al-Noor Foundation System', Setting::where('key', 'app.name')->value('value'));
        $this->assertSame(6, \App\Models\Role::count());
        $this->assertSame(config('foundation.version'), SystemState::get('version'));
        $this->assertSame($state->lock()['install_id'], SystemState::get('install_id'));
        $this->assertTrue(AuditLog::where('action', 'system.installed')->exists());

        // ---- installation log: no secrets, shows the steps ----
        $steps = InstallationLog::query()->pluck('step')->all();
        foreach (['install', 'configuration', 'migrations', 'seed', 'finalize'] as $step) {
            $this->assertContains($step, $steps);
        }
        $this->assertStringNotContainsString('Sup3r-secret-pass', json_encode(InstallationLog::all()));

        // ---- locked afterwards ----
        $this->get('/install')->assertStatus(403)->assertSee('This system is already installed.');
        $this->post('/install/run')->assertStatus(403);
        $this->post('/install/admin', ['name' => 'Mallory', 'email' => 'm@x.test', 'admin_password' => 'Another-pass-1', 'admin_password_confirmation' => 'Another-pass-1'])->assertStatus(403);
        $this->assertSame(1, User::count(), 'no second administrator can be created');
    }

    public function test_wizard_cannot_skip_steps(): void
    {
        $this->get('/install/system')->assertRedirect('/install/requirements');
        $this->get('/install/review')->assertRedirect('/install/requirements');
        $this->post('/install/run')->assertRedirect('/install/requirements');
        $this->assertSame(InstallStatus::NotInstalled, app(InstallState::class)->status());
    }

    public function test_database_step_requires_a_working_connection_before_continuing(): void
    {
        $this->post('/install/requirements');

        // MySQL on a closed port: must not advance.
        $this->post('/install/database', [
            'driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'foundation', 'username' => 'u', 'db_password' => 'p', 'action' => 'save',
        ])->assertRedirect()->assertSessionMissing('errors');
        $this->assertArrayNotHasKey('database', app(InstallState::class)->data());
        $this->get('/install/system')->assertRedirect('/install/database');

        // "Test" button never advances either, even on success.
        $this->post('/install/database', ['driver' => 'sqlite', 'sqlite_name' => $this->dbName, 'action' => 'test']);
        $this->assertArrayNotHasKey('database', app(InstallState::class)->data());
    }

    public function test_database_input_is_validated_against_injection_and_traversal(): void
    {
        $this->post('/install/requirements');
        foreach ([
            ['driver' => 'sqlite', 'sqlite_name' => '../../../etc/passwd'],
            ['driver' => 'sqlite', 'sqlite_name' => 'a/b'],
            ['driver' => 'mysql', 'host' => "127.0.0.1\nAPP_DEBUG=true", 'port' => 3306, 'database' => 'x', 'username' => 'u'],
            ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 3306, 'database' => 'x`; DROP DATABASE y;--', 'username' => 'u'],
            ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 70000, 'database' => 'x', 'username' => 'u'],
        ] as $payload) {
            $this->post('/install/database', $payload + ['action' => 'save']);
            $this->assertArrayNotHasKey('database', app(InstallState::class)->data(), json_encode($payload));
        }
        $this->assertFileDoesNotExist($this->envFile);
    }

    public function test_admin_step_enforces_password_policy_and_never_stores_plaintext(): void
    {
        $this->post('/install/requirements');
        $this->post('/install/database', ['driver' => 'sqlite', 'sqlite_name' => $this->dbName, 'action' => 'save']);
        $this->post('/install/system', ['app_name' => 'X', 'app_url' => 'https://x.test', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'PHP', 'deployment_model' => 'central']);
        $this->post('/install/foundation', ['name' => 'F']);

        foreach (['short1', 'alllettersnodigits', '1234567890123'] as $weak) {
            $this->post('/install/admin', ['name' => 'A', 'email' => 'a@x.test', 'admin_password' => $weak, 'admin_password_confirmation' => $weak]);
        }
        $this->post('/install/admin', ['name' => 'A', 'email' => 'a@x.test', 'admin_password' => 'Valid-pass-123', 'admin_password_confirmation' => 'different-123']);
        $this->assertArrayNotHasKey('admin', app(InstallState::class)->data());

        $this->post('/install/admin', ['name' => 'A', 'email' => 'a@x.test', 'admin_password' => 'Valid-pass-123', 'admin_password_confirmation' => 'Valid-pass-123'])->assertRedirect('/install/device');
        $this->assertStringNotContainsString('Valid-pass-123', file_get_contents($this->installDir.'/state.json'));
        $this->get('/install/admin')->assertDontSee('Valid-pass-123');
    }

    public function test_installer_refuses_a_non_empty_database_and_destroys_nothing(): void
    {
        @mkdir(dirname($this->dbFile), 0775, true);
        $pdo = new \PDO('sqlite:'.$this->dbFile);
        $pdo->exec('CREATE TABLE precious (id INTEGER PRIMARY KEY, note TEXT)');
        $pdo->exec("INSERT INTO precious (note) VALUES ('do not delete me')");
        $pdo = null;

        $this->completeWizard();
        $this->post('/install/run')->assertRedirect('/install/review');

        $state = app(InstallState::class);
        $this->assertSame(InstallStatus::Error, $state->status());
        $this->assertStringContainsString('not empty', $state->data()['error']['message']);
        $this->assertFileDoesNotExist($this->installDir.'/installed.lock', 'a failed install never leaves a lock');

        $check = new \PDO('sqlite:'.$this->dbFile);
        $this->assertSame('do not delete me', $check->query('SELECT note FROM precious')->fetchColumn());
        $this->assertCount(1, $check->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(), 'no foundation tables were created');

        // The wizard stays usable and explains what happened.
        $this->get('/install')->assertOk()->assertSee('previous installation attempt did not finish')->assertSee('Resume Setup');
    }

    public function test_a_failed_run_can_be_retried_and_then_succeeds(): void
    {
        $this->completeWizard();

        // Simulate a crash after the schema was built: tables exist, but no lock was written.
        $state = app(InstallState::class);
        $this->post('/install/run')->assertOk();
        @unlink($this->installDir.'/installed.lock');
        config(['foundation.install_id' => null]);
        $state->put(['status' => 'error', 'db_touched' => true, 'error' => ['step' => 'finalize', 'message' => 'simulated']]);
        $state->putSecret('admin_password_enc', 'Sup3r-secret-pass');
        $state->putSecret('db_password_enc', '');

        $this->post('/install/run')->assertOk()->assertSee('Installation Complete');
        $this->assertSame(1, User::count(), 'seeding is idempotent: no duplicate administrator');
        $this->assertSame(1, Device::count());
        $this->assertSame(1, Foundation::count());
        $this->assertSame(InstallStatus::Installed, $state->status());
    }

    public function test_installer_token_gate(): void
    {
        config(['foundation.installer_require_token' => true]);

        $this->get('/install')->assertRedirect('/install/token');
        $this->get('/install/database')->assertRedirect('/install/token');

        $page = $this->get('/install/token')->assertOk()->assertSee('storage/app/install/token');
        $this->assertFileExists($this->installDir.'/token');
        $token = trim(file_get_contents($this->installDir.'/token'));
        $this->assertGreaterThanOrEqual(32, strlen($token));

        $this->post('/install/token', ['token' => 'wrong'])->assertRedirect()->assertSessionHasErrors('token');
        $this->post('/install/token', ['token' => $token])->assertRedirect('/install');
    }

    public function test_loopback_skips_token_but_proxied_requests_do_not(): void
    {
        config(['foundation.installer_require_token' => false]);
        $this->get('/install')->assertOk();   // REMOTE_ADDR 127.0.0.1, no proxy headers

        $this->get('/install', ['X-Forwarded-For' => '203.0.113.7'])->assertRedirect('/install/token');
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.7'])->get('/install')->assertRedirect('/install/token');
    }

    public function test_logo_upload_rejects_non_images(): void
    {
        $this->post('/install/requirements');
        $this->post('/install/database', ['driver' => 'sqlite', 'sqlite_name' => $this->dbName, 'action' => 'save']);
        $this->post('/install/system', ['app_name' => 'X', 'app_url' => 'https://x.test', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'PHP', 'deployment_model' => 'central']);

        $php = \Illuminate\Http\UploadedFile::fake()->createWithContent('logo.png', '<?php system($_GET["c"]); ?>');
        $this->post('/install/foundation', ['name' => 'F', 'logo' => $php])->assertSessionHasErrors('logo');
        $this->assertArrayNotHasKey('foundation', app(InstallState::class)->data());
        $this->assertSame([], glob(storage_path('app/private/foundation/*')) ?: []);
    }

    public function test_minimal_foundation_profile_installs(): void
    {
        $this->post('/install/requirements');
        $this->post('/install/database', ['driver' => 'sqlite', 'sqlite_name' => $this->dbName, 'action' => 'save']);
        $this->post('/install/system', ['app_name' => 'X', 'app_url' => 'https://x.test', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'PHP', 'deployment_model' => 'standalone']);
        $this->post('/install/foundation', ['name' => 'Only A Name']);   // every optional field omitted entirely
        $this->post('/install/admin', ['name' => 'A', 'email' => 'a@x.test', 'admin_password' => 'Valid-pass-123', 'admin_password_confirmation' => 'Valid-pass-123']);
        $this->post('/install/device', ['name' => 'D', 'type' => 'office']);

        $this->post('/install/run')->assertOk()->assertSee('Installation Complete');
        $f = Foundation::current();
        $this->assertSame('Only A Name', $f->name);
        $this->assertNull($f->address);
        $this->assertNull($f->logo_path);
    }

    public function test_unrelated_wizard_actions_do_not_share_a_rate_limit(): void
    {
        $this->post('/install/requirements');

        // An administrator retrying the connection test many times must still be able to press "Install Now".
        for ($i = 0; $i < 12; $i++) {
            $this->post('/install/database', ['driver' => 'mysql', 'host' => '127.0.0.1', 'port' => 1, 'database' => 'x', 'username' => 'u', 'action' => 'test']);
        }
        $this->post('/install/run')->assertRedirect('/install/database')->assertStatus(302);   // gated by missing steps - NOT 429
    }
}
