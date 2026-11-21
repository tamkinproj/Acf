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

    public function test_the_system_admin_creates_the_first_president_admin_once_and_the_president_creates_the_rest(): void
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

        // the President creates every other role, including the technical ones
        foreach ([['security_admin', null], ['admin_maintenance', null], ['staff', 'finance'], ['user', null]] as $i => [$role, $dept]) {
            $body = ['name' => "Made $i", 'email' => "made$i@insania.test", 'role' => $role, 'password' => 'Str0ng#Passw0rd'] + ($dept ? ['department_key' => $dept] : []);
            if ($role === 'user') {
                $this->postJson('/api/users', $body)->assertStatus(422);             // a family account needs a guardian record
                continue;
            }
            $this->postJson('/api/users', $body)->assertCreated()->assertJsonPath('data.role', $role);
        }
        $this->postJson('/api/users', ['name' => 'Sneaky', 'email' => 'sneaky@insania.test', 'role' => 'system_admin', 'password' => 'Str0ng#Passw0rd'])->assertStatus(422);
    }

    public function test_the_system_admin_cannot_create_any_other_account_or_see_the_office(): void
    {
        $this->world();
        $this->actingAsRole('system_admin');
        $this->postJson('/api/system/security-admin', ['name' => 'X', 'email' => 'x@insania.test', 'password' => 'Str0ng#Passw0rd'])->assertStatus(404);
        $this->postJson('/api/users', ['name' => 'X', 'email' => 'x@insania.test', 'role' => 'staff', 'department_key' => 'finance', 'password' => 'Str0ng#Passw0rd'])->assertForbidden();
        foreach (['/api/users', '/api/audit', '/api/children', '/api/payments', '/api/projects', '/api/reports/summary', '/api/site'] as $url) {
            $this->getJson($url)->assertForbidden();
        }
    }

    public function test_steps_follow_the_role_and_use_counts_only(): void
    {
        $this->world();
        $this->assertSame(['president_admin', 'org_settings', 'partner_key'], array_column($this->stepsFor('system_admin')['steps'], 'key'));
        $sec = $this->stepsFor('security_admin');
        $this->assertSame('staff_aytam', $sec['steps'][0]['key']);
        $this->assertSame(['first_child', 'staff_aytam', 'staff_finance', 'staff_projects', 'security_admin', 'maintenance', 'first_publish', 'first_project'], array_column($this->stepsFor('president_admin')['steps'], 'key'));
        foreach (['staff:aytam', 'user', 'admin_maintenance'] as $role) {
            $this->assertSame([], $this->stepsFor($role)['steps']);
        }
        // a technical role never receives anything about children or money
        $this->assertStringNotContainsString('child', json_encode($this->stepsFor('system_admin')).json_encode($this->stepsFor('security_admin')));
    }

    public function test_the_first_login_flag_is_saved_in_preferences(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $this->patchJson('/api/me/preferences', ['setup_seen' => true])->assertOk();
        $this->assertTrue($this->getJson('/api/me')->json('data.preferences.setup_seen'));
    }

    public function test_anyone_changes_their_own_password_and_other_devices_are_signed_out(): void
    {
        $this->world();
        $user = User::where('role', 'staff')->first();
        $user->update(['password' => 'Old#Passw0rd123']);
        $other = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Old#Passw0rd123'])->assertOk()->json('data.token');
        $mine = $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Old#Passw0rd123'])->assertOk()->json('data.token');
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$mine);

        $this->postJson('/api/me/password', ['current_password' => 'wrong', 'password' => 'New#Passw0rd456'])->assertStatus(422)->assertJsonPath('code', 'WRONG_PASSWORD');
        $this->postJson('/api/me/password', ['current_password' => 'Old#Passw0rd123', 'password' => 'weak'])->assertStatus(422);
        $this->postJson('/api/me/password', ['current_password' => 'Old#Passw0rd123', 'password' => 'Old#Passw0rd123'])->assertStatus(422);
        $this->postJson('/api/me/password', ['current_password' => 'Old#Passw0rd123', 'password' => 'New#Passw0rd456'])->assertOk();

        $this->getJson('/api/me')->assertOk();                                  // this device stays signed in
        $this->assertSame(1, $user->tokens()->count());                         // the other one was ended
        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$other)->getJson('/api/me')->assertUnauthorized();
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'Old#Passw0rd123'])->assertStatus(401);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'New#Passw0rd456'])->assertOk();
    }

    public function test_the_system_admin_changes_the_president_admin_only_with_their_own_password(): void
    {
        $this->world();
        $pres = User::where('role', 'president_admin')->first();
        $pres->update(['password' => 'Old#Passw0rd123']);
        $old = $this->postJson('/api/login', ['email' => $pres->email, 'password' => 'Old#Passw0rd123'])->assertOk()->json('data.token');
        $sys = User::where('role', 'system_admin')->first();
        $sys->update(['password' => 'Sys#Passw0rd123']);
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('system_admin');
        $sys = User::where('role', 'system_admin')->first();
        $sys->update(['password' => 'Sys#Passw0rd123']);

        $this->getJson('/api/system/president-admin')->assertOk()->assertJsonPath('data.email', $pres->email)->assertJsonMissingPath('data.password');
        $this->patchJson('/api/system/president-admin', ['name' => 'X'])->assertStatus(422);                                              // needs the confirm password
        $this->patchJson('/api/system/president-admin', ['admin_password' => 'nope', 'name' => 'X'])->assertStatus(422)->assertJsonPath('code', 'WRONG_PASSWORD');
        $this->patchJson('/api/system/president-admin', ['admin_password' => 'Sys#Passw0rd123'])->assertStatus(422);                       // nothing to change
        $this->patchJson('/api/system/president-admin', ['admin_password' => 'Sys#Passw0rd123', 'email' => $sys->email])->assertStatus(422); // email already used
        $this->patchJson('/api/system/president-admin', ['admin_password' => 'Sys#Passw0rd123', 'password' => 'weak'])->assertStatus(422);

        $this->patchJson('/api/system/president-admin', ['admin_password' => 'Sys#Passw0rd123', 'name' => 'New President', 'email' => 'new.pres@insania.test', 'password' => 'Fresh#Passw0rd789'])
            ->assertOk()->assertJsonPath('data.name', 'New President')->assertJsonPath('data.id', $pres->id);

        $this->app['auth']->forgetGuards();
        $this->withHeader('Authorization', 'Bearer '.$old)->getJson('/api/me')->assertUnauthorized();                                       // the old person is out
        $this->postJson('/api/login', ['email' => $pres->email, 'password' => 'Old#Passw0rd123'])->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'new.pres@insania.test', 'password' => 'Fresh#Passw0rd789'])->assertOk()->assertJsonPath('data.user.role', 'president_admin');
        $this->assertDatabaseHas('audit_logs', ['action' => 'president.changed']);
        $this->assertStringNotContainsString('Fresh#Passw0rd789', json_encode(\DB::table('audit_logs')->get()));
    }

    public function test_no_other_role_can_read_or_change_the_president_admin(): void
    {
        $this->world();
        foreach (['president_admin', 'security_admin', 'admin_maintenance', 'staff:finance', 'user'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->getJson('/api/system/president-admin')->assertForbidden();
            $this->patchJson('/api/system/president-admin', ['admin_password' => 'x', 'name' => 'Y'])->assertForbidden();
        }
    }
}
