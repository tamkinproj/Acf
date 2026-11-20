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
}
