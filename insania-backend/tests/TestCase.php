<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    /**
     * Account changes ask for the signed-in person's own password. The demo accounts all share one, so tests send it for them
     * unless a test sets "confirm_password" itself (SecurityTest checks the missing and wrong cases).
     */
    public function json($method, $uri, array $data = [], array $headers = [], $options = 0)
    {
        $path = ltrim((string) parse_url((string) $uri, PHP_URL_PATH), '/');
        if (in_array(strtoupper($method), ['POST', 'PATCH', 'PUT'], true) && preg_match('#^api/users(/|$)#', $path) && ! array_key_exists('confirm_password', $data)) {
            $data['confirm_password'] = 'DemoPass#2026';
        }

        return parent::json($method, $uri, $data, $headers, $options);
    }

    protected function setUp(): void
    {
        parent::setUp();

        // A shared cache (Redis) outlives one test; the in-memory test cache starts empty each time. Start every test clean.
        \Illuminate\Support\Facades\Cache::flush();

        // Every test starts from an "already installed" server, in its own state folder. Installer tests change this.
        $dir = sys_get_temp_dir().'/insania-state-'.bin2hex(random_bytes(6));
        mkdir($dir, 0775, true);
        file_put_contents($dir.'/installed.lock', 'test');
        config(['install.state_dir' => $dir, 'install.driver' => 'sqlite']);

        // The tests check every feature, so all switches are on here. FeatureSwitchesTest checks them as shipped (off).
        config(['features.switches' => array_fill_keys(array_keys((array) config('features.switches')), true)]);
    }
}
