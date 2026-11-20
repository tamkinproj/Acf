<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class SetupTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function stepsFor(string $role): array
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsRole($role);

        return $this->getJson('/api/setup')->assertOk()->json('data');
    }

    public function test_the_system_admin_creates_the_first_security_admin_once(): void
    {
        $this->world();
        User::whereIn('role', ['security_admin', 'president_admin'])->delete();
        $this->actingAsRole('system_admin');

        $this->assertSame(2, $this->getJson('/api/setup')->json('data.required_left'));
        $body = ['name' => 'First Security', 'email' => 'first.sec@insania.test', 'password' => 'Str0ng#Passw0rd'];
        $this->postJson('/api/system/security-admin', ['password' => 'short'] + $body)->assertStatus(422);
        $this->postJson('/api/system/security-admin', $body)->assertCreated()->assertJsonPath('data.role', 'security_admin');
        $this->postJson('/api/system/security-admin', ['email' => 'second@insania.test'] + $body)->assertStatus(422)->assertJsonPath('code', 'ALREADY_EXISTS');
        $this->assertSame(1, $this->getJson('/api/setup')->json('data.required_left'));   // the President is still to do

        $login = $this->postJson('/api/login', ['email' => 'first.sec@insania.test', 'password' => 'Str0ng#Passw0rd'])->assertOk();
        $this->assertSame('security_admin', $login->json('data.user.role'));
    }

    public function test_the_system_admin_also_creates_the_first_president_admin_once(): void
    {
        $this->world();
        User::where('role', 'president_admin')->delete();
        $this->actingAsRole('system_admin');
        $body = ['name' => 'First President', 'email' => 'first.pres@insania.test', 'password' => 'Str0ng#Passw0rd'];

        $this->postJson('/api/system/president-admin', ['email' => 'not-an-email'] + $body)->assertStatus(422);
        $this->postJson('/api/system/president-admin', $body)->assertCreated()->assertJsonPath('data.role', 'president_admin');
        $this->postJson('/api/system/president-admin', ['email' => 'second.pres@insania.test'] + $body)->assertStatus(422)->assertJsonPath('code', 'ALREADY_EXISTS');

        // the new President reaches every office feature; the System Admin still reaches none of them
        $login = $this->postJson('/api/login', ['email' => 'first.pres@insania.test', 'password' => 'Str0ng#Passw0rd'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$login->json('data.token'));
        foreach (['/api/children', '/api/payments', '/api/projects', '/api/reports/summary'] as $url) {
            $this->getJson($url)->assertOk();
        }
        $this->getJson('/api/system/health')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_steps_follow_the_role_and_use_counts_only(): void
    {
        $this->world();
        $this->assertSame(['security_admin', 'president_admin', 'org_settings', 'partner_key'], array_column($this->stepsFor('system_admin')['steps'], 'key'));
        $sec = $this->stepsFor('security_admin');
        $this->assertSame('president_admin', $sec['steps'][0]['key']);
        $this->assertTrue($sec['steps'][0]['done']);                 // the demo world already has a President Admin
        $this->assertSame(['first_child', 'first_publish', 'first_project'], array_column($this->stepsFor('president_admin')['steps'], 'key'));
        foreach (['staff:aytam', 'user', 'admin_maintenance'] as $role) {
            $this->assertSame([], $this->stepsFor($role)['steps']);
        }
        // a technical role never receives anything about children or money
        $this->assertStringNotContainsString('child', json_encode($this->stepsFor('system_admin')).json_encode($this->stepsFor('security_admin')));
    }

    public function test_the_security_admin_sees_a_step_turn_done_after_creating_the_account(): void
    {
        $this->world();
        User::where('role', 'president_admin')->delete();
        $this->actingAsRole('security_admin');
        $this->assertSame(1, $this->getJson('/api/setup')->json('data.required_left'));
        $this->postJson('/api/users', ['name' => 'Pres', 'email' => 'p@insania.test', 'role' => 'president_admin', 'password' => 'Str0ng#Passw0rd'])->assertCreated();
        $this->assertSame(0, $this->getJson('/api/setup')->json('data.required_left'));
    }

    public function test_the_first_login_flag_is_saved_in_preferences(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $this->patchJson('/api/me/preferences', ['setup_seen' => true])->assertOk();
        $this->assertTrue($this->getJson('/api/me')->json('data.preferences.setup_seen'));
    }
}
