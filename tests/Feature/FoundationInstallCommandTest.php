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
            '--foundation-name' => 'CLI Foundation', '--admin-name' => 'Cli Admin', '--admin-email' => 'cli@example.test',
            '--admin-password' => 'Cli-secret-pass-1', '--device-name' => 'Laptop', '--device-type' => 'office',
        ];
    }

    public function test_installs_without_the_wizard_and_locks_itself(): void
    {
        $this->artisan('foundation:install', $this->args())->assertSuccessful();

        $this->assertSame(InstallStatus::Installed, app(InstallState::class)->status());
        $this->assertSame('cli@example.test', User::sole()->email);
        $env = file_get_contents($this->envFile);
        $this->assertStringContainsString('SESSION_PATH=/acr', $env);
        $this->assertStringNotContainsString('Cli-secret-pass-1', $env);

        $this->artisan('foundation:install', $this->args())->assertFailed();   // already installed
        $this->assertSame(1, User::count());
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
