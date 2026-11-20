<?php

namespace Tests\Feature;

use App\Install\InstallState;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    private string $dir;

    private string $db;

    private string $env;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/insania-inst-'.bin2hex(random_bytes(5));
        mkdir($this->dir);
        $this->db = $this->dir.'/data.sqlite';
        $this->env = $this->dir.'/.env';
        config(['install.state_dir' => $this->dir.'/state', 'install.env_path' => $this->env]);
    }

    private function input(array $over = []): array
    {
        touch($this->db);

        return $over + [
            'token' => InstallState::token(), 'foundation' => 'Test Foundation', 'app_url' => 'https://example.org/api',
            'cors' => 'https://example.org', 'db_name' => $this->db,
            'admin_name' => 'Root Admin', 'admin_email' => 'root@example.org',
            'admin_password' => 'Str0ng#Passw0rd', 'admin_password_confirmation' => 'Str0ng#Passw0rd',
        ];
    }

    public function test_api_says_not_installed_until_installed(): void
    {
        $this->getJson('/api/me')->assertStatus(503)->assertJsonPath('code', 'NOT_INSTALLED');
        $this->getJson('/api/ping')->assertOk()->assertJsonPath('data.installed', false);
    }

    public function test_a_browser_visit_before_install_goes_to_the_installer(): void
    {
        $this->get('/')->assertRedirect('/install');
    }

    public function test_the_home_address_is_friendly_once_installed(): void
    {
        InstallState::markInstalled();
        $this->get('/')->assertOk()->assertSee('Insania server is running');
    }

    public function test_forms_post_back_to_their_own_address_so_a_subfolder_works(): void
    {
        // The server lives at /server/ on the real site; a form pointing at "/install" would leave that folder.
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*action="\//', $this->get('/install')->getContent());
        InstallState::markInstalled();
        $this->assertDoesNotMatchRegularExpression('/<form[^>]*action="\//', $this->get('/upgrade')->getContent());
    }

    public function test_page_shows_and_creates_the_token_file(): void
    {
        $this->get('/install')->assertOk()->assertSee('storage/app/install/token', false);
        $this->assertFileExists($this->dir.'/state/token');
    }

    public function test_wrong_token_is_refused_and_nothing_is_created(): void
    {
        $this->get('/install');
        $this->post('/install', $this->input(['token' => 'nope']))->assertStatus(403);
        $this->assertFileDoesNotExist($this->env);
        $this->assertFalse(InstallState::installed());
    }

    public function test_bad_input_is_refused(): void
    {
        $this->get('/install');
        $this->post('/install', $this->input(['admin_password' => 'short', 'admin_password_confirmation' => 'short']))->assertStatus(422);
        $this->post('/install', $this->input(['admin_email' => 'not-an-email']))->assertStatus(422);
        $this->assertFalse(InstallState::installed());
    }

    public function test_full_install_then_everything_is_locked(): void
    {
        $this->get('/install');
        $this->post('/install', $this->input())->assertOk()->assertSee('Installed');

        $this->assertTrue(InstallState::installed());
        $this->assertFileDoesNotExist($this->dir.'/state/token');
        $env = file_get_contents($this->env);
        $this->assertStringContainsString('APP_DEBUG=false', $env);
        $this->assertStringContainsString('APP_KEY="base64:', str_replace('APP_KEY=base64:', 'APP_KEY="base64:', $env));

        $this->postJson('/api/login', ['email' => 'root@example.org', 'password' => 'Str0ng#Passw0rd'])
            ->assertOk()->assertJsonPath('data.user.role', 'system_admin');

        $this->get('/install')->assertNotFound();
        $this->post('/install', $this->input())->assertNotFound();
    }

    public function test_a_database_that_already_has_tables_is_refused(): void
    {
        $this->get('/install');
        $this->post('/install', $this->input())->assertOk();
        unlink($this->dir.'/state/installed.lock');
        unlink($this->env);                      // marker AND settings lost: nothing says "installed" any more
        $this->post('/install', $this->input(['token' => InstallState::token(), 'admin_email' => 'other@example.org']))
            ->assertStatus(422)->assertSee('already has tables');
    }

    public function test_an_interrupted_install_can_be_resumed(): void
    {
        $this->get('/install');
        InstallState::markInstalling();
        $this->post('/install', $this->input())->assertOk();
        $this->assertTrue(InstallState::installed());
        $this->assertFalse(InstallState::installing());
    }

    public function test_the_database_password_is_never_shown(): void
    {
        $this->get('/install');
        $r = $this->post('/install', $this->input(['admin_email' => 'bad', 'db_pass' => 'SuperSecretDbPass']));
        $this->assertStringNotContainsString('SuperSecretDbPass', $r->getContent());
    }

    public function test_upgrade_needs_the_token(): void
    {
        InstallState::markInstalled();
        $this->get('/upgrade')->assertOk()->assertSee('Upgrade');
        $this->post('/upgrade', ['token' => 'wrong'])->assertStatus(403);
        $this->post('/upgrade', ['token' => InstallState::token()])->assertOk()->assertSee('Done');
        $this->assertFileDoesNotExist($this->dir.'/state/token');
    }

    public function test_upgrade_is_not_available_before_install(): void
    {
        $this->get('/upgrade')->assertRedirect('/install');
        $this->post('/upgrade', ['token' => 'x'])->assertNotFound();
    }

    private function installedSite(): void
    {
        $this->get('/install');
        $this->post('/install', $this->input())->assertOk();
    }

    public function test_losing_only_the_installed_marker_heals_itself(): void
    {
        $this->installedSite();
        unlink($this->dir.'/state/installed.lock');

        $this->get('/install')->assertNotFound();                      // the installer stays switched off
        $this->assertTrue(InstallState::installed());
        $this->assertFileExists($this->dir.'/state/installed.lock');
        $this->postJson('/api/login', ['email' => 'root@example.org', 'password' => 'Str0ng#Passw0rd'])->assertOk();
    }

    public function test_losing_the_settings_file_too_is_repaired_with_the_reconnect_form(): void
    {
        $this->installedSite();
        unlink($this->dir.'/state/installed.lock');
        unlink($this->env);

        $page = $this->get('/install')->assertOk()->assertSee('Reconnect');
        $this->assertSame(1, substr_count($page->getContent(), 'Already installed before'));
        // normal installing is still refused: the database already holds tables
        $this->post('/install', $this->input(['token' => InstallState::token(), 'admin_email' => 'other@example.org']))->assertStatus(422)->assertSee('already has tables');

        $rec = fn ($over = []) => $over + ['token' => InstallState::token(), 'app_url' => 'https://example.org/api', 'db_name' => $this->db];
        $this->post('/install/reconnect', $rec(['token' => 'wrong']))->assertStatus(403);
        $this->assertFileDoesNotExist($this->env);

        $before = \Illuminate\Support\Facades\DB::connection('install')->table('users')->count();
        $this->post('/install/reconnect', $rec())->assertOk()->assertSee('no data was changed');
        $this->assertSame($before, \Illuminate\Support\Facades\DB::connection('install')->table('users')->count());
        $this->assertFileExists($this->env);
        $this->assertTrue(InstallState::installed());
        $this->assertStringContainsString('APP_DEBUG=false', file_get_contents($this->env));
        $this->postJson('/api/login', ['email' => 'root@example.org', 'password' => 'Str0ng#Passw0rd'])->assertOk();
        $this->get('/install')->assertNotFound();
    }

    public function test_reconnect_refuses_a_database_that_is_not_an_installed_system(): void
    {
        $this->get('/install');
        $empty = $this->dir.'/empty.sqlite';
        touch($empty);
        $this->post('/install/reconnect', ['token' => InstallState::token(), 'app_url' => 'https://example.org/api', 'db_name' => $empty])
            ->assertStatus(422)->assertSee('does not hold an installed system');
        $this->assertFileDoesNotExist($this->env);
        $this->assertFalse(InstallState::installed());

        $this->post('/install/reconnect', ['token' => InstallState::token(), 'app_url' => 'https://example.org/api', 'db_name' => $this->dir.'/missing/none.sqlite'])->assertStatus(422);
    }

    public function test_reconnect_is_not_available_once_installed(): void
    {
        $this->installedSite();
        $this->post('/install/reconnect', ['token' => 'x', 'app_url' => 'https://example.org/api', 'db_name' => $this->db])->assertNotFound();
    }
}
