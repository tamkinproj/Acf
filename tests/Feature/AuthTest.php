<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
    }

    public function test_login_returns_profile_permissions_and_audits(): void
    {
        $staff = $this->makeUser('staff', 'staff@example.test');

        $this->postJson('/api/auth/login', ['email' => 'STAFF@example.test', 'password' => 'Correct-horse-9'])
            ->assertOk()
            ->assertJsonPath('data.user.email', 'staff@example.test')
            ->assertJsonPath('data.user.role.key', 'staff')
            ->assertJsonPath('data.foundation.name', 'Test Foundation')
            ->assertJsonFragment(['locations.manage'])
            ->assertJsonMissing(['users.manage'])
            ->assertJsonMissing(['password']);

        $this->assertAuthenticatedAs($staff);
        $this->assertTrue(AuditLog::where('action', 'auth.login')->where('user_id', $staff->id)->exists());
        $this->assertNotNull($staff->fresh()->last_login_at);
        $this->assertSame(1, $staff->fresh()->version, 'login bookkeeping must not bump the replication version');
    }

    public function test_failures_are_indistinguishable_and_audited(): void
    {
        $this->makeUser('staff', 'staff@example.test');
        $disabled = $this->makeUser('staff', 'off@example.test');
        $disabled->update(['status' => 'disabled']);

        $unknown = $this->postJson('/api/auth/login', ['email' => 'nobody@example.test', 'password' => 'Correct-horse-9']);
        $wrong = $this->postJson('/api/auth/login', ['email' => 'staff@example.test', 'password' => 'wrong-password-1']);
        $off = $this->postJson('/api/auth/login', ['email' => 'off@example.test', 'password' => 'Correct-horse-9']);

        foreach ([$unknown, $wrong, $off] as $r) {
            $r->assertStatus(401)->assertJsonPath('code', 'INVALID_CREDENTIALS');
        }
        $this->assertSame($unknown->json('message'), $wrong->json('message'));
        $this->assertSame($wrong->json('message'), $off->json('message'));
        $this->assertGuest();
        $this->assertSame(3, AuditLog::where('action', 'auth.login_failed')->count());
    }

    public function test_login_is_throttled_per_email_and_ip(): void
    {
        $this->makeUser('staff', 'staff@example.test');

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'staff@example.test', 'password' => 'bad-password-1'])->assertStatus(401);
        }
        $this->postJson('/api/auth/login', ['email' => 'staff@example.test', 'password' => 'Correct-horse-9'])
            ->assertStatus(429)->assertJsonPath('code', 'THROTTLED')->assertHeader('Retry-After');

        // A different account is not locked out by someone hammering this one.
        $this->makeUser('viewer', 'other@example.test');
        $this->postJson('/api/auth/login', ['email' => 'other@example.test', 'password' => 'Correct-horse-9'])->assertOk();
    }

    public function test_protected_routes_require_a_session_and_logout_ends_it(): void
    {
        $this->getJson('/api/auth/me')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');

        $this->postJson('/api/auth/login', ['email' => $this->admin->email, 'password' => 'Correct-horse-9'])->assertOk();
        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.user.id', $this->admin->id);

        $this->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest();
        $this->assertTrue(AuditLog::where('action', 'auth.logout')->exists());
    }

    public function test_disabling_a_user_cuts_off_an_existing_session_immediately(): void
    {
        $staff = $this->makeUser('staff');
        $this->actingAs($staff)->getJson('/api/auth/me')->assertOk();

        $staff->update(['status' => 'disabled']);
        $this->getJson('/api/auth/me')->assertStatus(401)->assertJsonPath('code', 'ACCOUNT_DISABLED');
    }

    public function test_password_change_validates_and_clears_the_forced_change_flag(): void
    {
        $user = $this->makeUser('staff');
        $user->forceFill(['must_change_password' => true])->save();

        $this->actingAs($user)->getJson('/api/dashboard/summary')->assertStatus(403)->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->getJson('/api/auth/me')->assertOk();

        $base = ['current_password' => 'Correct-horse-9'];
        $this->putJson('/api/auth/password', $base + ['password' => 'short1', 'password_confirmation' => 'short1'])->assertStatus(422);
        $this->putJson('/api/auth/password', $base + ['password' => 'onlyletterspassword', 'password_confirmation' => 'onlyletterspassword'])->assertStatus(422);
        $this->putJson('/api/auth/password', $base + ['password' => 'Correct-horse-9', 'password_confirmation' => 'Correct-horse-9'])->assertStatus(422);
        $this->putJson('/api/auth/password', ['current_password' => 'wrong-one-123', 'password' => 'Brand-new-pass-77', 'password_confirmation' => 'Brand-new-pass-77'])->assertStatus(422);
        $this->putJson('/api/auth/password', $base + ['password' => 'Brand-new-pass-77', 'password_confirmation' => 'Brand-new-pass-77'])->assertOk();

        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('Brand-new-pass-77', $user->fresh()->password));
        $this->assertTrue(AuditLog::where('action', 'auth.password_changed')->exists());
        $this->getJson('/api/dashboard/summary')->assertOk();
    }

    public function test_profile_update_cannot_touch_role_or_email(): void
    {
        $user = $this->makeUser('viewer', 'v@example.test');
        $this->actingAs($user)->patchJson('/api/auth/profile', ['name' => 'New Name', 'role_id' => $this->admin->role_id, 'email' => 'evil@example.test'])->assertOk();

        $fresh = $user->fresh();
        $this->assertSame('New Name', $fresh->name);
        $this->assertSame('v@example.test', $fresh->email);
        $this->assertSame('viewer', $fresh->role->key);
    }

    public function test_passwords_never_appear_in_any_response_or_the_sync_feed(): void
    {
        $this->actingAs($this->admin)->withToken($this->deviceToken);
        $this->getJson('/api/users')->assertOk()->assertDontSee('"password"', false)->assertDontSee('$2y$', false);
        $this->assertStringNotContainsString('$2y$', json_encode(\App\Models\SyncChange::all()->toArray()));
        $this->assertStringNotContainsString('$2y$', json_encode(AuditLog::all()->toArray()));
    }
}
