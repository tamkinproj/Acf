<?php

namespace Tests\Feature;

use App\Install\InstallState;
use App\Install\InstallStatus;
use App\Models\User;
use Tests\InstallTestCase;

class FoundationInstallCommandTest extends InstallTestCase
{
    private function args(array $extra = []): array
    {
        return $extra + [
            '--driver' => 'sqlite', '--sqlite-name' => $this->dbName, '--app-url' => 'https://example.test/acr',
            '--admin-name' => 'Cli Admin', '--admin-email' => 'cli@example.test',
            '--admin-password' => 'Cli-secret-pass-1',
        ];
    }

    public function test_installs_without_the_wizard_and_locks_itself(): void
    {
        $this->artisan('foundation:install', $this->args())->assertSuccessful();

        $this->assertSame(InstallStatus::Installed, app(InstallState::class)->status());
        $admin = app(\App\Tenancy\TenantContext::class)->asSystem(fn () => User::sole());
        $this->assertSame('cli@example.test', $admin->email);
        $this->assertNull($admin->foundation_id, 'the installer creates the Platform Admin, not a foundation user');
        $env = file_get_contents($this->envFile);
        $this->assertStringContainsString('SESSION_PATH=/acr', $env);
        $this->assertStringNotContainsString('Cli-secret-pass-1', $env);

        $this->artisan('foundation:install', $this->args())->assertFailed();   // already installed
        $this->assertSame(1, app(\App\Tenancy\TenantContext::class)->asSystem(fn () => User::count()));
    }

    public function test_rejects_weak_passwords_and_bad_input_before_touching_anything(): void
    {
        $this->artisan('foundation:install', $this->args(['--admin-password' => 'short']))->assertFailed();
        $this->artisan('foundation:install', $this->args(['--sqlite-name' => '../evil']))->assertFailed();
        $this->artisan('foundation:install', $this->args(['--timezone' => 'Mars/Base']))->assertFailed();
        $this->assertFileDoesNotExist($this->envFile);
        $this->assertSame(InstallStatus::NotInstalled, app(InstallState::class)->status());
    }
}
