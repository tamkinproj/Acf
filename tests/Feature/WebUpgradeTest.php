<?php

namespace Tests\Feature;

use App\Install\InstallState;
use App\Models\Role;
use App\Models\SystemState;
use App\Models\User;
use App\Tenancy\TenantContext;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

/**
 * An installation from before multi-tenancy (one foundation, a "Super Admin", no Platform Admin) must come through
 * the upgrade intact - including on a host with no terminal, where the update is finished from a token-protected page.
 */
class WebUpgradeTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
        $this->makeLegacy();
    }

    /** Recreate what a 1.0.0 database looks like once the new schema is in place. */
    private function makeLegacy(): void
    {
        $tenant = app(TenantContext::class);
        $tenant->asSystem(function () {
            $legacy = Role::create(['foundation_id' => $this->foundation->id, 'key' => 'super_admin', 'name' => 'Super Admin', 'is_system' => true,
                'scope' => 'foundation', 'permissions' => ['foundation.manage', 'users.manage', 'dashboard.view']]);
            $this->admin->forceFill(['role_id' => $legacy->id])->save();
            Role::where('foundation_id', $this->foundation->id)->where('key', 'staff')->first()
                ->update(['permissions' => ['dashboard.view', 'users.manage', 'bogus.removed.permission']]);
            User::where('id', $this->platformAdmin->id)->forceDelete();
        });
        app(InstallState::class)->writeLock('test-install-id', '1.0.0');
        SystemState::put('version', '1.0.0');
        SystemState::put('schema_version', 1);
        config(['foundation.installer_require_token' => true]);
    }

    public function test_every_request_is_sent_to_the_upgrade_page_until_it_is_done(): void
    {
        $this->get('/')->assertRedirect('/upgrade');
        $this->get('/anything')->assertRedirect('/upgrade');
        $this->getJson('/api/auth/me')->assertStatus(503)->assertJsonPath('code', 'UPGRADE_REQUIRED');
        $this->postJson('/api/auth/login', ['email' => 'admin@example.test', 'password' => 'Correct-horse-9'])->assertStatus(503);
        $this->get('/up')->assertOk();   // health check stays reachable
    }

    public function test_the_upgrade_page_needs_the_server_token(): void
    {
        $this->get('/upgrade')->assertRedirect('/upgrade/token');
        $this->post('/upgrade/run')->assertRedirect('/upgrade/token');
        $this->get('/upgrade/token')->assertOk()->assertSee('storage/app/install/token')->assertSee('action="'.url('/upgrade/token').'"', false);

        $this->post('/upgrade/token', ['token' => 'wrong'])->assertSessionHasErrors('token');
        $this->get('/upgrade')->assertRedirect('/upgrade/token');

        $token = trim(file_get_contents($this->installDir.'/token'));
        $this->post('/upgrade/token', ['token' => $token])->assertRedirect('/upgrade');
        $this->get('/upgrade')->assertOk()->assertSee('Update needed')->assertSee('1.0.0')->assertSee('Platform Admin');
    }

    public function test_the_upgrade_keeps_everyone_and_everything_and_adds_the_platform_admin(): void
    {
        $this->get('/upgrade/token');
        $this->post('/upgrade/token', ['token' => trim(file_get_contents($this->installDir.'/token'))]);

        // The Platform Admin is mandatory when there is none yet.
        $this->post('/upgrade/run', [])->assertSessionHasErrors(['name', 'email', 'admin_password']);
        $this->assertTrue(app(InstallState::class)->lock()['version'] === '1.0.0', 'a rejected form leaves the installation untouched');

        $this->post('/upgrade/run', ['name' => 'Owner', 'email' => 'owner@platform.test', 'admin_password' => 'Platform-pass-77', 'admin_password_confirmation' => 'Platform-pass-77'])
            ->assertOk()->assertSee('Update complete')->assertSee('Platform Admin');

        $tenant = app(TenantContext::class);
        $tenant->asSystem(function () {
            // The former Super Admin is now this foundation's Foundation Admin; the legacy role is gone.
            $this->assertNull(Role::where('foundation_id', $this->foundation->id)->where('key', 'super_admin')->first());
            $adminRole = Role::where('foundation_id', $this->foundation->id)->where('key', 'foundation_admin')->first();
            $this->assertSame($adminRole->id, User::find($this->admin->id)->role_id);
            $this->assertContains('users.create', $adminRole->permissions);
            $this->assertContains('programs.activate', $adminRole->permissions);

            // Renamed permissions carry over on other roles; unknown ones are dropped.
            $staff = Role::where('foundation_id', $this->foundation->id)->where('key', 'staff')->first();
            $this->assertEqualsCanonicalizing(['dashboard.view', 'users.create', 'users.update', 'users.deactivate'], $staff->permissions);

            $platform = User::whereNull('foundation_id')->first();
            $this->assertSame('owner@platform.test', $platform->email);
            $this->assertSame('platform_admin', $platform->role->key);
        });
        $this->assertSame(config('foundation.version'), app(InstallState::class)->lock()['version']);
        $this->assertSame(config('foundation.version'), SystemState::get('version'));
        $this->assertSame((int) config('foundation.schema_version'), SystemState::get('schema_version'));

        // The site is back; the old administrator signs in as before and is now a Foundation Admin.
        $this->get('/upgrade')->assertRedirect('/');
        $this->postJson('/api/auth/login', ['email' => 'admin@example.test', 'password' => 'Correct-horse-9'])->assertOk()
            ->assertJsonPath('data.kind', 'foundation')->assertJsonPath('data.user.role.key', 'foundation_admin');
        $this->postJson('/api/auth/login', ['email' => 'owner@platform.test', 'password' => 'Platform-pass-77'])->assertOk()->assertJsonPath('data.kind', 'platform');
    }

    public function test_the_command_line_upgrade_does_the_same_and_asks_for_a_platform_admin(): void
    {
        $this->artisan('foundation:upgrade')->assertSuccessful()->expectsOutputToContain('platform:admin');
        $tenant = app(TenantContext::class);
        $this->assertNull($tenant->asSystem(fn () => Role::where('key', 'super_admin')->first()));

        $this->artisan('foundation:upgrade')->assertSuccessful();   // idempotent
        $this->artisan('platform:admin', ['email' => 'ops@platform.test', 'name' => 'Ops', '--password' => 'Platform-pass-77'])->assertSuccessful();
        $this->assertSame(1, $tenant->asSystem(fn () => User::whereNull('foundation_id')->count()));
    }
}
