<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\ChildApplication;
use App\Models\Partner;
use App\Support\Partners;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Partners: Dar Al Ber works, the others are listed but not set up; the President pauses and resumes; nothing is ever deleted. */
class PartnersTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function fid(): int
    {
        return (int) DB::table('foundations')->value('id');
    }

    private function form(array $over = []): array
    {
        return $over + ['child_first_en' => 'Test', 'child_father_en' => 'Partner', 'gender' => 'm', 'birth_date' => '2018-01-01', 'resp_first_en' => 'Guardian', 'responsible_relation' => 'Mother', 'guardian_phone' => '09171234567'];
    }

    public function test_four_partners_and_every_record_belongs_to_dar_al_ber(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        // staff see only the partner that is working
        $this->assertSame(['dar_al_ber'], array_column($this->getJson('/api/partners')->assertOk()->json('data'), 'code'));
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');   // the President sees them all
        $list = $this->getJson('/api/partners')->assertOk()->json('data');
        $this->assertSame(['dar_al_ber', 'dubai_charity', 'sharjah_charity', 'ico'], array_column($list, 'code'));
        $this->assertSame(['active', 'not_setup', 'not_setup', 'not_setup'], array_column($list, 'status'));
        $this->assertSame('Dar Al Ber Society', $list[0]['name_en']);
        $this->assertSame('International Charity Organization (ICO)', $list[3]['name_en']);
        $dar = Partners::defaultId($this->fid());
        $this->assertSame(0, Child::withoutGlobalScopes()->whereNull('partner_id')->count(), 'no orphan is left without a partner');
        $this->assertSame(Child::withoutGlobalScopes()->count(), Child::withoutGlobalScopes()->where('partner_id', $dar)->count());
        // a new orphan is stamped with the partner the app works in
        $id = $this->postJson('/api/children', ['child_first_en' => 'New', 'child_father_en' => 'Orphan'], ['X-Partner' => 'dar_al_ber'])->assertCreated()->json('data.id');
        $this->assertSame($dar, (int) Child::withoutGlobalScopes()->find($id)->partner_id);
    }

    public function test_only_the_president_pauses_and_resumes_with_the_password_and_nothing_can_be_deleted(): void
    {
        $this->world();
        foreach (['staff:aytam', 'security_admin', 'system_admin'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->postJson('/api/partners/dar_al_ber/pause', ['admin_password' => 'DemoPass#2026'])->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $this->postJson('/api/partners/dar_al_ber/pause', ['admin_password' => 'wrong'])->assertStatus(422)->assertJsonPath('code', 'WRONG_PASSWORD');
        $this->postJson('/api/partners/ico/resume', ['admin_password' => 'DemoPass#2026'])->assertStatus(422)->assertJsonPath('code', 'PARTNER_NOT_SETUP');
        $this->postJson('/api/partners/dar_al_ber/pause', ['admin_password' => 'DemoPass#2026'])->assertOk()->assertJsonPath('data.0.status', 'paused');
        $this->postJson('/api/partners/dar_al_ber/pause', ['admin_password' => 'DemoPass#2026'])->assertStatus(422)->assertJsonPath('code', 'NO_CHANGE');
        $this->assertDatabaseHas('audit_logs', ['action' => 'partner.paused']);
        // there is no way to delete or unlink a partner
        $this->assertContains($this->deleteJson('/api/partners/dar_al_ber')->status(), [404, 405]);
        $this->assertContains($this->deleteJson('/api/partners/dar_al_ber/link')->status(), [404, 405]);
        $this->assertSame(4, Partner::withoutGlobalScopes()->count());
        $this->postJson('/api/partners/dar_al_ber/resume', ['admin_password' => 'DemoPass#2026'])->assertOk()->assertJsonPath('data.0.status', 'active');
        $this->assertDatabaseHas('audit_logs', ['action' => 'partner.resumed']);
    }

    public function test_a_paused_partner_blocks_staff_and_the_president_can_only_look(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $child = Child::withoutGlobalScopes()->first();
        $this->postJson('/api/partners/dar_al_ber/pause', ['admin_password' => 'DemoPass#2026'])->assertOk();

        $this->app['auth']->forgetGuards();
        $this->actingAsRole('staff:aytam');
        $this->getJson('/api/children')->assertStatus(423)->assertJsonPath('code', 'PARTNER_PAUSED');
        $this->getJson('/api/applications')->assertStatus(423);
        $this->getJson('/api/edu/teachers')->assertStatus(423);
        $this->postJson('/api/children', ['child_first_en' => 'Blocked'])->assertStatus(423);
        $this->getJson('/api/dashboard')->assertOk();   // shared work (dashboard, attendance) goes on

        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $this->getJson('/api/children')->assertOk()->assertHeader('X-Partner-Paused', '1');
        $this->getJson('/api/children/'.$child->id)->assertOk();
        $this->patchJson('/api/children/'.$child->id, ['city' => 'Changed'])->assertStatus(423);
        $this->assertNotSame('Changed', $child->fresh()->city, 'the record stays as it was');
    }

    public function test_a_partner_that_is_not_set_up_cannot_be_used_and_records_of_another_partner_are_not_found(): void
    {
        $this->world();
        $this->actingAsRole('president_admin');
        $this->getJson('/api/children', ['X-Partner' => 'ico'])->assertStatus(423)->assertJsonPath('code', 'PARTNER_NOT_SETUP');
        $this->getJson('/api/children', ['X-Partner' => 'nobody'])->assertNotFound();
        // give one orphan to another partner: Dar Al Ber's lists and links no longer find it
        $other = Partners::byCode($this->fid(), 'dubai_charity');
        $other->update(['status' => 'active']);
        $child = Child::withoutGlobalScopes()->first();
        $child->forceFill(['partner_id' => $other->id])->save();
        $ids = array_column($this->getJson('/api/children?per_page=100', ['X-Partner' => 'dar_al_ber'])->assertOk()->json('data'), 'id');
        $this->assertNotContains($child->id, $ids);
        $this->getJson('/api/children/'.$child->id, ['X-Partner' => 'dar_al_ber'])->assertNotFound();
        $this->getJson('/api/children/'.$child->id, ['X-Partner' => 'dubai_charity'])->assertOk();
    }

    public function test_each_partner_has_its_own_application_link(): void
    {
        $this->world();
        config(['orphan_fields.apply.per_hour' => 1000]);
        $this->getJson('/api/public/apply')->assertOk()->assertJsonPath('data.open', true)->assertJsonPath('data.partner.code', 'dar_al_ber');
        $this->getJson('/api/public/apply?partner=dar_al_ber')->assertOk()->assertJsonPath('data.open', true)->assertJsonPath('data.partner.name_en', 'Dar Al Ber Society');
        $this->getJson('/api/public/apply?partner=sharjah_charity')->assertOk()->assertJsonPath('data.open', false)->assertJsonPath('data.reason', 'not_setup');
        $this->getJson('/api/public/apply?partner=nobody')->assertOk()->assertJsonPath('data.open', false)->assertJsonPath('data.reason', 'unknown');
        $this->postJson('/api/public/apply', $this->form(['partner' => 'ico']))->assertNotFound()->assertJsonPath('code', 'NOT_OPEN');
        $this->postJson('/api/public/apply', $this->form(['partner' => 'dar_al_ber']))->assertCreated();
        $this->postJson('/api/public/apply', $this->form())->assertCreated();   // an old link without a partner is Dar Al Ber's
        $dar = Partners::defaultId($this->fid());
        $this->assertSame(2, ChildApplication::withoutGlobalScopes()->where('partner_id', $dar)->count());

        // a paused partner's link is closed for now
        $this->actingAsRole('president_admin');
        $this->postJson('/api/partners/dar_al_ber/pause', ['admin_password' => 'DemoPass#2026'])->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/public/apply?partner=dar_al_ber')->assertJsonPath('data.open', false)->assertJsonPath('data.reason', 'paused');
        $this->postJson('/api/public/apply', $this->form(['partner' => 'dar_al_ber']))->assertStatus(503)->assertJsonPath('code', 'CLOSED');
    }

    public function test_an_approved_application_becomes_an_orphan_of_its_partner(): void
    {
        $this->world();
        config(['orphan_fields.apply.per_hour' => 1000]);
        $this->postJson('/api/public/apply', $this->form(['partner' => 'dar_al_ber']))->assertCreated();
        $app = ChildApplication::withoutGlobalScopes()->latest('id')->first();
        $this->actingAsRole('staff:aytam');
        $id = $this->postJson('/api/applications/'.$app->id.'/approve')->assertOk()->json('data.child.id');
        $this->assertSame((int) $app->partner_id, (int) Child::withoutGlobalScopes()->find($id)->partner_id);
    }
}
