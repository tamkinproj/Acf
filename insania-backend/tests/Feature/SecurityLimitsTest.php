<?php

namespace Tests\Feature;

use App\Support\SecurityLimits;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** The System Admin sets the sign-in and request limits; each value must stay inside its safe range. */
class SecurityLimitsTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_only_the_system_admin_reads_and_changes_them(): void
    {
        $this->world();
        foreach (['security_admin', 'president_admin', 'staff:aytam', 'admin_maintenance'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->getJson('/api/system/limits')->assertForbidden();
            $this->patchJson('/api/system/limits', ['admin_password' => 'DemoPass#2026', 'lockout_attempts' => 4])->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('system_admin');
        $this->getJson('/api/system/limits')->assertOk()->assertJsonPath('data.values.lockout_attempts', 10)->assertJsonPath('data.ranges.lockout_minutes.min', 1);
    }

    public function test_a_change_needs_the_password_and_stays_inside_the_ranges(): void
    {
        $this->world();
        $this->actingAsRole('system_admin');
        $this->patchJson('/api/system/limits', ['admin_password' => 'not my password', 'lockout_attempts' => 4])->assertStatus(422)->assertJsonPath('code', 'WRONG_PASSWORD');
        $this->patchJson('/api/system/limits', ['admin_password' => 'DemoPass#2026', 'lockout_attempts' => 1])->assertStatus(422);
        $this->patchJson('/api/system/limits', ['admin_password' => 'DemoPass#2026', 'login_per_minute' => 100000])->assertStatus(422);
        $this->patchJson('/api/system/limits', ['admin_password' => 'DemoPass#2026', 'lockout_attempts' => 4, 'attempts_per_minute' => 6])->assertStatus(422);
        $this->patchJson('/api/system/limits', ['admin_password' => 'DemoPass#2026', 'lockout_attempts' => 6, 'lockout_minutes' => 30, 'idle_minutes_privileged' => 30])
            ->assertOk()->assertJsonPath('data.values.lockout_attempts', 6)->assertJsonPath('data.values.lockout_minutes', 30);
        $this->assertSame(30, SecurityLimits::get('idle_minutes_privileged'));
        $this->assertSame(5, SecurityLimits::get('attempts_per_minute'), 'a value not sent keeps its value');
        $this->assertDatabaseHas('audit_logs', ['action' => 'limits.changed']);
    }

    public function test_a_lower_lockout_is_enforced_on_sign_in(): void
    {
        $this->world();
        $this->actingAsRole('system_admin');
        $this->patchJson('/api/system/limits', ['admin_password' => 'DemoPass#2026', 'lockout_attempts' => 3, 'attempts_per_minute' => 3])->assertOk();
        $this->app['auth']->forgetGuards();
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/login', ['login' => 'aytam@insania.test', 'password' => 'wrong-'.$i])->assertStatus(401);
        }
        $this->postJson('/api/login', ['login' => 'aytam@insania.test', 'password' => 'DemoPass#2026'])->assertStatus(429)->assertJsonPath('code', 'THROTTLED');
    }
}
