<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class ChildrenAndPaymentsTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_the_children_list_searches_filters_and_pages(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');

        $this->getJson('/api/children')->assertOk()->assertJsonPath('meta.total', 12)->assertJsonPath('data.0.ref', 'ACF-0001');
        $this->assertCount(1, $this->getJson('/api/children?q=layla')->json('data'));
        $this->assertCount(1, $this->getJson('/api/children?q=ACF-0003')->json('data'));
        $this->assertCount(1, $this->getJson('/api/children?q=Sami')->json('data'), 'search reaches the guardian name');
        $this->assertCount(4, $this->getJson('/api/children?pay_type=cash')->json('data'));
        $this->assertCount(8, $this->getJson('/api/children?pay_type=bank')->json('data'));
        $this->getJson('/api/children?per_page=5')->assertJsonPath('meta.last_page', 3);
        $this->getJson('/api/children?per_page=1000')->assertStatus(422);
        $this->assertCount(0, $this->getJson('/api/children?q='.urlencode('%'))->json('data'), 'a % in the search is text, not a wildcard');
    }

    public function test_a_profile_has_every_section_and_never_the_full_account_number(): void
    {
        $this->world();
        $this->actingAsRole('aytam_staff' === 'x' ? 'president_admin' : 'staff:aytam');

        $p = $this->getJson('/api/children/'.$this->childId('ACF-0001'))->assertOk()->json('data');

        foreach (['personal', 'family', 'guardian_detail', 'bank', 'channel', 'channel_history', 'documents'] as $section) {
            $this->assertArrayHasKey($section, $p);
        }
        $this->assertSame('1137', $p['bank']['last4']);
        $this->assertSame(['holder', 'bank_name', 'last4', 'verified'], array_keys($p['bank']));
        $this->assertSame('Dar Al Ber Society', $p['channel']['sponsor']);
        $this->assertCount(4, $p['documents']);
    }

    public function test_registering_a_child_creates_the_guardian_bank_account_and_sponsorship(): void
    {
        $this->world();
        $me = $this->actingAsRole('staff:aytam');

        $res = $this->postJson('/api/children', [
            'name_en' => 'New Child', 'name_ar' => 'طفل جديد', 'gender' => 'f', 'birth_date' => '2015-05-05', 'city' => 'Molave', 'brothers' => 1,
            'guardian' => ['name' => 'New Guardian', 'relation' => 'Mother', 'phone' => '+63 900 111 2222'],
            'pay_type' => 'bank', 'monthly_amount' => 120, 'channel' => 'direct',
            'bank' => ['holder' => 'New Guardian', 'bank_name' => 'Sample Bank A', 'last4' => '4321'],
        ])->assertCreated();

        $id = $res->json('data.id');
        $this->assertMatchesRegularExpression('/^ACF-\d{4}$/', $res->json('data.ref'));
        $this->assertSame('4321', $res->json('data.bank.last4'));
        $this->assertFalse($res->json('data.bank.verified'));
        $this->assertSame(1, DB::table('sponsorships')->where('child_id', $id)->count());
        $this->assertSame(1, DB::table('channel_histories')->where('child_id', $id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'child.created', 'user_id' => $me->id, 'subject_id' => $id]);
    }

    public function test_bad_input_is_refused_with_a_clear_error(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');

        foreach ([
            ['name_en' => ''], ['name_en' => 'X', 'gender' => 'z'], ['name_en' => 'X', 'birth_date' => now()->addDay()->toDateString()],
            ['name_en' => 'X', 'brothers' => 99], ['name_en' => 'X', 'pay_type' => 'crypto'],
            ['name_en' => 'X', 'bank' => ['holder' => 'H', 'bank_name' => 'B', 'last4' => 'abcd']], ['name_en' => 'X', 'bank' => ['holder' => 'H', 'bank_name' => 'B', 'last4' => '12345']],
        ] as $bad) {
            $this->postJson('/api/children', $bad)->assertStatus(422)->assertJsonPath('success', false);
        }
    }

    public function test_updating_a_child_cannot_change_money_fields(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $id = $this->childId('ACF-0002');

        $this->patchJson("/api/children/$id", ['city' => 'Molave', 'brothers' => 2])->assertOk()->assertJsonPath('data.personal.city', 'Molave');
        $this->patchJson("/api/children/$id", ['pay_type' => 'cash'])->assertStatus(422);
        $this->patchJson("/api/children/$id", ['monthly_amount' => 1])->assertStatus(422);
    }

    public function test_bank_verification_is_recorded_and_only_possible_with_an_account(): void
    {
        $this->world();
        $me = $this->actingAsRole('staff:aytam');
        $id = $this->childId('ACF-0001');

        $this->putJson("/api/children/$id/bank/verification", ['verified' => true])->assertOk()->assertJsonPath('data.verified', true);
        $this->assertSame($me->id, (int) DB::table('bank_accounts')->where('child_id', $id)->value('verified_by'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'bank.verified', 'subject_id' => $id]);

        $this->putJson("/api/children/$id/bank/verification", ['verified' => false])->assertOk();
        $this->assertNull(DB::table('bank_accounts')->where('child_id', $id)->value('verified_by'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'bank.unverified', 'subject_id' => $id]);

        $this->putJson('/api/children/'.$this->childId('ACF-0003').'/bank/verification', ['verified' => true])->assertStatus(422)->assertJsonPath('code', 'NO_BANK_ACCOUNT');
        $this->putJson("/api/children/$id/bank/verification", ['verified' => 'yes please'])->assertStatus(422);
    }

    public function test_the_monthly_payment_list_and_totals(): void
    {
        $this->world();
        $this->actingAsRole('staff:finance');

        $nov = $this->getJson('/api/payments?month=2026-11')->assertOk();
        $nov->assertJsonPath('meta.children', 12)->assertJsonPath('meta.paid_count', 8)->assertJsonPath('meta.pending_count', 4)
            ->assertJsonPath('meta.paid_total', 1500)->assertJsonPath('meta.due_total', 600);

        $this->getJson('/api/payments?month=2026-10')->assertJsonPath('meta.paid_count', 12)->assertJsonPath('meta.paid_total', 2100);
        $this->getJson('/api/payments?month=2026-12')->assertJsonPath('meta.paid_count', 0)->assertJsonPath('meta.pending_count', 12);
        $this->assertCount(4, $this->getJson('/api/payments?month=2026-11&scope=cash')->json('data'));
        $this->getJson('/api/payments?month=nope')->assertStatus(422);
        $this->getJson('/api/payments?month=2026-13')->assertStatus(422);
    }

    public function test_marking_cash_received_updates_the_ledger_once_and_is_audited(): void
    {
        $this->world();
        $me = $this->actingAsRole('staff:finance');
        $id = $this->childId('ACF-0006');

        $this->postJson("/api/payments/$id/mark-cash", ['month' => '2026-11'])->assertOk()->assertJsonPath('data.status', 'paid');
        $this->postJson("/api/payments/$id/mark-cash", ['month' => '2026-11'])->assertOk();   // pressing it twice changes nothing

        $this->getJson('/api/payments?month=2026-11')->assertJsonPath('meta.paid_count', 9)->assertJsonPath('meta.paid_total', 1650)->assertJsonPath('meta.due_total', 450);
        $this->assertSame(1, DB::table('sponsorship_payments')->where('month', '2026-11')->where('method', 'cash')->where('recorded_by', $me->id)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.cash_received', 'subject_id' => $id]);

        $this->postJson("/api/payments/$id/mark-cash", ['month' => '2026-12'])->assertOk();   // a month with no row yet gets one
        $this->postJson("/api/payments/$id/mark-cash", ['month' => 'soon'])->assertStatus(422);
    }

    public function test_only_cash_payments_can_be_marked_received(): void
    {
        $this->world();
        $this->actingAsRole('staff:finance');

        $this->postJson('/api/payments/'.$this->childId('ACF-0002').'/mark-cash', ['month' => '2026-11'])->assertStatus(422)->assertJsonPath('code', 'NOT_CASH');
    }

    public function test_the_project_list_and_edits(): void
    {
        $this->world();
        $this->actingAsRole('staff:projects');

        $this->assertCount(4, $this->getJson('/api/projects')->json('data'));
        $id = $this->postJson('/api/projects', ['name_en' => 'Mosque roof', 'budget' => 1000, 'spent' => 250])->assertCreated()->assertJsonPath('data.progress', 25)->json('data.id');
        $this->patchJson("/api/projects/$id", ['spent' => 1000, 'status' => 'completed'])->assertOk()->assertJsonPath('data.progress', 100);
        $this->patchJson("/api/projects/$id", ['spent' => -5])->assertStatus(422);
        $this->assertSame(2, AuditLog::whereIn('action', ['project.created', 'project.updated'])->count());
    }
}
