<?php

namespace Tests\Feature;

use App\Core\Access\PermissionCatalog;
use App\Core\Settings\SettingsCatalog;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SystemState;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class UpgradeTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
    }

    public function test_upgrade_adds_new_settings_and_only_new_permissions_to_default_roles(): void
    {
        // The administrator deliberately removed a permission from Staff.
        $staff = Role::where('key', 'staff')->first();
        $staff->update(['permissions' => array_values(array_diff($staff->permissions, ['locations.manage']))]);

        // A later release (e.g. the Aytam module) introduces a permission and a setting.
        PermissionCatalog::register(['relief.view' => ['label' => 'View relief', 'group' => 'Relief', 'scope' => 'program']]);
        SettingsCatalog::register(['aytam.default_status' => ['group' => 'aytam', 'default' => 'active', 'rules' => ['required']]]);

        $this->artisan('foundation:upgrade')->assertSuccessful();

        $this->assertTrue(Setting::where('key', 'aytam.default_status')->exists());
        $this->assertContains('relief.view', Role::where('key', 'foundation_admin')->first()->permissions);
        $this->assertNotContains('locations.manage', $staff->fresh()->permissions, 'a removed permission is not silently re-granted');
        $this->assertSame(config('foundation.version'), SystemState::get('version'));

        $this->artisan('foundation:upgrade')->assertSuccessful();   // idempotent
        $this->assertSame(1, Setting::where('key', 'aytam.default_status')->count());
    }

    public function test_upgrade_refuses_to_run_on_an_uninstalled_system(): void
    {
        config(['foundation.install_id' => null]);
        @unlink($this->installDir.'/installed.lock');
        $this->artisan('foundation:upgrade')->assertFailed();
    }

    public function test_status_command_detects_a_database_that_belongs_to_another_install(): void
    {
        $this->artisan('foundation:status')->assertFailed();   // DB install_id was never recorded in this fixture

        SystemState::put('install_id', 'test-install-id');
        SystemState::put('schema_version', (int) config('foundation.schema_version'));
        $this->artisan('foundation:status')->assertSuccessful();

        SystemState::put('install_id', 'someone-elses');
        $this->artisan('foundation:status')->assertFailed();
    }
}
