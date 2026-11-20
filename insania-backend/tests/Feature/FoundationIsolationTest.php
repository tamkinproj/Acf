<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Child;
use App\Models\Sponsor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Two foundations in one database. Nothing may cross between them, in any direction. */
class FoundationIsolationTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_a_president_sees_only_their_own_foundation(): void
    {
        $this->world();
        $o = $this->otherFoundation();
        $this->actingAsRole('president_admin');

        $refs = collect($this->getJson('/api/children?per_page=100')->assertOk()->json('data'))->pluck('ref');
        $this->assertCount(12, $refs);
        $this->assertNotContains('OTH-0001', $refs);

        $this->assertNotContains('OTH-0001', collect($this->getJson('/api/payments?month=2026-11')->json('data'))->pluck('ref'));
        $this->assertNotContains('Other Project', collect($this->getJson('/api/projects')->json('data'))->pluck('name_en'));
        $this->assertStringNotContainsString('OTH-0001', $this->get('/api/reports/payments.csv?month=2026-11')->getContent());
    }

    public function test_another_foundations_records_do_not_exist_for_you(): void
    {
        $this->world();
        $o = $this->otherFoundation();
        $c = $o['child']->id;
        $this->actingAsRole('president_admin');

        $this->getJson("/api/children/$c")->assertNotFound();
        $this->patchJson("/api/children/$c", ['city' => 'Hacked'])->assertNotFound();
        $this->putJson("/api/children/$c/bank/verification", ['verified' => true])->assertNotFound();
        $this->getJson("/api/children/$c/documents")->assertNotFound();
        $this->postJson("/api/payments/$c/mark-cash", ['month' => '2026-11'])->assertNotFound();
        $this->patchJson('/api/projects/'.$o['project']->id, ['name_en' => 'Hacked'])->assertNotFound();

        $this->assertSame('Other Child', DB::table('children')->where('id', $c)->value('name_en'));
        $this->assertSame(0, (int) DB::table('bank_accounts')->where('child_id', $c)->value('verified'));
        $this->assertSame('pending', DB::table('sponsorship_payments')->where('sponsorship_id', $o['sponsorship']->id)->value('status'));
    }

    public function test_new_records_always_belong_to_the_callers_foundation(): void
    {
        $this->world();
        $o = $this->otherFoundation();
        $me = $this->actingAsRole('president_admin');
        $foreignSponsor = Sponsor::withoutGlobalScopes()->create(['foundation_id' => $o['foundation']->id, 'name' => 'Foreign Sponsor']);

        $res = $this->postJson('/api/children', [
            'name_en' => 'Stamped Child', 'foundation_id' => $o['foundation']->id, 'channel' => 'partner', 'sponsor_id' => $foreignSponsor->id,
        ])->assertCreated();

        $this->assertSame($me->foundation_id, (int) DB::table('children')->where('id', $res->json('data.id'))->value('foundation_id'));
        $this->assertNull(DB::table('sponsorships')->where('child_id', $res->json('data.id'))->value('sponsor_id'), 'a foreign sponsor must not be attachable');
    }

    public function test_user_administration_stops_at_the_foundation_boundary(): void
    {
        $this->world();
        $o = $this->otherFoundation();
        $this->actingAsRole('security_admin');

        $emails = collect($this->getJson('/api/users')->assertOk()->json('data'))->pluck('email');
        $this->assertNotContains('other-president@x.test', $emails);

        $id = $o['president']->id;
        $this->patchJson("/api/users/$id", ['name' => 'Hacked'])->assertNotFound();
        $this->postJson("/api/users/$id/password", ['password' => 'NewStrongPass#2026'])->assertNotFound();
        $this->postJson("/api/users/$id/revoke-tokens")->assertNotFound();
    }

    public function test_the_audit_log_is_per_foundation(): void
    {
        $this->world();
        $o = $this->otherFoundation();
        AuditLog::create(['foundation_id' => $o['foundation']->id, 'user_id' => $o['president']->id, 'action' => 'child.created']);
        $this->actingAsRole('president_admin');
        $this->postJson('/api/children', ['name_en' => 'Audited'])->assertCreated();

        $this->actingAsRole('security_admin');
        $logs = $this->getJson('/api/audit?per_page=100')->assertOk()->json('data');

        $this->assertNotEmpty($logs);
        $this->assertSame([], array_values(array_filter($logs, fn ($l) => (int) $l['foundation_id'] === $o['foundation']->id)));
    }

    public function test_a_family_account_sees_only_its_own_children(): void
    {
        $this->world();
        $o = $this->otherFoundation();

        $this->actingAsEmail('other-family@x.test');
        $this->assertSame(['OTH-0001'], collect($this->getJson('/api/my/family')->json('data'))->pluck('ref')->all());

        $this->actingAsRole('user');
        $refs = collect($this->getJson('/api/my/family')->json('data'))->pluck('ref')->all();
        $this->assertSame(['ACF-0001'], $refs);
    }

    public function test_an_account_with_no_foundation_sees_nothing_at_all(): void
    {
        $this->world();
        $u = $this->actingAsRole('president_admin');
        DB::table('users')->where('id', $u->id)->update(['foundation_id' => null]);
        $this->actingAsEmail('president@insania.test');

        $this->assertSame(0, Child::count(), 'scope must fail closed');
        $this->getJson('/api/children')->assertOk()->assertJsonPath('meta.total', 0);
    }
}
