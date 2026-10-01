<?php

namespace Tests\Feature;

use App\Models\Aytam;
use App\Models\AuditLog;
use App\Models\Program;
use App\Models\Role;
use Tests\Concerns\BootsAytam;
use Tests\TestCase;

class AytamTest extends TestCase
{
    use BootsAytam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAytam();
    }

    public function test_each_aytam_gets_a_permanent_sequential_id_per_program(): void
    {
        $first = $this->actingAs($this->mushrif)->postJson($this->base().'/aytam', $this->aytamPayload())->assertCreated();
        $second = $this->postJson($this->base().'/aytam', $this->aytamPayload(['first_name' => 'Bilal']))->assertCreated();
        $this->assertSame('AYT-000001', $first->json('data.aytam_code'));
        $this->assertSame('AYT-000002', $second->json('data.aytam_code'));
        $this->assertSame('draft', $first->json('data.status'));

        // The id never changes and is not reused, even if the record is archived.
        $this->patchJson($this->base().'/aytam/'.$first->json('data.id'), ['first_name' => 'Ahmed'])->assertOk()->assertJsonPath('data.aytam_code', 'AYT-000001');
        $this->assertSame('AYT-000003', $this->postJson($this->base().'/aytam', $this->aytamPayload(['first_name' => 'Carim']))->json('data.aytam_code'));

        // A second Aytam program numbers independently, with its own prefix.
        $other = Program::find($this->actingAs($this->admin)->postJson('/api/programs', ['name' => 'Aytam North', 'category' => 'aytam'])->json('data.id'));
        $this->postJson('/api/programs/'.$other->id.'/status', ['status' => 'active']);
        $this->patchJson('/api/programs/'.$other->id, ['config' => ['code_prefix' => 'NRT']])->assertOk();
        $this->join($this->mushrif, 'aytam_mushrif', $other);
        $this->assertSame('NRT-000001', $this->actingAs($this->mushrif)->postJson($this->base($other).'/aytam', $this->aytamPayload())->assertCreated()->json('data.aytam_code'));
    }

    public function test_record_validation_and_the_master_fields(): void
    {
        $this->actingAs($this->mushrif);
        $this->postJson($this->base().'/aytam', [])->assertStatus(422)->assertJsonValidationErrors(['first_name', 'last_name']);
        $this->postJson($this->base().'/aytam', $this->aytamPayload(['date_of_birth' => '2999-01-01']))->assertStatus(422);
        $this->postJson($this->base().'/aytam', $this->aytamPayload(['gender' => 'robot']))->assertStatus(422);

        $id = $this->postJson($this->base().'/aytam', $this->aytamPayload([
            'middle_name' => 'bin', 'arabic_name' => 'أحمد عبدالله', 'nationality' => 'Filipino', 'country' => 'Philippines', 'region' => 'BARMM', 'province' => 'Maguindanao',
            'city' => 'Cotabato City', 'barangay' => 'Rosary Heights', 'address_detail' => 'Block 4', 'education_level' => 'Elementary', 'school' => 'Al-Noor School', 'grade' => '5',
            'phone' => '0917 000 0000', 'email' => 'guardian@example.test',
        ]))->assertCreated()->json('data.id');
        $r = $this->getJson($this->base()."/aytam/{$id}")->assertOk();
        $this->assertSame('Ahmad bin Abdullah', $r->json('data.name'));
        $this->assertSame('أحمد عبدالله', $r->json('data.fields.arabic_name'));
        $this->assertSame('2012-04-17', $r->json('data.fields.date_of_birth'));
        $this->assertSame('Al-Noor School', $r->json('data.fields.school'));
    }

    public function test_status_workflow_and_who_may_move_a_record(): void
    {
        $id = $this->createAytam(['first_name' => 'Zaid']);
        $status = fn ($user, string $to, ?string $note = null) => $this->actingAs($user)->postJson($this->base()."/aytam/{$id}/status", ['status' => $to] + ($note ? ['note' => $note] : []));

        // draft -> submitted (anyone who can edit) -> decided (reviewers only)
        $this->actingAs($this->mushrif)->putJson($this->base()."/aytam/{$id}/assignments", ['user_ids' => [$this->worker->id]])->assertOk();
        $status($this->worker, 'pending_review')->assertOk()->assertJsonPath('data.status', 'pending_review');
        $status($this->worker, 'approved')->assertStatus(403);
        $status($this->mushrif, 'needs_correction')->assertStatus(422)->assertJsonPath('code', 'REASON_REQUIRED');
        $status($this->mushrif, 'needs_correction', 'Birth certificate unreadable')->assertOk()->assertJsonPath('data.status_note', 'Birth certificate unreadable');
        $status($this->worker, 'pending_review')->assertOk();
        $status($this->mushrif, 'approved')->assertOk()->assertJsonPath('data.status', 'approved');
        $this->assertNotNull(Aytam::find($id)->approved_at);
        $this->assertSame($this->mushrif->id, Aytam::find($id)->approved_by);

        $status($this->mushrif, 'draft')->assertStatus(422)->assertJsonPath('code', 'INVALID_TRANSITION');
        $status($this->mushrif, 'active')->assertOk();
        $status($this->worker, 'inactive')->assertStatus(403);
        $status($this->mushrif, 'inactive')->assertOk();
        $status($this->mushrif, 'archived')->assertOk();
        $this->patchJson($this->base()."/aytam/{$id}", ['phone' => '1'])->assertStatus(409)->assertJsonPath('code', 'ARCHIVED');
        $status($this->mushrif, 'inactive')->assertOk();

        $actions = AuditLog::where('subject_id', $id)->pluck('action')->all();
        foreach (['aytam.created', 'aytam.submitted', 'aytam.returned', 'aytam.approved', 'aytam.assigned'] as $a) {
            $this->assertContains($a, $actions, $a);
        }
        $allowed = $this->actingAs($this->mushrif)->getJson($this->base()."/aytam/{$id}")->json('data.can.transitions');
        $this->assertEqualsCanonicalizing(['active', 'archived'], $allowed);
    }

    public function test_only_reviewers_can_create_live_records(): void
    {
        $this->actingAs($this->mushrif)->postJson($this->base().'/aytam', $this->aytamPayload(['status' => 'active']))->assertCreated()->assertJsonPath('data.status', 'active');
        $this->assertNotNull(Aytam::first()->approved_at);
        $this->actingAs($this->worker)->postJson($this->base().'/aytam', $this->aytamPayload())->assertStatus(403);
    }

    public function test_field_workers_see_only_assigned_records_and_edit_only_permitted_fields(): void
    {
        $mine = $this->createAytam(['first_name' => 'Assigned']);
        $other = $this->createAytam(['first_name' => 'Unassigned']);
        $this->actingAs($this->mushrif)->putJson($this->base()."/aytam/{$mine}/assignments", ['user_ids' => [$this->worker->id]])->assertOk();

        $list = $this->actingAs($this->worker)->getJson($this->base().'/aytam')->assertOk();
        $this->assertSame(['Assigned Abdullah'], array_column($list->json('data'), 'name'));
        $this->assertFalse($list->json('meta.sees_all'));
        $this->getJson($this->base()."/aytam/{$other}")->assertStatus(404);
        $this->patchJson($this->base()."/aytam/{$other}", ['phone' => '1'])->assertStatus(404);
        $this->getJson($this->base()."/aytam/{$other}/documents")->assertStatus(404);

        // Permitted: contact, address, education. Not permitted: identity, family, guardian.
        $this->patchJson($this->base()."/aytam/{$mine}", ['school' => 'New School', 'phone' => '0999'])->assertOk()->assertJsonPath('data.fields.school', 'New School');
        $this->patchJson($this->base()."/aytam/{$mine}", ['first_name' => 'Renamed'])->assertStatus(403)->assertJsonPath('code', 'FIELDS_NOT_ALLOWED');
        $this->patchJson($this->base()."/aytam/{$mine}", ['date_of_birth' => '2010-01-01'])->assertStatus(403);
        $this->assertSame('Assigned', Aytam::find($mine)->first_name);

        // No whole-program data for the worker.
        $this->getJson($this->base().'/families')->assertStatus(403);
        $this->getJson($this->base().'/guardians')->assertStatus(403);
        $this->getJson($this->base().'/aytam/assignable')->assertStatus(403);
        $this->patchJson($this->base()."/aytam/{$mine}/status", [])->assertStatus(405);

        // Unassigning removes access immediately; the Mushrif still sees both.
        $this->actingAs($this->mushrif)->putJson($this->base()."/aytam/{$mine}/assignments", ['user_ids' => []])->assertOk();
        $this->actingAs($this->worker)->getJson($this->base()."/aytam/{$mine}")->assertStatus(404);
        $this->actingAs($this->mushrif)->getJson($this->base().'/aytam')->assertJsonCount(2, 'data');
    }

    public function test_assignment_rules(): void
    {
        $id = $this->createAytam();
        $this->actingAs($this->mushrif);
        $this->getJson($this->base().'/aytam/assignable')->assertOk()->assertJsonCount(2, 'data');

        // Only active members of THIS program's team can be assigned.
        $this->putJson($this->base()."/aytam/{$id}/assignments", ['user_ids' => [$this->outsider->id]])->assertStatus(422);
        $this->putJson($this->base()."/aytam/{$id}/assignments", ['user_ids' => [$this->worker->id, $this->worker->id]])->assertStatus(422);
        $this->actingAs($this->worker)->putJson($this->base()."/aytam/{$id}/assignments", ['user_ids' => [$this->worker->id]])->assertStatus(403);

        $this->actingAs($this->mushrif)->putJson($this->base()."/aytam/{$id}/assignments", ['user_ids' => [$this->worker->id]])->assertOk()->assertJsonPath('data.0.name', $this->worker->name);
        $this->worker->update(['status' => 'disabled']);
        $this->putJson($this->base()."/aytam/{$id}/assignments", ['user_ids' => [$this->worker->id]])->assertStatus(422);
    }

    public function test_families_and_guardians_are_reusable_and_not_duplicated(): void
    {
        $this->actingAs($this->mushrif);
        $guardian = $this->postJson($this->base().'/guardians', ['full_name' => 'Aunt Salma', 'relationship' => 'aunt', 'phone' => '0917 111 1111'])->assertCreated()->json('data.id');
        $family = $this->postJson($this->base().'/families', ['name' => 'Abdullah family', 'father_name' => 'Abdullah', 'father_status' => 'deceased', 'mother_status' => 'living', 'guardian_id' => $guardian, 'city' => 'Cotabato City'])
            ->assertCreated()->json('data.id');

        $a = $this->createAytam(['first_name' => 'Ahmad', 'family_id' => $family]);
        $b = $this->createAytam(['first_name' => 'Bilal', 'family_id' => $family, 'date_of_birth' => '2014-02-02']);
        $c = $this->createAytam(['first_name' => 'Chaim', 'family_id' => $family, 'guardian_id' => $this->postJson($this->base().'/guardians', ['full_name' => 'Uncle Yusuf'])->json('data.id')]);

        $detail = $this->getJson($this->base()."/aytam/{$a}")->json('data');
        $this->assertSame('Abdullah family', $detail['family']['name']);
        $this->assertSame('Aunt Salma', $detail['guardian']['full_name']);
        $this->assertTrue($detail['guardian']['inherited'], 'the family guardian applies unless a child has their own');
        $this->assertEqualsCanonicalizing(['Bilal Abdullah', 'Chaim Abdullah'], array_column($detail['siblings'], 'name'));
        $this->assertSame('Uncle Yusuf', $this->getJson($this->base()."/aytam/{$c}")->json('data.guardian.full_name'));
        $this->assertFalse($this->getJson($this->base()."/aytam/{$c}")->json('data.guardian.inherited'));

        $f = $this->getJson($this->base()."/families/{$family}")->assertOk()->json('data');
        $this->assertCount(3, $f['members']);
        $this->assertSame(3, $this->getJson($this->base().'/families')->json('data.0.members_count'));
        $this->assertSame(['Ahmad Abdullah', 'Bilal Abdullah', 'Chaim Abdullah'], array_column($this->getJson($this->base()."/guardians/{$guardian}")->json('data.children'), 'name'));

        // In-use records cannot be removed from under the children.
        $this->deleteJson($this->base()."/families/{$family}")->assertStatus(409);
        $this->deleteJson($this->base()."/guardians/{$guardian}")->assertStatus(409);
        $this->patchJson($this->base()."/families/{$family}", ['mother_name' => 'Maryam'])->assertOk()->assertJsonPath('data.mother_name', 'Maryam');
        foreach ([$a, $b, $c] as $id) {
            $this->patchJson($this->base()."/aytam/{$id}", ['family_id' => null]);
        }
        $this->deleteJson($this->base()."/families/{$family}")->assertOk();

        $this->postJson($this->base().'/families', ['name' => 'X', 'father_status' => 'unknown?'])->assertStatus(422);
    }

    public function test_the_dashboard_counts_by_status(): void
    {
        $a = $this->createAytam(['first_name' => 'One']);
        $this->createAytam(['first_name' => 'Two']);
        $this->createAytam(['first_name' => 'Three', 'status' => 'active']);
        $this->postJson($this->base()."/aytam/{$a}/status", ['status' => 'pending_review'])->assertOk();

        $d = $this->getJson($this->base().'/aytam-dashboard')->assertOk()->json('data');
        $this->assertSame([3, 1, 1, 1], [$d['total'], $d['active'], $d['pending_review'], $d['draft']]);
        $this->assertSame(3, $d['missing_documents'], 'photo and birth certificate are required by default');
        $this->assertNotEmpty($d['recent_activity']);

        $this->actingAs($this->worker)->getJson($this->base().'/aytam-dashboard')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_search_filter_and_sort(): void
    {
        $this->createAytam(['first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'arabic_name' => 'أحمد']);
        $this->createAytam(['first_name' => 'Fatima', 'last_name' => 'Yusuf', 'gender' => 'female', 'status' => 'active']);
        $this->actingAs($this->mushrif);

        $names = fn (string $qs) => array_column($this->getJson($this->base().'/aytam?'.$qs)->json('data'), 'name');
        $this->assertSame(['Ahmad Abdullah'], $names('q=ahmad'));
        $this->assertSame(['Ahmad Abdullah'], $names('q=Abdullah+Ahmad'), 'word order does not matter');
        $this->assertSame(['Ahmad Abdullah'], $names('q='.urlencode('أحمد')));
        $this->assertSame(['Fatima Yusuf'], $names('q=AYT-000002'));
        $this->assertSame(['Fatima Yusuf'], $names('gender=female'));
        $this->assertSame(['Fatima Yusuf'], $names('status=active'));
        $this->assertSame(['Fatima Yusuf', 'Ahmad Abdullah'], $names('sort=name&dir=desc'));
        $this->getJson($this->base().'/aytam?sort=password')->assertStatus(422);
    }

    public function test_records_are_confined_to_their_program_and_foundation(): void
    {
        $id = $this->createAytam();
        $relief = Program::find($this->actingAs($this->admin)->postJson('/api/programs', ['name' => 'Relief', 'category' => 'relief'])->json('data.id'));

        // Not an Aytam program: the module routes do not exist for it.
        $this->getJson($this->base($relief).'/aytam')->assertStatus(404);

        // A second Aytam program cannot read the first program's records.
        $other = Program::find($this->postJson('/api/programs', ['name' => 'Aytam North', 'category' => 'aytam'])->json('data.id'));
        $this->postJson('/api/programs/'.$other->id.'/status', ['status' => 'active']);
        $this->actingAs($this->admin)->getJson($this->base($other)."/aytam/{$id}")->assertStatus(404);

        // Another foundation cannot reach it at all.
        [, $foreign] = $this->createFoundation('Other', 'other@example.test');
        $this->actingAs($foreign)->getJson($this->base()."/aytam/{$id}")->assertStatus(404);
        $this->getJson($this->base().'/aytam')->assertStatus(404);
        $this->postJson($this->base().'/aytam', $this->aytamPayload())->assertStatus(404);
        $this->assertSame(1, Aytam::withoutGlobalScopes()->count());

        // A family from another program cannot be linked.
        $family = $this->actingAs($this->admin)->postJson($this->base($other).'/families', ['name' => 'Other family'])->assertCreated()->json('data.id');
        $this->actingAs($this->mushrif)->postJson($this->base().'/aytam', $this->aytamPayload(['family_id' => $family]))->assertStatus(422)->assertJsonValidationErrors('family_id');
    }

    public function test_changes_need_an_active_program_and_archived_programs_are_read_only(): void
    {
        $id = $this->createAytam();
        $this->actingAs($this->admin)->postJson('/api/programs/'.$this->program->id.'/status', ['status' => 'inactive'])->assertOk();

        $this->actingAs($this->mushrif);
        $this->getJson($this->base()."/aytam/{$id}")->assertOk();                                   // reading stays possible
        $this->postJson($this->base().'/aytam', $this->aytamPayload())->assertStatus(409)->assertJsonPath('code', 'PROGRAM_NOT_ACTIVE');
        $this->patchJson($this->base()."/aytam/{$id}", ['phone' => '1'])->assertStatus(409);

        $this->actingAs($this->admin)->postJson('/api/programs/'.$this->program->id.'/status', ['status' => 'archived'])->assertOk();
        $this->actingAs($this->mushrif)->postJson($this->base().'/aytam', $this->aytamPayload())->assertStatus(409)->assertJsonPath('code', 'PROGRAM_ARCHIVED');
    }

    public function test_pii_stays_out_of_the_activity_log_values(): void
    {
        $id = $this->createAytam(['phone' => '0917 555 0000', 'email' => 'secret@example.test', 'address_detail' => 'Private lane 7']);
        $log = json_encode(AuditLog::where('subject_id', $id)->get(['old_values', 'new_values', 'summary'])->toArray());
        foreach (['0917 555 0000', 'secret@example.test', 'Private lane 7'] as $private) {
            $this->assertStringNotContainsString($private, $log);
        }
        $this->assertStringContainsString('AYT-000001', $log);
    }

    public function test_custom_roles_can_grant_aytam_permissions_programwide(): void
    {
        // A foundation role that includes program permissions applies to EVERY program (no team membership needed).
        $reviewer = $this->makeUserWithPermissions(['aytam.view', 'aytam.view_all', 'aytam.review']);
        $id = $this->createAytam();
        $this->actingAs($reviewer)->getJson($this->base()."/aytam/{$id}")->assertOk();
        $this->postJson($this->base()."/aytam/{$id}/status", ['status' => 'approved'])->assertOk();
        $this->postJson($this->base().'/aytam', $this->aytamPayload())->assertStatus(403);
        $this->assertSame(0, Role::where('key', 'like', 'custom_%')->where('scope', 'program')->count());
    }
}
