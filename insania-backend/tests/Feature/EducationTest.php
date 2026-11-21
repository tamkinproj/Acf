<?php

namespace Tests\Feature;

use App\Models\Education\Teacher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class EducationTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_only_the_education_department_and_the_president_reach_education(): void
    {
        $this->world();
        foreach (['staff:aytam', 'staff:finance', 'staff:projects', 'user', 'system_admin', 'security_admin'] as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/edu/teachers')->assertForbidden();
            $this->postJson('/api/edu/teachers', ['name_en' => 'X'])->assertForbidden();
            $this->getJson('/api/edu/salaries')->assertForbidden();
        }
        foreach (['staff:education', 'president_admin'] as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/edu/teachers')->assertOk();
            $this->getJson('/api/edu/summary')->assertOk();
        }
        $this->actingAsRole('staff:education');
        $me = $this->getJson('/api/me')->json('data');
        $this->assertSame('education', $me['department']);
        $this->assertContains('education.salary', $me['permissions']);
    }

    public function test_a_teacher_gets_a_six_month_contract_that_can_be_renewed(): void
    {
        $this->world();
        $this->actingAsRole('staff:education');
        $madrasa = $this->postJson('/api/edu/institutions', ['kind' => 'madrasa', 'partnership' => 'own', 'name_en' => 'Al Insania Madrasa', 'city' => 'Pagadian'])->assertCreated()->json('data.id');
        $t = $this->postJson('/api/edu/teachers', ['name_en' => 'Ustadh Amin Salik', 'name_ar' => 'الأستاذ أمين سالك', 'institution_id' => $madrasa, 'start_date' => '2026-01-01', 'monthly_salary' => 9000])->assertCreated();
        $id = $t->json('data.id');
        $this->assertMatchesRegularExpression('/^ALM-\d{4}$/', $t->json('data.ref'));
        $this->assertSame('2026-01-01', $t->json('data.contract.start_date'));
        $this->assertSame('2026-06-30', $t->json('data.contract.end_date'), 'six months: 1 Jan to 30 Jun');
        $this->assertEquals(9000, $t->json('data.contract.monthly_salary'));

        // renew: follows the old contract, keeps the salary unless changed
        $this->travelTo(now()->setDate(2026, 6, 20));
        $this->assertSame('ending_soon', $this->getJson("/api/edu/teachers/$id")->json('data.contract_state'));
        $r = $this->postJson("/api/edu/teachers/$id/contracts", [])->assertCreated();
        $this->assertSame('2026-07-01', $r->json('data.contract.start_date'));
        $this->assertSame('2026-12-31', $r->json('data.contract.end_date'));
        $this->assertEquals(9000, $r->json('data.contract.monthly_salary'));
        $this->assertCount(2, $r->json('data.contracts'));
        $this->assertSame('active', $r->json('data.contract_state'));
        $r = $this->postJson("/api/edu/teachers/$id/contracts", ['months' => 3, 'monthly_salary' => 10000, 'start_date' => '2027-01-01'])->assertCreated();
        $this->assertSame('2027-03-31', $r->json('data.contract.end_date'));
        $this->assertEquals(10000, $r->json('data.contract.monthly_salary'));
        $this->travelBack();

        $this->travelTo(now()->setDate(2028, 1, 5));
        $this->assertSame('expired', $this->getJson("/api/edu/teachers/$id")->json('data.contract_state'));
        $sum = $this->getJson('/api/edu/summary')->assertOk()->json('data');
        $this->assertSame(1, $sum['contracts']['expired']);
        $this->assertSame($id, $sum['attention'][0]['id']);
        $this->travelBack();
    }

    public function test_salaries_are_made_for_the_month_and_marked_paid(): void
    {
        $this->world();
        $this->actingAsRole('staff:education');
        $a = $this->postJson('/api/edu/teachers', ['name_en' => 'Teacher A', 'start_date' => '2026-03-01', 'monthly_salary' => 8000])->json('data.id');
        $b = $this->postJson('/api/edu/teachers', ['name_en' => 'Teacher B', 'start_date' => '2026-09-01', 'monthly_salary' => 7000])->json('data.id');
        $this->postJson('/api/edu/teachers', ['name_en' => 'No contract yet'])->assertCreated();

        $g = $this->postJson('/api/edu/salaries/generate', ['month' => '2026-04'])->assertOk();
        $this->assertSame(1, $g->json('data.created'), 'only Teacher A has a contract covering April');
        $this->postJson('/api/edu/salaries/generate', ['month' => '2026-04'])->assertOk()->assertJsonPath('data.created', 0);
        $list = $this->getJson('/api/edu/salaries?month=2026-04')->assertOk();
        $this->assertSame(8000.0, (float) $list->json('data.totals.due_total'));

        $this->putJson("/api/edu/teachers/$a/salaries/2026-04", ['status' => 'paid', 'method' => 'bank'])->assertOk()->assertJsonPath('data.status', 'paid');
        $list = $this->getJson('/api/edu/salaries?month=2026-04')->json('data');
        $this->assertSame(1, $list['totals']['paid_count']);
        $this->assertSame(8000.0, (float) $list['totals']['paid_total']);
        $this->assertSame(0.0, (float) $list['totals']['due_total']);
        $this->putJson("/api/edu/teachers/$a/salaries/2026-04", ['status' => 'pending'])->assertOk();
        $this->putJson("/api/edu/teachers/$b/salaries/2026-10", ['status' => 'paid', 'amount' => 7500])->assertOk()->assertJsonPath('data.amount', 7500);   // a month can be recorded without generating first
        $this->putJson("/api/edu/teachers/$a/salaries/April", ['status' => 'paid'])->assertStatus(422);
        $this->assertCount(1, $this->getJson("/api/edu/teachers/$b")->json('data.salaries'));
    }

    public function test_salary_amounts_are_only_for_people_who_hold_the_salary_permission(): void
    {
        $this->world();
        config(['roles.departments.education.grants' => ['education.view', 'education.manage', 'chat.use']]);
        $this->actingAsRole('staff:education');
        $id = $this->postJson('/api/edu/teachers', ['name_en' => 'Teacher C', 'start_date' => '2026-01-01', 'monthly_salary' => 5000])->json('data.id');
        $d = $this->getJson("/api/edu/teachers/$id")->assertOk()->json('data');
        $this->assertArrayNotHasKey('monthly_salary', $d['contract'], 'no salary amount for them');
        $this->assertArrayNotHasKey('salaries', $d);
        $this->assertFalse($d['can_salary']);
        $this->getJson('/api/edu/salaries')->assertForbidden();
        $this->postJson("/api/edu/teachers/$id/contracts", ['monthly_salary' => 99999])->assertCreated();
        $this->actingAsRole('president_admin');
        $this->assertEquals(0, $this->getJson("/api/edu/teachers/$id")->json('data.contract.monthly_salary'), 'a renewal without the salary permission carries the old amount... which was 0 here');
    }

    public function test_madrasa_and_markaz_partners_and_the_curriculum(): void
    {
        $this->world();
        $this->actingAsRole('staff:education');
        $this->postJson('/api/edu/institutions', ['kind' => 'castle', 'name_en' => 'X'])->assertStatus(422);
        $m = $this->postJson('/api/edu/institutions', ['kind' => 'madrasa', 'partnership' => 'own', 'name_en' => 'Madrasa One'])->assertCreated()->json('data.id');
        $k1 = $this->postJson('/api/edu/institutions', ['kind' => 'markaz', 'partnership' => 'partner', 'name_en' => 'Markaz Noor', 'contact_name' => 'Ustadh Yusuf', 'phone' => '0917'])->assertCreated()->json('data.id');
        $this->postJson('/api/edu/institutions', ['kind' => 'markaz', 'partnership' => 'partner', 'name_en' => 'Markaz Huda'])->assertCreated();
        $list = $this->getJson('/api/edu/institutions')->json('data');
        $this->assertSame(1, collect($list)->where('kind', 'madrasa')->count());
        $this->assertSame(2, collect($list)->where('kind', 'markaz')->count());
        $this->patchJson("/api/edu/institutions/$k1", ['phone' => '0918', 'status' => 'ended'])->assertOk()->assertJsonPath('data.status', 'ended');

        $c = $this->postJson('/api/edu/curricula', ['title_en' => 'Tahfiz Level 1', 'units' => ['Surah Al-Fatiha', 'Surah An-Nas', 'Daily duas']])->assertCreated();
        $this->assertSame('Dar Al Ber', $c->json('data.source'));
        $this->assertCount(3, $c->json('data.units'));
        $cid = $c->json('data.id');
        $u = $this->postJson("/api/edu/curricula/$cid/units", ['title_en' => 'Wudu and salah'])->assertCreated()->json('data.units');
        $this->assertCount(4, $u);

        $t = $this->postJson('/api/edu/teachers', ['name_en' => 'Teacher D', 'institution_id' => $m, 'curriculum_id' => $cid])->json('data.id');
        $unit = $u[0]['id'];
        $this->putJson("/api/edu/teachers/$t/progress/$unit", ['done' => true])->assertOk();
        $this->assertTrue(collect($this->getJson("/api/edu/teachers/$t")->json('data.curriculum.units'))->firstWhere('id', $unit)['done']);
        $this->putJson("/api/edu/teachers/$t/progress/$unit", ['done' => false])->assertOk();
        $this->assertFalse(collect($this->getJson("/api/edu/teachers/$t")->json('data.curriculum.units'))->firstWhere('id', $unit)['done']);
        $this->assertSame(1, $this->getJson("/api/edu/institutions/$m")->json('data.teachers_count'));
        $this->postJson('/api/edu/teachers', ['name_en' => 'Bad link', 'institution_id' => 99999])->assertStatus(422);
        $this->deleteJson("/api/edu/units/$unit")->assertOk();
        $this->assertCount(3, $this->getJson("/api/edu/curricula/$cid")->json('data.units'));
    }

    public function test_education_records_stay_inside_their_own_foundation(): void
    {
        $this->world();
        $this->actingAsRole('staff:education');
        $id = $this->postJson('/api/edu/teachers', ['name_en' => 'Mine'])->json('data.id');
        $other = $this->otherFoundation();
        $this->assertNotEmpty($other);
        $this->actingAsRole('staff:education');
        $foreign = Teacher::withoutGlobalScopes()->create(['foundation_id' => \App\Models\Foundation::where('slug', 'other')->value('id'), 'ref' => 'ALM-9999', 'name_en' => 'Foreign']);
        $this->getJson('/api/edu/teachers/'.$foreign->id)->assertNotFound();
        $this->assertCount(1, $this->getJson('/api/edu/teachers')->json('data'));
        $this->assertNotNull($id);
    }
}
