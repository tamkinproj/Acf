<?php

namespace Tests\Unit;

use App\Providers\CoreServiceProvider;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class SessionPathTest extends TestCase
{
    private function request(string $script): Request
    {
        return Request::create('https://example.org'.dirname($script).'/api/auth/csrf', 'GET', [], [], [], [
            'SCRIPT_NAME' => $script, 'SCRIPT_FILENAME' => '/var/www/html'.$script, 'PHP_SELF' => $script,
        ]);
    }

    public function test_cookie_path_follows_the_folder_the_site_is_served_from(): void
    {
        $this->assertSame('/acr', CoreServiceProvider::sessionPathFor($this->request('/acr/index.php')));
        $this->assertSame('/a/b', CoreServiceProvider::sessionPathFor($this->request('/a/b/index.php')));
    }

    public function test_domain_root_gets_slash(): void
    {
        $this->assertSame('/', CoreServiceProvider::sessionPathFor($this->request('/index.php')));
    }
}
