<?php

namespace Tests\Feature;

use App\Install\InstallState;
use App\Install\InstallStatus;
use Tests\TestCase;

class InstallGateTest extends TestCase
{
    public function test_fresh_system_is_not_installed_and_redirects_to_the_wizard(): void
    {
        $this->assertSame(InstallStatus::NotInstalled, app(InstallState::class)->status());

        $this->get('/')->assertRedirect('/install');
        $this->get('/login')->assertRedirect('/install');
    }

    public function test_api_reports_not_installed_as_json(): void
    {
        $this->getJson('/api/system/status')->assertStatus(503)->assertJsonPath('code', 'NOT_INSTALLED');
    }

    public function test_installed_system_locks_every_installer_route(): void
    {
        $this->markInstalled();

        foreach (['/install', '/install/requirements', '/install/database', '/install/token'] as $url) {
            $this->get($url)->assertStatus(403)->assertSee('This system is already installed.');
        }
        $this->post('/install/run')->assertStatus(403);
        $this->post('/install/admin', ['name' => 'x', 'email' => 'x@x.test', 'admin_password' => 'abc'])->assertStatus(403);
        $this->postJson('/install/run')->assertStatus(403)->assertJsonPath('code', 'ALREADY_INSTALLED');
    }

    public function test_installed_system_serves_normal_pages(): void
    {
        $this->markInstalled();
        $this->get('/')->assertOk();
    }

    public function test_lock_without_matching_env_is_corrupt_and_shows_recovery_not_the_installer(): void
    {
        $this->markInstalled();
        config(['foundation.install_id' => 'some-other-install']);

        $this->assertSame(InstallStatus::Corrupt, app(InstallState::class)->status());
        $this->get('/')->assertStatus(503)->assertSee('Installation needs attention')->assertSee('Nothing has been changed or deleted');
        $this->get('/install')->assertStatus(503)->assertSee('Installation needs attention');
        $this->getJson('/api/system/status')->assertStatus(503)->assertJsonPath('code', 'INSTALL_CORRUPT');
    }

    public function test_env_marked_installed_but_lock_missing_is_corrupt_never_reinstallable(): void
    {
        config(['foundation.install_id' => 'abc']);   // .env says installed, no lock file exists

        $this->assertSame(InstallStatus::Corrupt, app(InstallState::class)->status());
        $this->get('/install')->assertStatus(503);
    }

    public function test_tampered_lock_is_detected(): void
    {
        $this->markInstalled();
        $file = $this->installDir.'/installed.lock';
        chmod($file, 0640);
        $lock = json_decode(file_get_contents($file), true);
        $lock['version'] = '9.9.9';   // signature no longer matches
        file_put_contents($file, json_encode($lock));

        $this->assertSame(InstallStatus::Corrupt, app(InstallState::class)->status());
    }

    public function test_garbage_lock_file_is_corrupt(): void
    {
        config(['foundation.install_id' => 'abc']);
        file_put_contents($this->installDir.'/installed.lock', 'not json');

        $this->assertSame(InstallStatus::Corrupt, app(InstallState::class)->status());
    }

    public function test_security_headers_are_sent(): void
    {
        $this->markInstalled();
        $r = $this->get('/');
        $r->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString("frame-ancestors 'none'", $r->headers->get('Content-Security-Policy'));
        $this->assertStringNotContainsString('unsafe-inline', $r->headers->get('Content-Security-Policy'));
    }

    public function test_booting_does_not_touch_the_cache_or_database(): void
    {
        // On a bare deployment the default cache/session store is a database that does not exist yet. Anything that
        // binds to the cache at boot (e.g. eagerly defining rate limiters) would crash every installer page.
        $this->assertFalse(app()->resolved(\Illuminate\Cache\RateLimiter::class));
        $this->assertFalse(app()->resolved('cache.store'));

        // ...and the limiters still exist once the limiter is actually used.
        $this->assertNotNull(\Illuminate\Support\Facades\RateLimiter::limiter('installer-run'));
        $this->assertNotNull(\Illuminate\Support\Facades\RateLimiter::limiter('sync-push'));
    }
}
