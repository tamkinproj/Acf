<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();

        // Every test starts from an "already installed" server, in its own state folder. Installer tests change this.
        $dir = sys_get_temp_dir().'/insania-state-'.bin2hex(random_bytes(6));
        mkdir($dir, 0775, true);
        file_put_contents($dir.'/installed.lock', 'test');
        config(['install.state_dir' => $dir, 'install.driver' => 'sqlite']);
    }
}
