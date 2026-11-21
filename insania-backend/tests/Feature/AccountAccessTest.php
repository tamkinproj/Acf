<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\PermissionCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class AccountAccessTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function make(array $over = []): array
    {
        $this->actingAsRole('president_admin');

        return $this->postJson('/api/users', $over + ['name' => 'Hana HR', 'email' => 'hana@insania.test', 'role' => 'staff', 'department_key' => 'hr', 'password' => 'Str0ng#Zq9xKw', 'confirm_password' => 'DemoPass#2026'])->assertCreated()->json('data');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->world();
    }

    public function test_hr_is_a_department_with_attendance_and_salary_access(): void
    {
        $u = $this->make();
        $this->assertContains('attendance.manage', $u['access']);
        $this->assertContains('attendance.salary', $u['access']);
        $this->assertFalse($u['access_custom']);
        $this->assertNotContains('children.view', $u['permissions']);
    }

    public function test_the_president_ticks_and_unticks_what_a_staff_account_can_open(): void
    {
        $u = $this->make();
        $this->patchJson('/api/users/'.$u['id'], ['access' => ['attendance.clock', 'attendance.manage', 'chat.use', 'children.view'], 'confirm_password' => 'DemoPass#2026'])->assertOk()
            ->assertJsonPath('data.access_custom', true);
        $user = User::find($u['id']);
        $perms = PermissionCatalog::forUser($user);
        $this->assertContains('children.view', $perms);          // added
        $this->assertNotContains('attendance.salary', $perms);   // taken away
        $this->assertContains('attendance.manage', $perms);
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.access_changed']);

        // the salary screens refuse an account whose salary tick was taken away
        $this->app['auth']->forgetGuards();
        \Laravel\Sanctum\Sanctum::actingAs($user, ['*']);
        $this->getJson('/api/attendance/payroll')->assertForbidden();
        $this->getJson('/api/attendance/board')->assertOk();
        $this->getJson('/api/children')->assertOk();
    }

    public function test_ticking_exactly_the_departments_access_stores_nothing(): void
    {
        $u = $this->make();
        $this->patchJson('/api/users/'.$u['id'], ['access' => ['attendance.clock', 'attendance.manage', 'chat.use'], 'confirm_password' => 'DemoPass#2026'])->assertOk();
        $this->patchJson('/api/users/'.$u['id'], ['access' => PermissionCatalog::departmentDefaults('hr'), 'confirm_password' => 'DemoPass#2026'])->assertOk()->assertJsonPath('data.access_custom', false);
        $this->assertNull(User::find($u['id'])->access_overrides);
    }

    public function test_a_new_department_starts_from_its_own_access(): void
    {
        $u = $this->make();
        $this->patchJson('/api/users/'.$u['id'], ['access' => ['chat.use'], 'confirm_password' => 'DemoPass#2026'])->assertOk();
        $this->patchJson('/api/users/'.$u['id'], ['department_key' => 'finance', 'confirm_password' => 'DemoPass#2026'])->assertOk()->assertJsonPath('data.access_custom', false);
        $this->assertContains('payments.view', User::find($u['id'])->toApi()['permissions']);
    }

    public function test_only_the_president_changes_access_and_only_for_staff(): void
    {
        $u = $this->make();
        $this->actingAsRole('security_admin');
        $this->patchJson('/api/users/'.$u['id'], ['access' => ['chat.use'], 'confirm_password' => 'DemoPass#2026'])->assertForbidden();
        $this->postJson('/api/users', ['name' => 'Sec', 'email' => 'sec2@insania.test', 'role' => 'staff', 'department_key' => 'hr', 'password' => 'Str0ng#Zq9xKw', 'access' => ['chat.use'], 'confirm_password' => 'DemoPass#2026'])->assertForbidden();

        $this->actingAsRole('president_admin');
        $m = $this->postJson('/api/users', ['name' => 'Mx', 'email' => 'mx@insania.test', 'role' => 'admin_maintenance', 'password' => 'Str0ng#Zq9xKw', 'confirm_password' => 'DemoPass#2026'])->assertCreated()->json('data.id');
        $this->patchJson('/api/users/'.$m, ['access' => ['chat.use'], 'confirm_password' => 'DemoPass#2026'])->assertStatus(422);
        // only the listed office features can be ticked
        $this->patchJson('/api/users/'.$u['id'], ['access' => ['users.manage'], 'confirm_password' => 'DemoPass#2026'])->assertStatus(422);
    }

    public function test_the_access_catalog_lists_the_groups_and_department_defaults(): void
    {
        $this->actingAsRole('president_admin');
        $c = $this->getJson('/api/users/access-catalog')->assertOk()->json('data');
        $this->assertContains('attendance', array_column($c['groups'], 'key'));
        $this->assertContains('attendance.manage', $c['defaults']['hr']);
        $this->actingAsRole('staff:finance');
        $this->getJson('/api/users/access-catalog')->assertForbidden();
    }
}
