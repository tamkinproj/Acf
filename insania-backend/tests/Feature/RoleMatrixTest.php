<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/**
 * The agreed role matrix, enforced. For every role and every protected endpoint, the role is let in only if it should be.
 * "Let in" means the request got past the permission gate (it may still be refused for bad input).
 */
class RoleMatrixTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    /** endpoint => [method, path template, body, roles allowed] */
    private function endpoints(): array
    {
        $office = ['president_admin', 'staff:aytam'];

        return [
            'children list' => ['GET', '/api/children', [], $office],
            'child profile' => ['GET', '/api/children/{c}', [], $office],
            'child create' => ['POST', '/api/children', ['name_en' => 'Matrix Child'], $office],
            'child update' => ['PATCH', '/api/children/{c}', ['city' => 'Molave'], $office],
            'bank verify' => ['PUT', '/api/children/{c}/bank/verification', ['verified' => true], $office],
            'documents list' => ['GET', '/api/children/{c}/documents', [], $office],
            'payments list' => ['GET', '/api/payments', [], ['president_admin', 'staff:finance']],
            'mark cash' => ['POST', '/api/payments/{cash}/mark-cash', ['month' => '2026-11'], ['president_admin', 'staff:finance']],
            'report summary' => ['GET', '/api/reports/summary', [], ['president_admin', 'staff:finance']],
            'report csv' => ['GET', '/api/reports/payments.csv', [], ['president_admin', 'staff:finance']],
            'projects list' => ['GET', '/api/projects', [], ['president_admin', 'staff:projects']],
            'project create' => ['POST', '/api/projects', ['name_en' => 'Matrix Project'], ['president_admin', 'staff:projects']],
            'system health' => ['GET', '/api/system/health', [], ['system_admin', 'admin_maintenance']],
            'system settings' => ['GET', '/api/system/settings', [], ['system_admin']],
            'site draft' => ['GET', '/api/site', [], ['president_admin']],
            'site save' => ['PUT', '/api/site', ['content' => []], ['president_admin']],
            'site photo' => ['POST', '/api/site/images', [], ['president_admin']],
            'site publish' => ['POST', '/api/site/publish', [], ['president_admin']],
            'updates list' => ['GET', '/api/system/updates', [], ['system_admin']],
            'updates check' => ['POST', '/api/system/updates/check', [], ['system_admin']],
            'first president admin' => ['POST', '/api/system/president-admin', [], ['system_admin']],
            'api keys list' => ['GET', '/api/system/api-keys', [], ['system_admin']],
            'api keys create' => ['POST', '/api/system/api-keys', ['name' => 'Matrix'], ['system_admin']],
            'system settings update' => ['PATCH', '/api/system/settings', ['currency' => 'AED'], ['system_admin']],
            'users list' => ['GET', '/api/users', [], ['president_admin', 'security_admin']],
            'users create' => ['POST', '/api/users', ['name' => 'X'], ['president_admin', 'security_admin']],
            'audit log' => ['GET', '/api/audit', [], ['president_admin', 'security_admin']],
            'my family' => ['GET', '/api/my/family', [], ['user']],
        ];
    }

    private function roles(): array
    {
        return ['system_admin', 'admin_maintenance', 'security_admin', 'president_admin', 'staff:aytam', 'staff:finance', 'staff:projects', 'staff:relief', 'user'];
    }

    private function actAs(string $role): void
    {
        if ($role === 'staff:relief') {
            $u = User::firstOrCreate(['email' => 'relief@insania.test'], ['name' => 'Relief', 'password' => 'x', 'role' => 'staff', 'foundation_id' => 1, 'active' => true,
                'department_id' => Department::where('key', 'relief')->value('id')]);
            Sanctum::actingAs($u, ['*']);

            return;
        }
        $this->actingAsRole($role);
    }

    public function test_every_role_gets_in_only_where_the_matrix_says(): void
    {
        $this->world();
        $c = $this->childId('ACF-0001');
        $cash = $this->childId('ACF-0006');
        $mistakes = [];

        foreach ($this->endpoints() as $name => [$method, $path, $body, $allowed]) {
            foreach ($this->roles() as $role) {
                $this->actAs($role);
                $res = $this->json($method, str_replace(['{c}', '{cash}'], [$c, $cash], $path), $body);
                $status = $res->getStatusCode();
                $letIn = ! in_array($status, [401, 403], true);

                if ($status >= 500) {
                    $mistakes[] = "$role on '$name' caused a server error ($status)";
                }

                if ($letIn !== in_array($role, $allowed, true)) {
                    $mistakes[] = "$role on '$name' got $status (should be ".(in_array($role, $allowed, true) ? 'allowed' : 'refused').')';
                }
            }
        }

        $this->assertSame([], $mistakes, implode("\n", $mistakes));
    }

    public function test_technical_roles_can_never_reach_children_or_money(): void
    {
        $this->world();
        $c = $this->childId('ACF-0001');

        foreach (['system_admin', 'admin_maintenance', 'security_admin'] as $role) {
            $this->actingAsRole($role);
            foreach (['/api/children', "/api/children/$c", "/api/children/$c/documents", '/api/payments', '/api/reports/summary', '/api/reports/payments.csv', '/api/my/family'] as $url) {
                $this->getJson($url)->assertForbidden();
            }
        }
    }

    public function test_every_account_can_read_its_own_profile_and_dashboard(): void
    {
        $this->world();

        foreach (array_keys($this->accounts) as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/me')->assertOk()->assertJsonPath('data.role', $this->accounts[$role] ? User::where('email', $this->accounts[$role])->value('role') : null);
            $this->getJson('/api/dashboard')->assertOk();
        }
    }

    public function test_a_switched_off_account_has_no_permissions_even_with_a_valid_token(): void
    {
        $this->world();
        $user = $this->actingAsRole('president_admin');
        $user->update(['active' => false]);

        $this->getJson('/api/children')->assertForbidden();
    }

    public function test_staff_without_a_department_can_do_nothing_in_the_office(): void
    {
        $this->world();
        $u = User::create(['name' => 'No Dept', 'email' => 'nodept@insania.test', 'password' => 'x', 'role' => 'staff', 'foundation_id' => 1, 'active' => true]);
        Sanctum::actingAs($u, ['*']);

        foreach (['/api/children', '/api/payments', '/api/projects'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }
}
