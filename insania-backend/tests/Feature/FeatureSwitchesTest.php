<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class FeatureSwitchesTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function shipped(): void
    {
        // exactly what config/features.php ships with
        $file = require base_path('config/features.php');
        config(['features.switches' => $file['switches']]);
    }

    private function as(string $role): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsRole($role);
    }

    public function test_office_features_ship_on_for_the_president_and_for_the_departments_that_hold_them(): void
    {
        $file = require base_path('config/features.php');
        $this->assertNotEmpty($file['switches']);
        foreach ($file['switches'] as $feature => $on) {
            $expected = [
                'children' => ['staff', 'president_admin'], 'applications' => ['staff', 'president_admin'], 'payments' => ['staff', 'president_admin'],
                'projects' => ['staff', 'president_admin'], 'reports' => ['staff', 'president_admin'], 'website' => ['president_admin'],
                'accounts' => ['president_admin'], 'activity_log' => ['president_admin'], 'chat' => ['staff', 'president_admin'],
            ][$feature] ?? false;   // family accounts stay off
            $this->assertSame($expected, $on, "$feature switch as shipped");
        }
    }

    public function test_features_are_open_only_to_the_roles_they_are_switched_on_for(): void
    {
        $this->world();
        $this->shipped();
        $office = [
            ['GET', '/api/children'], ['POST', '/api/children'], ['GET', '/api/payments'], ['GET', '/api/projects'], ['GET', '/api/reports/summary'],
            ['GET', '/api/site'], ['POST', '/api/site/publish'], ['GET', '/api/users'], ['POST', '/api/users'], ['GET', '/api/audit'], ['GET', '/api/my/family'],
        ];
        // which office addresses each role may reach (the rest must be refused with 403); family accounts are off for everyone
        $open = [
            'president_admin' => ['/api/children', '/api/payments', '/api/projects', '/api/reports', '/api/site', '/api/users', '/api/audit'],
            'staff:aytam' => ['/api/children'],
            'staff:finance' => ['/api/payments', '/api/reports'],
            'staff:projects' => ['/api/projects'],
        ];
        foreach (['president_admin', 'security_admin', 'staff:aytam', 'staff:finance', 'staff:projects', 'user', 'system_admin', 'admin_maintenance'] as $role) {
            $this->as($role);
            foreach ($office as [$m, $url]) {
                $allowed = false;
                foreach ($open[$role] ?? [] as $prefix) {
                    $allowed = $allowed || str_starts_with($url, $prefix);
                }
                $status = $this->json($m, $url)->getStatusCode();
                $allowed ? $this->assertNotSame(403, $status, "$role $m $url") : $this->assertSame(403, $status, "$role $m $url");
            }
            $me = $this->getJson('/api/me')->assertOk()->json('data.permissions');
            $this->assertSame(in_array($role, ['staff:aytam', 'president_admin'], true), in_array('children.view', $me, true), "$role children.view");
            $this->assertSame($role === 'president_admin', in_array('site.edit', $me, true), "$role site.edit");
            $this->assertSame($role === 'president_admin', in_array('users.manage', $me, true), "$role users.manage");
            $this->assertSame($role === 'president_admin', in_array('audit.read', $me, true), "$role audit.read");
            $this->assertNotContains('family.view_own', array_diff($me, $role === 'user' ? ['family.view_own'] : []));
        }
    }

    public function test_the_system_admin_keeps_every_tool(): void
    {
        $this->world();
        $this->shipped();
        $this->as('system_admin');
        foreach (['/api/system/health', '/api/system/settings', '/api/system/api-keys', '/api/system/updates', '/api/system/president-admin'] as $url) {
            $this->getJson($url)->assertOk();
        }
        $this->getJson('/api/setup')->assertOk()->assertJsonPath('data.total', 3);
        $this->as('admin_maintenance');
        $this->getJson('/api/system/health')->assertOk();
    }

    public function test_the_president_signs_in_sees_every_office_feature_but_no_system_tool_and_can_change_their_password(): void
    {
        $this->world();
        $this->shipped();
        $p = User::where('role', 'president_admin')->first();
        $p->update(['password' => 'Old#Passw0rd123']);
        $token = $this->postJson('/api/login', ['email' => $p->email, 'password' => 'Old#Passw0rd123'])->assertOk()->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$token);
        $me = $this->getJson('/api/me')->assertOk()->json('data.permissions');
        foreach (['users.manage', 'children.view', 'applications.review', 'payments.view', 'projects.manage', 'reports.export', 'site.publish', 'audit.read'] as $perm) {
            $this->assertContains($perm, $me);
        }
        foreach ($me as $perm) {
            $this->assertStringStartsNotWith('system.', $perm);
        }
        $this->assertNotContains('family.view_own', $me);
        $this->getJson('/api/system/health')->assertForbidden();
        $this->getJson('/api/system/updates')->assertForbidden();
        $d = $this->getJson('/api/dashboard')->assertOk()->json('data');
        $this->assertArrayHasKey('security', $d);
        $this->assertArrayHasKey('children', $d);
        $this->assertArrayNotHasKey('family', $d);
        $steps = collect($this->getJson('/api/setup')->assertOk()->assertJsonPath('data.required_left', 0)->json('data.steps'))->pluck('key')->all();
        foreach (['staff_aytam', 'staff_finance', 'staff_projects', 'security_admin', 'maintenance'] as $k) {
            $this->assertContains($k, $steps);
        }
        $this->postJson('/api/me/password', ['current_password' => 'Old#Passw0rd123', 'password' => 'New#Passw0rd456'])->assertOk();
    }

    public function test_switching_one_feature_off_takes_away_only_that_feature(): void
    {
        $this->world();
        $this->shipped();
        config(['features.switches.website' => false]);
        $this->as('president_admin');
        $this->getJson('/api/site')->assertForbidden();
        $this->getJson('/api/projects')->assertOk();
        $this->getJson('/api/children')->assertOk();
        $steps = collect($this->getJson('/api/setup')->assertOk()->json('data.steps'))->pluck('key')->all();
        $this->assertContains('first_project', $steps);
        $this->assertContains('first_child', $steps);
        $this->assertNotContains('first_publish', $steps);
        $this->as('staff:projects');
        $this->getJson('/api/projects')->assertOk();
        $this->getJson('/api/payments')->assertForbidden();
        $this->as('staff:finance');
        $this->getJson('/api/payments')->assertOk();
        $this->getJson('/api/projects')->assertForbidden();
    }

    public function test_published_website_content_stays_visible_to_visitors(): void
    {
        $this->world();
        $this->shipped();
        $this->getJson('/api/public/site')->assertOk();
    }

    public function test_the_president_sets_up_every_role_and_nobody_else_can(): void
    {
        $this->world();
        $this->shipped();
        $this->as('president_admin');
        $made = [['security_admin', null], ['admin_maintenance', null]];
        foreach (array_keys(config('roles.departments')) as $dept) {
            $made[] = ['staff', $dept];
        }
        foreach ($made as $i => [$role, $dept]) {
            $body = ['name' => "Person $i", 'email' => "person$i@insania.test", 'role' => $role, 'password' => 'Str0ng#Passw0rd'] + ($dept ? ['department_key' => $dept] : []);
            $id = $this->postJson('/api/users', $body)->assertCreated()->assertJsonPath('data.role', $role)->json('data.id');
            $this->assertNotNull($id);
        }
        $this->postJson('/api/users', ['name' => 'Nope', 'email' => 'nope@insania.test', 'role' => 'system_admin', 'password' => 'Str0ng#Passw0rd'])->assertStatus(422);
        $this->postJson('/api/users', ['name' => 'Fam', 'email' => 'fam@insania.test', 'role' => 'user', 'password' => 'Str0ng#Passw0rd'])->assertStatus(422);
        $listed = collect($this->getJson('/api/users')->assertOk()->json('data'))->pluck('email')->all();
        $this->assertContains('person0@insania.test', $listed);

        // the President's own account is managed by the System Admin only, never from the Accounts screen
        $pres = User::where('role', 'president_admin')->first();
        $this->patchJson("/api/users/{$pres->id}", ['name' => 'X'])->assertForbidden();
        $this->postJson("/api/users/{$pres->id}/password", ['password' => 'Other#Passw0rd99'])->assertForbidden();

        // the people the President created sign in to an empty office (their own features are still off)
        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/login', ['email' => 'person0@insania.test', 'password' => 'Str0ng#Passw0rd'])->assertOk()->json('data.token');
        $this->withHeader('Authorization', 'Bearer '.$token)->getJson('/api/users')->assertForbidden();

        // and nobody but the President holds accounts
        foreach (['security_admin', 'system_admin', 'admin_maintenance', 'staff:aytam', 'user'] as $role) {
            $this->as($role);
            $this->getJson('/api/users')->assertForbidden();
        }
    }

    public function test_aytam_staff_keep_orphan_records_and_profiles_and_nobody_else_can(): void
    {
        $this->world();
        $this->shipped();
        $this->as('staff:aytam');
        $made = $this->postJson('/api/children', [
            'name_en' => 'Zyxwv Test', 'name_ar' => 'عمر', 'gender' => 'm', 'birth_date' => now()->subYears(9)->toDateString(), 'city' => 'Pagadian',
            'grade' => 'Grade 3', 'health_status' => 'good', 'mother_name' => 'Mother T', 'brothers' => 1, 'sisters' => 2,
            'guardian' => ['name' => 'Aunt T', 'relation' => 'Aunt', 'phone' => '0917000000'],
        ])->assertCreated()->assertJsonPath('data.age', 9)->json('data');
        $this->getJson("/api/children/{$made['id']}")->assertOk()->assertJsonPath('data.name_en', 'Zyxwv Test')->assertJsonPath('data.guardian_detail.relation', 'Aunt')->assertJsonPath('data.created_at', now()->toDateString());
        $this->patchJson("/api/children/{$made['id']}", ['grade' => 'Grade 4', 'status' => 'active', 'guardian' => ['name' => 'Aunt T', 'relation' => 'Aunt', 'phone' => '0917111111']])
            ->assertOk()->assertJsonPath('data.personal.grade', 'Grade 4')->assertJsonPath('data.guardian_detail.phone', '0917111111');
        $this->getJson('/api/children?q=Zyxwv')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/payments')->assertForbidden();          // money is Finance's (and the President's), not Aytam's
        $this->getJson('/api/reports/summary')->assertForbidden();

        foreach (['security_admin', 'staff:finance', 'staff:projects', 'user', 'admin_maintenance', 'system_admin'] as $role) {
            $this->as($role);
            $this->getJson("/api/children/{$made['id']}")->assertForbidden();
            $this->patchJson("/api/children/{$made['id']}", ['grade' => 'X'])->assertForbidden();
        }
    }

    public function test_home_figures_are_shaped_by_role(): void
    {
        $this->world();
        $this->shipped();

        $this->as('staff:aytam');
        $d = $this->getJson('/api/dashboard')->assertOk()->json('data');
        $this->assertSame(\App\Models\Child::count(), array_sum($d['children']['by_status']));
        $this->assertLessThanOrEqual(5, count($d['children']['latest']));
        $this->assertArrayHasKey('name_en', $d['children']['latest'][0]);
        $this->assertArrayNotHasKey('security', $d);
        $this->assertSame(\App\Models\Child::whereNotNull('birth_date')->count(), array_sum($d['children']['by_age']));
        $this->assertSame(['0-4', '5-8', '9-12', '13-16', '17+'], array_keys($d['children']['by_age']));
        $this->assertSame(0, $d['children']['samples']);

        $this->as('president_admin');
        $d = $this->getJson('/api/dashboard')->assertOk()->json('data');
        $this->assertArrayHasKey('children', $d);
        $this->assertGreaterThan(0, array_sum($d['security']['by_role']));
        $this->assertArrayHasKey('role', $d['security']['latest_users'][0]);
        $this->assertStringNotContainsString('password', json_encode($d));
        $this->assertStringNotContainsString('@', json_encode($d['security']));    // no email addresses on Home

        foreach (['system_admin', 'admin_maintenance', 'staff:finance', 'user'] as $role) {
            $this->as($role);
            $d = $this->getJson('/api/dashboard')->assertOk()->json('data');
            $this->assertArrayNotHasKey('children', $d, $role);
            $this->assertArrayNotHasKey('security', $d, $role);
        }
    }
}
