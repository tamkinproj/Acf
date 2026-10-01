<?php

namespace Tests\Feature;

use App\Install\Requirements;
use Tests\InstallTestCase;

/** The installer writes database credentials; it must refuse to run if the app folder is downloadable. */
class AppFolderExposureTest extends InstallTestCase
{
    public function test_app_inside_the_document_root_is_flagged_and_blocks_the_wizard(): void
    {
        // Document root = the folder CONTAINING the app (the mistake: foundation_app dropped inside public_html).
        $exposed = $this->withServerVariables(['DOCUMENT_ROOT' => dirname(base_path())]);

        $exposed->get('/install/requirements')->assertOk()
            ->assertSee('outside the public web folder')->assertSee('Move the application folder');
        $exposed->post('/install/requirements')->assertRedirect()->assertSessionHasErrors('requirements');
        $this->assertArrayNotHasKey('requirements', app(\App\Install\InstallState::class)->data());
    }

    public function test_normal_layouts_pass(): void
    {
        // Laravel's own layout: document root is public/, the app root is its parent.
        $this->withServerVariables(['DOCUMENT_ROOT' => base_path('public')]);
        $this->assertFalse(app(Requirements::class)->appFolderIsPublic());

        // Hostinger layout: document root is a sibling folder (public_html) of the app folder.
        $this->assertFalse($this->requestWithDocRoot(dirname(base_path()).'/some_other_public_html')->appFolderIsPublic());
        // No document root (CLI): the check is skipped.
        $this->assertFalse($this->requestWithDocRoot('')->appFolderIsPublic());
    }

    public function test_the_installer_run_itself_also_refuses(): void
    {
        $this->post('/install/requirements');
        $this->post('/install/database', ['driver' => 'sqlite', 'sqlite_name' => $this->dbName, 'action' => 'save']);
        $this->post('/install/system', ['app_name' => 'X', 'app_url' => 'https://x.test', 'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'PHP', 'deployment_model' => 'central']);
        $this->post('/install/foundation', ['name' => 'F']);
        $this->post('/install/admin', ['name' => 'A', 'email' => 'a@x.test', 'admin_password' => 'Valid-pass-123', 'admin_password_confirmation' => 'Valid-pass-123']);
        $this->post('/install/device', ['name' => 'D', 'type' => 'office']);

        $this->withServerVariables(['DOCUMENT_ROOT' => dirname(base_path())])->post('/install/run')->assertRedirect();
        $this->assertFileDoesNotExist($this->envFile, 'nothing is written when the folder is exposed');
        $this->assertFileDoesNotExist($this->installDir.'/installed.lock');
    }

    public function test_a_deny_all_htaccess_ships_in_the_app_root(): void
    {
        $this->assertStringContainsString('Require all denied', file_get_contents(base_path('.htaccess')));
    }

    private function requestWithDocRoot(string $docRoot): Requirements
    {
        $this->app->instance('request', \Illuminate\Http\Request::create('/', 'GET', [], [], [], ['DOCUMENT_ROOT' => $docRoot]));

        return new Requirements;
    }
}
