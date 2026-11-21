<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private const PW = 'DemoPass#2026';

    protected function setUp(): void
    {
        parent::setUp();
        $this->world();
    }

    private function login(string $who, string $ip = '10.0.0.1', string $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit Safari/604.1'): string
    {
        $this->app['auth']->forgetGuards();

        return $this->withServerVariables(['REMOTE_ADDR' => $ip, 'HTTP_USER_AGENT' => $ua])->postJson('/api/login', ['login' => $who, 'password' => self::PW])->assertOk()->json('data.token');
    }

    private function as(string $token): self
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_an_account_is_locked_after_ten_wrong_passwords_even_from_many_addresses(): void
    {
        for ($i = 1; $i <= 10; $i++) {
            $this->withServerVariables(['REMOTE_ADDR' => "10.1.0.$i"])->postJson('/api/login', ['login' => 'finance', 'password' => 'wrong'.$i])->assertStatus(401);
        }
        // the right password is refused too while the account is locked, from a new address
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.99'])->postJson('/api/login', ['login' => 'finance', 'password' => self::PW])->assertStatus(429);
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.98'])->postJson('/api/login', ['login' => 'FINANCE@insania.test', 'password' => self::PW])->assertOk();   // the email is a separate typed identifier: only the typed name is locked
        $this->assertSame(1, AuditLog::where('action', 'login.locked')->count());
        // other accounts are not affected
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.97'])->postJson('/api/login', ['login' => 'aytam', 'password' => self::PW])->assertOk();
        // and it unlocks by itself
        $this->travel(16)->minutes();
        $this->withServerVariables(['REMOTE_ADDR' => '10.1.0.96'])->postJson('/api/login', ['login' => 'finance', 'password' => self::PW])->assertOk();
    }

    public function test_a_privileged_sign_in_ends_after_an_hour_idle_and_other_staff_after_four(): void
    {
        $pres = $this->login('president');
        $fin = $this->login('finance', '10.0.0.2');
        $this->as($pres)->getJson('/api/me')->assertOk();
        $this->as($fin)->getJson('/api/me')->assertOk();

        $this->travel(61)->minutes();
        $this->as($pres)->getJson('/api/me')->assertStatus(401);            // President: idle limit 60 minutes
        $this->as($fin)->getJson('/api/me')->assertOk();                    // staff: still fine
        $this->assertSame(1, User::where('email', 'president@insania.test')->first()->tokens()->count() + 1, 'the idle token was removed');

        $this->travel(241)->minutes();
        $this->as($fin)->getJson('/api/me')->assertStatus(401);             // staff: idle limit 240 minutes
    }

    public function test_a_sign_in_never_outlasts_its_absolute_limit_even_when_busy(): void
    {
        $pres = $this->login('president');
        $sys = $this->login('system', '10.0.0.3');
        $fin = $this->login('finance', '10.0.0.4');
        foreach (range(1, 9) as $h) {          // someone active every 55 minutes for 9 hours
            $this->travel(55)->minutes();
            $this->as($pres)->getJson('/api/me');
            $this->as($fin)->getJson('/api/me');
            $this->as($sys)->getJson('/api/me');
        }
        $this->as($pres)->getJson('/api/me')->assertStatus(401);            // 8 hours for the President and System Admin
        $this->as($sys)->getJson('/api/me')->assertStatus(401);
        $this->as($fin)->getJson('/api/me')->assertOk();                    // 12 hours for staff
        $this->travel(4)->hours();
        $this->as($fin)->getJson('/api/me')->assertStatus(401);
    }

    public function test_account_changes_need_the_signed_in_persons_own_password(): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $new = ['name' => 'Back Door', 'email' => 'back.door@example.org', 'role' => 'staff', 'department_key' => 'hr', 'password' => 'Rv7!kqLm29xZ'];
        $this->postJson('/api/users', $new + ['confirm_password' => ''])->assertStatus(422)->assertJsonPath('code', 'WRONG_PASSWORD');
        $this->postJson('/api/users', $new + ['confirm_password' => 'wrong'])->assertStatus(422)->assertJsonValidationErrors('confirm_password');
        $this->assertDatabaseMissing('users', ['email' => 'back.door@example.org']);
        $this->assertSame(2, AuditLog::where('action', 'stepup.failed')->count());
        $this->postJson('/api/users', $new + ['confirm_password' => self::PW])->assertCreated();
        $id = User::where('email', 'back.door@example.org')->value('id');

        $this->patchJson("/api/users/$id", ['name' => 'Renamed', 'confirm_password' => ''])->assertOk();                          // a name is harmless
        foreach ([['role' => 'admin_maintenance'], ['active' => false], ['department_key' => 'aytam'], ['email' => 'other@example.org']] as $change) {
            $this->patchJson("/api/users/$id", $change + ['confirm_password' => ''])->assertStatus(422)->assertJsonPath('code', 'WRONG_PASSWORD');
        }
        $this->patchJson("/api/users/$id", ['active' => false, 'confirm_password' => self::PW])->assertOk();
        $this->postJson("/api/users/$id/password", ['password' => 'Bt4#nWq81vLx', 'confirm_password' => ''])->assertStatus(422);
        $this->postJson("/api/users/$id/password", ['password' => 'Bt4#nWq81vLx', 'confirm_password' => self::PW])->assertOk();

        // five wrong tries lock the confirmation for a few minutes (a stolen unlocked phone cannot guess it)
        for ($i = 0; $i < 4; $i++) {
            $this->postJson("/api/users/$id/password", ['password' => 'Bt4#nWq81vLx', 'confirm_password' => 'nope'.$i])->assertStatus(422);
        }
        $this->postJson("/api/users/$id/password", ['password' => 'Bt4#nWq81vLx', 'confirm_password' => 'nope'])->assertStatus(422);
        $this->postJson("/api/users/$id/password", ['password' => 'Bt4#nWq81vLx', 'confirm_password' => self::PW])->assertStatus(429);
    }

    public function test_weak_and_obvious_passwords_are_refused_everywhere_a_password_is_set(): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $base = ['name' => 'Layla Hamdan', 'email' => 'layla.hamdan@example.org', 'role' => 'staff', 'department_key' => 'hr'];
        foreach (['Passw0rd!2026x', 'Welcome#2026ab', 'Qwerty#Abc12345', 'Insania#2026ab', 'Layla#Hamdan1a', 'Aaaa#Bbbb#1234x', 'Abcd#Efgh9z!Q', 'Xyz#1234#Mnop'] as $weak) {
            $this->postJson('/api/users', $base + ['password' => $weak])->assertStatus(422)->assertJsonValidationErrors('password');
        }
        $this->postJson('/api/users', $base + ['password' => 'Rv7!kqLm29xZ'])->assertCreated();

        // changing your own password uses the same rules, and you cannot use your own name or username
        $token = $this->login('finance', '10.0.0.5');
        $mine = User::where('username', 'finance')->first();
        foreach (['Finance#2026Zq!', 'Welcome#2026ab', str_replace('finance', 'FINANCE', 'Rv7finance!kqLm')] as $weak) {
            $this->as($token)->postJson('/api/me/password', ['current_password' => self::PW, 'password' => $weak])->assertStatus(422);
        }
        $this->as($token)->postJson('/api/me/password', ['current_password' => self::PW, 'password' => 'Hx5#bTr93qWn'])->assertOk();
        $this->assertSame('finance', $mine->username);
    }

    public function test_people_see_their_own_devices_and_can_sign_one_or_all_others_out(): void
    {
        $phone = $this->login('aytam', '10.2.0.1', 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari/604.1');
        $laptop = $this->login('aytam', '10.2.0.2', 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0 Safari/537.36');
        $other = $this->login('finance', '10.2.0.3');

        $rows = $this->as($phone)->getJson('/api/me/sessions')->assertOk()->json('data');
        $this->assertCount(2, $rows);
        $this->assertEqualsCanonicalizing(['iPhone · Safari', 'Windows · Chrome'], array_column($rows, 'device'));
        $this->assertSame([true], array_values(array_unique(array_column(array_filter($rows, fn ($r) => $r['device'] === 'iPhone · Safari'), 'current'))));

        $laptopId = collect($rows)->firstWhere('device', 'Windows · Chrome')['id'];
        $finRows = $this->as($other)->getJson('/api/me/sessions')->json('data');
        $this->as($other)->deleteJson('/api/me/sessions/'.$laptopId)->assertNotFound();                      // never someone else's sign-in
        $this->as($phone)->deleteJson('/api/me/sessions/'.$laptopId)->assertOk();
        $this->as($laptop)->getJson('/api/me')->assertStatus(401);

        $second = $this->login('aytam', '10.2.0.4');
        $this->as($phone)->postJson('/api/me/sessions/end-others')->assertOk()->assertJsonPath('data.ended', 1);
        $this->as($second)->getJson('/api/me')->assertStatus(401);
        $this->as($phone)->getJson('/api/me')->assertOk();                                                   // this phone stays signed in
        $this->as($other)->getJson('/api/me')->assertOk();
        $this->assertCount(1, $finRows);
    }

    public function test_the_system_admin_sees_a_security_check_with_no_personal_data(): void
    {
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('system_admin');
        $d = $this->getJson('/api/system/security')->assertOk()->json('data');
        $keys = array_column($d['checks'], 'key');
        foreach (['https', 'debug_off', 'production', 'app_key', 'env_private', 'cors_own_site', 'update_key', 'no_demo_accounts', 'strong_limits'] as $k) {
            $this->assertContains($k, $keys);
        }
        $byKey = array_column($d['checks'], 'ok', 'key');
        $this->assertFalse($byKey['no_demo_accounts']);          // the test world has the demo accounts: the check says so
        $this->assertTrue($byKey['cors_own_site']);
        $this->assertTrue($byKey['strong_limits']);
        $this->assertSame(60, $d['limits']['idle_minutes']['privileged']);
        $this->assertStringNotContainsString('@', json_encode($d));
        foreach (['president_admin', 'staff:finance', 'user'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->getJson('/api/system/security')->assertForbidden();
        }
    }

    public function test_cross_origin_calls_are_allowed_only_from_this_site_by_default(): void
    {
        $this->assertNotContains('*', config('cors.allowed_origins'));
        $r = $this->withHeaders(['Origin' => 'https://evil.example'])->getJson('/api/public/apply');
        $this->assertNotSame('*', $r->headers->get('Access-Control-Allow-Origin'));
        $this->assertNotSame('https://evil.example', $r->headers->get('Access-Control-Allow-Origin'));   // a browser will refuse to hand the answer to that site
    }

    public function test_a_request_without_a_sign_in_gets_a_clean_401_even_if_it_does_not_ask_for_json(): void
    {
        foreach (['/api/users', '/api/children', '/api/me', '/api/avatars/1', '/api/system/security', '/api/chat/conversations'] as $url) {
            $r = $this->get($url);                                   // a plain browser request: no Accept header, no token
            $r->assertStatus(401);
            $this->assertSame('UNAUTHENTICATED', $r->json('code'), $url);
            $this->assertStringNotContainsString('Exception', $r->getContent());
        }
        $this->withHeader('Authorization', 'Bearer not-a-real-token')->get('/api/me')->assertStatus(401);
    }
}
