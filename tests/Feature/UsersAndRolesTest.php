<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SyncChange;
use App\Models\User;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class UsersAndRolesTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
    }

    private function roleId(string $key): string
    {
        return Role::where('key', $key)->value('id');
    }

    public function test_admin_creates_a_user_with_a_one_time_temporary_password(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/api/users', [
            'name' => 'Field Fatima', 'email' => 'Fatima@Example.test', 'role_id' => $this->roleId('field_worker'),
        ])->assertCreated()->assertJsonPath('data.email', 'fatima@example.test')->assertJsonPath('data.must_change_password', true);

        $temp = $res->json('data.temporary_password');
        $this->assertGreaterThanOrEqual(14, strlen($temp));

        $user = User::where('email', 'fatima@example.test')->sole();
        $this->assertNotSame($temp, $user->password);
        $this->getJson('/api/users/'.$user->id)->assertOk()->assertDontSee($temp);
        $this->assertTrue(AuditLog::where('action', 'user.created')->where('subject_id', $user->id)->exists());
        $this->assertStringNotContainsString($temp, json_encode(AuditLog::all()));
        $this->assertStringNotContainsString($temp, json_encode(SyncChange::all()));

        // They can sign in with it, but only to change it.
        auth()->logout();
        $this->postJson('/api/auth/login', ['email' => 'fatima@example.test', 'password' => $temp])->assertOk();
        $this->getJson('/api/users')->assertStatus(403);
    }

    public function test_email_is_unique_case_insensitively_even_for_removed_users(): void
    {
        $this->actingAs($this->admin);
        $a = $this->makeUser('staff', 'taken@example.test');
        $payload = ['name' => 'Dup', 'role_id' => $this->roleId('staff')];

        $this->postJson('/api/users', $payload + ['email' => 'TAKEN@example.test'])->assertStatus(422)->assertJsonValidationErrors('email');
        $a->delete();
        $this->postJson('/api/users', $payload + ['email' => 'taken@example.test'])->assertStatus(422);
    }

    public function test_users_endpoints_are_permission_gated(): void
    {
        $staff = $this->makeUser('staff');
        $viewer = $this->makeUser('viewer');

        $this->actingAs($staff)->getJson('/api/users')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->actingAs($viewer)->postJson('/api/users', ['name' => 'x'])->assertStatus(403);
        $this->actingAs($viewer)->getJson('/api/audit-logs')->assertStatus(403);
        $this->actingAs($viewer)->getJson('/api/roles')->assertStatus(403);
        $this->actingAs($viewer)->getJson('/api/dashboard/summary')->assertOk();
    }

    public function test_only_a_foundation_admin_can_touch_foundation_admin_accounts(): void
    {
        // A people-manager (holds every users.* permission) is still not a Foundation Admin.
        $manager = $this->makeUserWithPermissions(['dashboard.view', 'users.view', 'users.create', 'users.update', 'users.deactivate', 'roles.view']);
        $this->actingAs($manager);

        $this->patchJson('/api/users/'.$this->admin->id, ['name' => 'Hacked'])->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
        $this->postJson('/api/users', ['name' => 'Sneaky', 'email' => 's@example.test', 'role_id' => $this->roleId('foundation_admin')])->assertStatus(403);
        $this->postJson('/api/users/'.$this->admin->id.'/reset-password')->assertStatus(403);
        $this->deleteJson('/api/users/'.$this->admin->id)->assertStatus(403);

        $staff = $this->makeUser('staff');
        $this->patchJson('/api/users/'.$staff->id, ['role_id' => $this->roleId('foundation_admin')])->assertStatus(403);
        $this->patchJson('/api/users/'.$staff->id, ['role_id' => $this->roleId('viewer')])->assertOk();
    }

    public function test_deactivating_needs_its_own_permission(): void
    {
        $editor = $this->makeUserWithPermissions(['users.view', 'users.update']);
        $staff = $this->makeUser('staff');

        $this->actingAs($editor)->patchJson('/api/users/'.$staff->id, ['name' => 'Renamed'])->assertOk();
        $this->patchJson('/api/users/'.$staff->id, ['status' => 'disabled'])->assertStatus(403);
        $this->deleteJson('/api/users/'.$staff->id)->assertStatus(403);
        $this->assertSame('active', $staff->fresh()->status);
    }

    public function test_cannot_lock_out_the_last_foundation_admin_or_yourself(): void
    {
        $this->actingAs($this->admin);
        $this->patchJson('/api/users/'.$this->admin->id, ['status' => 'disabled'])->assertStatus(403);
        $this->deleteJson('/api/users/'.$this->admin->id)->assertStatus(403);
        $this->patchJson('/api/users/'.$this->admin->id, ['role_id' => $this->roleId('viewer')])->assertStatus(403);

        $second = $this->makeUser('foundation_admin');
        $this->actingAs($second)->patchJson('/api/users/'.$this->admin->id, ['status' => 'disabled'])->assertOk();
        // Now $second is the only active super admin left.
        $third = $this->makeUser('foundation_admin');
        $third->update(['status' => 'disabled']);
        $this->actingAs($this->admin->fresh())->getJson('/api/auth/me')->assertStatus(401);
        $this->actingAs($second)->deleteJson('/api/users/'.$second->id)->assertStatus(403);
    }

    public function test_reset_password_issues_new_temp_password_and_forces_change(): void
    {
        $staff = $this->makeUser('staff');
        $res = $this->actingAs($this->admin)->postJson('/api/users/'.$staff->id.'/reset-password')->assertOk();
        $temp = $res->json('data.temporary_password');

        $this->assertTrue($staff->fresh()->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check($temp, $staff->fresh()->password));
        $this->assertTrue(AuditLog::where('action', 'user.password_reset')->exists());
        $this->assertStringNotContainsString($temp, json_encode(AuditLog::all()));
    }

    public function test_role_permissions_are_editable_validated_audited_and_replicated(): void
    {
        $viewer = Role::where('key', 'viewer')->first();
        $this->actingAs($this->admin);

        $this->putJson("/api/roles/{$viewer->id}/permissions", ['permissions' => ['dashboard.view', 'sync.use', 'locations.manage']])
            ->assertOk()->assertJsonPath('data.permissions.2', 'locations.manage');
        $this->putJson("/api/roles/{$viewer->id}/permissions", ['permissions' => ['not.a.permission']])->assertStatus(422);

        $this->assertTrue(AuditLog::where('action', 'role.updated')->where('subject_id', $viewer->id)->exists());
        $change = SyncChange::where('entity', 'roles')->where('entity_id', $viewer->id)->orderByDesc('seq')->first();
        $this->assertSame('update', $change->op);
        $this->assertContains('locations.manage', $change->payload['permissions']);

        $this->assertTrue($this->makeUser('viewer')->hasPermission('locations.manage'));
    }

    public function test_foundation_admin_role_is_fixed_and_only_role_managers_edit_roles(): void
    {
        $fixed = Role::where('key', 'foundation_admin')->first();
        $this->actingAs($this->admin)->putJson("/api/roles/{$fixed->id}/permissions", ['permissions' => []])->assertStatus(403);

        // Foundation Admin CAN edit other roles (a foundation controls its own roles)...
        $staffId = Role::where('key', 'staff')->value('id');
        $this->putJson("/api/roles/{$staffId}/permissions", ['permissions' => ['dashboard.view']])->assertOk();
        // ...but staff cannot.
        $this->actingAs($this->makeUser('staff'))->putJson("/api/roles/{$staffId}/permissions", ['permissions' => ['dashboard.view']])->assertStatus(403);
    }

    public function test_roles_and_permission_catalog_listing(): void
    {
        $r = $this->actingAs($this->admin)->getJson('/api/roles')->assertOk();
        $keys = array_column($r->json('data'), 'key');
        foreach (['foundation_admin', 'staff', 'field_worker', 'volunteer', 'viewer', 'aytam_mushrif', 'aytam_field_worker'] as $expected) {
            $this->assertContains($expected, $keys);
        }
        $this->assertNotContains('platform_admin', $keys, 'platform roles are invisible to a foundation');

        $catalog = $this->getJson('/api/permissions')->assertOk()->assertJsonFragment(['key' => 'users.create'])->json('data');
        $this->assertNotContains('platform.view', array_column($catalog, 'key'), 'a foundation can never grant platform permissions');
    }

    public function test_a_foundation_can_create_custom_roles_but_not_with_the_wrong_scope_of_permission(): void
    {
        $this->actingAs($this->admin);
        $this->postJson('/api/roles', ['name' => 'Case Reviewer', 'scope' => 'program', 'module' => 'aytam', 'permissions' => ['aytam.view', 'aytam.review']])
            ->assertCreated()->assertJsonPath('data.key', 'case_reviewer');
        $this->postJson('/api/roles', ['name' => 'Odd', 'scope' => 'program', 'permissions' => ['users.create']])->assertStatus(422)->assertJsonPath('code', 'INVALID_PERMISSION');
        $this->postJson('/api/roles', ['name' => 'Sneaky', 'scope' => 'foundation', 'permissions' => ['platform.view']])->assertStatus(422);

        $id = Role::where('key', 'case_reviewer')->value('id');
        $this->deleteJson("/api/roles/{$id}")->assertOk();
        $this->deleteJson('/api/roles/'.Role::where('key', 'staff')->value('id'))->assertStatus(403);
    }
}
