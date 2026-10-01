<?php

namespace Tests;

use App\Install\InstallState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected string $installDir;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test gets its own installer-state directory, so install state never leaks between tests
        // or touches the developer's real storage/app/install.
        $this->installDir = sys_get_temp_dir().'/fdn-install-'.bin2hex(random_bytes(5));
        mkdir($this->installDir, 0775, true);
        config([
            'foundation.install_path' => $this->installDir,
            'foundation.install_id' => null,
            'foundation.sync.settle_seconds' => 0,
        ]);
        app(\App\Sync\DeviceContext::class)->reset();
    }

    protected function tearDown(): void
    {
        if (isset($this->installDir) && is_dir($this->installDir)) {
            foreach (glob($this->installDir.'/{,.}*', GLOB_BRACE) ?: [] as $f) {
                is_file($f) && @unlink($f);
            }
            @rmdir($this->installDir);
        }
        parent::tearDown();
    }

    /** Write a valid install lock so the app behaves as an installed system. */
    protected function markInstalled(): void
    {
        config(['foundation.install_id' => 'test-install-id']);
        app(InstallState::class)->writeLock('test-install-id', config('foundation.version'));
    }
}
