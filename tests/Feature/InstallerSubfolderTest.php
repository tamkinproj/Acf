<?php

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\InstallTestCase;

/**
 * Many shared hosts serve an app from a subfolder (https://example.com/public/...), not a domain root. Every
 * link, form action and stylesheet must stay inside that subfolder: a root-relative "/install/x" would send the
 * administrator - and the setup token - to a different site on the same domain.
 *
 * (Verified separately against a real server presenting requests as Apache does for /public/index.php; this test
 * pins the part that regressed: the views must build URLs with the framework's helpers, never hard-code "/".)
 */
class InstallerSubfolderTest extends InstallTestCase
{
    private function asSubfolderRequest(): string
    {
        $request = Request::create('https://example.com/public/install/token', 'GET', [], [], [], [
            'SCRIPT_NAME' => '/public/index.php', 'PHP_SELF' => '/public/index.php', 'SCRIPT_FILENAME' => base_path('public/index.php'),
        ]);
        $this->app->instance('request', $request);
        $this->app['url']->setRequest($request);

        return 'https://example.com/public';
    }

    public function test_views_build_every_url_from_the_framework_base(): void
    {
        $base = $this->asSubfolderRequest();
        $this->assertSame($base.'/install/token', url('/install/token'));

        $views = [
            'install.token' => ['path' => 'storage/app/install/token'],
            'install.welcome' => ['error' => null, 'step' => 'database'],
            'install.already' => [],
            'install.recovery' => ['reason' => 'x'],
            'install.complete' => ['summary' => ['foundation' => 'F', 'administrator' => 'A', 'device' => 'D', 'version' => '1']],
            'install.requirements' => ['checks' => [], 'passes' => true, 'current' => 'requirements'],
            'install.review' => ['current' => 'review', 'd' => [], 'error' => null],
            'shell' => ['foundation' => null],
        ];
        foreach ($views as $name => $data) {
            $html = view($name, $data)->render();
            $this->assertDoesNotMatchRegularExpression('#(action|href|src)="/#', $html, "{$name} contains a root-relative URL");
            if ($name === 'shell') {
                $this->assertStringContainsString('data-base="'.$base.'"', $html, 'the client learns its base URL from the page');
                $this->assertStringContainsString($base.'/app/main.js', $html);
            }
            $css = $name === 'shell' ? 'css/app.css' : 'css/foundation.css';
            $this->assertStringContainsString($base.'/'.$css, $html, "{$name} stylesheet");
        }

        $this->assertStringContainsString('action="'.$base.'/install/token"', view('install.token', ['path' => 'x'])->render());
        $this->assertStringContainsString('href="'.$base.'/install/database"', view('install.welcome', ['error' => null, 'step' => 'database'])->render());
        $this->assertStringContainsString('action="'.$base.'/install/run"', view('install.review', ['current' => 'review', 'd' => [], 'error' => null])->render());
    }

    public function test_no_view_hardcodes_a_root_relative_url(): void
    {
        foreach (glob(resource_path('views/{,*/}*.blade.php'), GLOB_BRACE) as $file) {
            $this->assertDoesNotMatchRegularExpression('#(action|href|src)="/#', file_get_contents($file), basename($file));
        }
    }

    public function test_the_suggested_application_url_includes_the_subfolder(): void
    {
        $base = $this->asSubfolderRequest();
        $this->assertSame($base, request()->root());
    }
}
