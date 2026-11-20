<?php

namespace Tests\Feature;

use App\Models\ApiKey;
use App\Models\Guardian;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class UsersFamilyPartnerAndDashboardTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private const STRONG = 'BrandNewPass#2026';

    /* ---------- Security Admin: accounts ---------- */

    public function test_security_admin_creates_staff_with_a_department_and_the_account_works(): void
    {
        $this->world();
        $this->actingAsRole('security_admin');

        $res = $this->postJson('/api/users', ['name' => 'New Finance', 'email' => 'newfin@insania.test', 'password' => self::STRONG, 'role' => 'staff', 'department_key' => 'finance'])
            ->assertCreated();
        $this->assertSame('finance', $res->json('data.department'));
        $this->assertContains('payments.view', $res->json('data.permissions'));
        $this->assertArrayNotHasKey('password', $res->json('data'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.created']);

        $this->app['auth']->forgetGuards();
        $token = $this->postJson('/api/login', ['email' => 'newfin@insania.test', 'password' => self::STRONG])->assertOk()->json('data.token');
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/payments')->assertOk();
    }

    public function test_the_role_rules_are_enforced_when_creating_accounts(): void
    {
        $this->world();
        $this->actingAsRole('security_admin');
        $base = ['name' => 'X', 'email' => 'x@insania.test', 'password' => self::STRONG];

        $this->postJson('/api/users', $base + ['role' => 'system_admin'])->assertStatus(422);                       // nobody is promoted to System Admin here
        $this->postJson('/api/users', $base + ['role' => 'staff'])->assertStatus(422)->assertJsonPath('code', 'DEPARTMENT_REQUIRED');
        $this->postJson('/api/users', $base + ['role' => 'staff', 'department_key' => 'wizardry'])->assertStatus(422);
        $this->postJson('/api/users', $base + ['role' => 'user'])->assertStatus(422)->assertJsonPath('code', 'GUARDIAN_REQUIRED');
        $this->postJson('/api/users', ['password' => 'short', 'role' => 'president_admin'] + $base)->assertStatus(422);   // weak password
        $this->postJson('/api/users', ['email' => 'president@insania.test'] + $base + ['role' => 'president_admin'])->assertStatus(422);   // duplicate email

        $guardian = Guardian::first()->id;
        $this->postJson('/api/users', $base + ['role' => 'user', 'guardian_id' => $guardian])->assertCreated()->assertJsonPath('data.role', 'user');
        $this->assertSame(0, User::where('email', 'x@insania.test')->where('role', 'system_admin')->count());
    }

    public function test_a_system_admin_account_cannot_be_touched_through_the_api(): void
    {
        $this->world();
        $this->actingAsRole('security_admin');
        $sys = User::where('email', 'system@insania.test')->value('id');

        $this->patchJson("/api/users/$sys", ['name' => 'Hijacked'])->assertForbidden();
        $this->postJson("/api/users/$sys/password", ['password' => self::STRONG])->assertForbidden();
        $this->postJson("/api/users/$sys/revoke-tokens")->assertForbidden();
        $this->assertSame('System Admin', User::find($sys)->name);
    }

    public function test_nobody_can_change_their_own_role_or_switch_themselves_off(): void
    {
        $this->world();
        $me = $this->actingAsRole('security_admin');

        $this->patchJson("/api/users/{$me->id}", ['role' => 'president_admin'])->assertStatus(422)->assertJsonPath('code', 'SELF_CHANGE');
        $this->patchJson("/api/users/{$me->id}", ['active' => false])->assertStatus(422)->assertJsonPath('code', 'SELF_CHANGE');
        $this->patchJson("/api/users/{$me->id}", ['name' => 'Renamed'])->assertOk();
    }

    public function test_changing_access_or_resetting_a_password_ends_that_persons_sessions(): void
    {
        $this->world();
        $target = User::where('email', 'finance@insania.test')->first();
        $token = $target->createToken('phone')->plainTextToken;
        $this->actingAsRole('security_admin');

        $this->patchJson("/api/users/{$target->id}", ['department_key' => 'projects'])->assertOk()->assertJsonPath('data.department', 'projects');
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $target->id)->count());

        $token2 = $target->createToken('phone')->plainTextToken;
        $this->postJson("/api/users/{$target->id}/password", ['password' => 'weak'])->assertStatus(422);
        $this->postJson("/api/users/{$target->id}/password", ['password' => self::STRONG])->assertOk();
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $target->id)->count());

        $this->app['auth']->forgetGuards();
        $this->postJson('/api/login', ['email' => 'finance@insania.test', 'password' => DemoSeeder::PASSWORD])->assertStatus(401);
        $this->postJson('/api/login', ['email' => 'finance@insania.test', 'password' => self::STRONG])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'user.password_reset', 'subject_id' => $target->id]);
    }

    public function test_the_audit_log_records_who_did_what_without_personal_data(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        $this->putJson('/api/children/'.$this->childId('ACF-0001').'/bank/verification', ['verified' => true])->assertOk();

        $this->actingAsRole('security_admin');
        $logs = $this->getJson('/api/audit?action=bank.verified')->assertOk()->json('data');

        $this->assertCount(1, $logs);
        $this->assertStringNotContainsString('Amina', json_encode($logs));
        $this->assertStringNotContainsString('1137', json_encode($logs));
    }

    /* ---------- Family account ---------- */

    public function test_a_family_account_sees_its_own_children_and_nothing_financial(): void
    {
        $this->world();
        $this->actingAsRole('user');

        $res = $this->getJson('/api/my/family')->assertOk();
        $this->assertSame(['ACF-0001'], collect($res->json('data'))->pluck('ref')->all());
        foreach (['bank', 'last4', 'monthly_amount', 'guardian_detail', 'phone', 'pay_type', 'sponsor'] as $leak) {
            $this->assertStringNotContainsString($leak, json_encode($res->json('data')));
        }
        $this->getJson('/api/children/'.$this->childId('ACF-0001'))->assertForbidden();   // not even their own child's full profile
    }

    /* ---------- Partner API key ---------- */

    private function key(array $over = []): string
    {
        $raw = 'pk_'.bin2hex(random_bytes(16));
        ApiKey::create($over + ['name' => 'Partner', 'key_prefix' => substr($raw, 0, 8), 'key_hash' => hash('sha256', $raw), 'abilities' => ['partner.summary'], 'foundation_id' => 1]);

        return $raw;
    }

    public function test_the_partner_summary_needs_a_valid_key_with_the_right_ability(): void
    {
        $this->world();

        $this->getJson('/api/partner/summary')->assertStatus(401);
        $this->getJson('/api/partner/summary', ['X-Api-Key' => 'wrong'])->assertStatus(401);
        $this->getJson('/api/partner/summary', ['X-Api-Key' => $this->key(['revoked_at' => now()])])->assertStatus(401);
        $this->getJson('/api/partner/summary', ['X-Api-Key' => $this->key(['expires_at' => now()->subDay()])])->assertStatus(401);
        $this->getJson('/api/partner/summary', ['X-Api-Key' => $this->key(['abilities' => ['something.else']])])->assertForbidden();
        $this->getJson('/api/partner/summary', ['X-Api-Key' => $this->key(['foundation_id' => null])])->assertForbidden();
    }

    public function test_the_partner_summary_gives_totals_only_for_its_own_foundation(): void
    {
        $this->world();
        $this->otherFoundation();
        $raw = $this->key();

        $res = $this->getJson('/api/partner/summary?month=2026-11', ['X-Api-Key' => $raw])->assertOk();
        $res->assertJsonPath('data.sponsored_children', 12)->assertJsonPath('data.paid_total', 1500)->assertJsonPath('data.due_total', 600);

        $text = json_encode($res->json());
        foreach (['Amina', 'ACF-', 'OTH-', 'name_en', 'guardian'] as $private) {
            $this->assertStringNotContainsString($private, $text, "partner summary leaked: $private");
        }
        $this->assertNotNull(ApiKey::first()->last_used_at);
        $this->getJson('/api/partner/summary?month=oops', ['X-Api-Key' => $raw])->assertStatus(422);
    }

    /* ---------- Dashboards ---------- */

    public function test_each_role_receives_only_its_own_parts_of_the_dashboard(): void
    {
        $this->world();
        $expect = [
            'president_admin' => ['children', 'payments', 'projects', 'security'],
            'staff:aytam' => ['children'],
            'staff:finance' => ['payments'],
            'staff:projects' => ['projects'],
            'system_admin' => ['system'],
            'admin_maintenance' => ['system'],
            'security_admin' => ['security'],
            'user' => ['family'],
        ];

        foreach ($expect as $role => $parts) {
            $this->actingAsRole($role);
            $data = $this->getJson('/api/dashboard')->assertOk()->json('data');
            $sections = array_values(array_intersect(array_keys($data), ['children', 'payments', 'projects', 'system', 'security', 'family']));
            $this->assertEqualsCanonicalizing($parts, $sections, "$role dashboard sections");
        }

        $this->actingAsRole('president_admin');
        $this->getJson('/api/dashboard')->assertJsonPath('data.children.total', 12)->assertJsonPath('data.children.bank_accounts', 8)->assertJsonPath('data.children.bank_verified', 6);
    }

    /* ---------- System ---------- */

    public function test_system_health_and_settings_hold_no_personal_data(): void
    {
        $this->world();
        $this->actingAsRole('system_admin');

        $health = $this->getJson('/api/system/health')->assertOk()->assertJsonPath('data.status', 'ok')->assertJsonPath('data.checks.database', true)->json();
        foreach (['Amina', 'ACF-', '@insania.test'] as $private) {
            $this->assertStringNotContainsString($private, json_encode($health));
        }

        $this->patchJson('/api/system/settings', ['currency' => 'AED', 'org_name' => 'Al Insania'])->assertOk()->assertJsonPath('data.currency', 'AED');
        $this->patchJson('/api/system/settings', ['currency' => 'dirhams'])->assertStatus(422);
        $this->patchJson('/api/system/settings', ['secret_key' => 'x'])->assertOk();   // unknown keys are ignored, never stored
        $this->assertDatabaseMissing('settings', ['key' => 'secret_key']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.updated']);
    }
}
