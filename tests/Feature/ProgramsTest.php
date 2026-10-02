<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Program;
use App\Models\ProgramUser;
use App\Models\Role;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class ProgramsTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
    }

    private function makeProgram(string $name = 'Aytam Care', string $category = 'aytam', bool $activate = false): Program
    {
        $id = $this->actingAs($this->admin)->postJson('/api/programs', ['name' => $name, 'category' => $category])->assertCreated()->json('data.id');
        if ($activate) {
            $this->postJson("/api/programs/{$id}/status", ['status' => 'active'])->assertOk();
        }

        return Program::find($id);
    }

    public function test_signing_in_lists_programs_for_an_admin_who_is_not_a_member_of_any(): void
    {
        $this->makeProgram('Aytam Care', 'aytam', true);
        $this->makeProgram('Flood relief', 'relief', true);
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $r = $this->postJson('/api/auth/login', ['email' => 'admin@example.test', 'password' => 'Correct-horse-9'])->assertOk();
        $this->assertEqualsCanonicalizing(['Aytam Care', 'Flood relief'], array_column($r->json('data.programs'), 'name'));
        $this->assertNull($r->json('data.programs.0.role'), 'a foundation admin has no program role, and that is fine');
    }

    private function roleId(string $key): string
    {
        return Role::where('key', $key)->value('id');
    }

    public function test_a_foundation_can_run_several_kinds_of_programs_side_by_side(): void
    {
        $types = $this->actingAs($this->admin)->getJson('/api/program-types')->assertOk()->json('data');
        $byCategory = collect($types)->keyBy('category');
        $this->assertTrue($byCategory['aytam']['available']);
        $this->assertSame('aytam', $byCategory['aytam']['module']);
        $this->assertFalse($byCategory['relief']['available'], 'a category without a module is a program shell');
        foreach (['relief', 'education', 'healthcare', 'food_distribution', 'emergency_assistance', 'qurbani', 'other'] as $c) {
            $this->assertArrayHasKey($c, $byCategory);
        }

        $aytam = $this->makeProgram('Aytam Care');
        $relief = $this->makeProgram('Flood Relief', 'relief');
        $education = $this->makeProgram('Scholarships', 'education');
        $this->assertSame('aytam', $aytam->module);
        $this->assertNull($relief->module);
        $this->assertSame(['code_prefix' => 'AYT', 'required_documents' => ['photo', 'birth_certificate']], $aytam->config);
        $this->assertNull($education->config);
        $this->assertSame('draft', $aytam->status);

        $this->assertCount(3, $this->getJson('/api/programs')->json('data'));
        $this->assertSame('aytam-care-2', $this->makeProgram('Aytam Care')->slug, 'slugs stay unique');
        $this->postJson('/api/programs', ['name' => 'X', 'category' => 'not-a-category'])->assertStatus(422);
    }

    public function test_status_lifecycle_and_permissions(): void
    {
        $p = $this->makeProgram();
        $viewer = $this->makeUser('viewer');
        $staff = $this->makeUser('staff');

        $this->actingAs($staff)->postJson('/api/programs', ['name' => 'Nope', 'category' => 'other'])->assertStatus(403);
        $this->actingAs($staff)->postJson("/api/programs/{$p->id}/status", ['status' => 'active'])->assertStatus(403);
        $this->actingAs($viewer)->getJson('/api/programs')->assertOk()->assertJsonCount(1, 'data');

        $creator = $this->makeUserWithPermissions(['programs.view', 'programs.create', 'programs.update']);
        $this->actingAs($creator)->postJson('/api/programs', ['name' => 'Made by creator', 'category' => 'other'])->assertCreated();
        $this->postJson("/api/programs/{$p->id}/status", ['status' => 'active'])->assertStatus(403);   // activation is its own permission

        $this->actingAs($this->admin);
        $this->postJson("/api/programs/{$p->id}/status", ['status' => 'inactive'])->assertStatus(422)->assertJsonPath('code', 'INVALID_TRANSITION');
        $this->postJson("/api/programs/{$p->id}/status", ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->postJson("/api/programs/{$p->id}/status", ['status' => 'inactive'])->assertOk();
        $this->postJson("/api/programs/{$p->id}/status", ['status' => 'active'])->assertOk();
        $this->postJson("/api/programs/{$p->id}/status", ['status' => 'archived'])->assertOk();
        $this->patchJson("/api/programs/{$p->id}", ['name' => 'Renamed'])->assertStatus(409)->assertJsonPath('code', 'PROGRAM_ARCHIVED');

        $this->assertNotContains($p->id, array_column($this->getJson('/api/programs')->json('data'), 'id'), 'archived programs are hidden by default');
        $this->assertContains($p->id, array_column($this->getJson('/api/programs?status=archived')->json('data'), 'id'));

        $actions = \App\Models\AuditLog::where('subject_id', $p->id)->pluck('action')->all();
        foreach (['program.created', 'program.active', 'program.inactive', 'program.archived'] as $a) {
            $this->assertContains($a, $actions);
        }
    }

    public function test_program_configuration_is_validated_by_its_module(): void
    {
        $p = $this->makeProgram();
        $this->actingAs($this->admin);
        $this->patchJson("/api/programs/{$p->id}", ['config' => ['code_prefix' => 'ABC', 'required_documents' => ['photo', 'passport']]])->assertOk()
            ->assertJsonPath('data.config.code_prefix', 'ABC')->assertJsonPath('data.config.required_documents.1', 'passport');
        $this->patchJson("/api/programs/{$p->id}", ['config' => ['code_prefix' => 'lower']])->assertStatus(422);
        $this->patchJson("/api/programs/{$p->id}", ['config' => ['required_documents' => ['spaceship']]])->assertStatus(422);
        $this->patchJson("/api/programs/{$p->id}", ['config' => ['mystery' => 1]])->assertStatus(422);
        $this->patchJson("/api/programs/{$p->id}", ['start_date' => '2026-05-01', 'end_date' => '2026-01-01'])->assertStatus(422);
    }

    public function test_program_team_and_effective_permissions(): void
    {
        $p = $this->makeProgram('Aytam Care', activate: true);
        $other = $this->makeProgram('Flood Relief', 'relief', activate: true);
        $person = $this->makeUser('volunteer');   // a foundation role with NO program permissions of its own

        // Nothing yet: the person sees no programs.
        $this->actingAs($person)->getJson('/api/auth/me')->assertJsonPath('data.programs', []);
        $this->getJson("/api/programs/{$p->id}")->assertStatus(404);

        // Assigned as Mushrif: sees that program, with the Mushrif's permissions - and only there.
        $this->actingAs($this->admin)->putJson("/api/programs/{$p->id}/team/{$person->id}", ['role_id' => $this->roleId('aytam_mushrif')])->assertCreated();
        $me = $this->actingAs($person)->getJson('/api/auth/me')->assertOk()->json('data.programs');
        $this->assertCount(1, $me);
        $this->assertSame($p->id, $me[0]['id']);
        $this->assertSame('aytam_mushrif', $me[0]['role']['key']);
        $this->assertContains('aytam.review', $me[0]['permissions']);
        $this->assertContains('forms.publish', $me[0]['permissions']);
        $this->assertTrue($person->fresh()->hasPermission('aytam.review', $p));
        $this->assertFalse($person->fresh()->hasPermission('aytam.review', $other), 'program roles do not leak into other programs');
        $this->assertFalse($person->fresh()->hasPermission('aytam.review'), 'nor into foundation-wide permissions');
        $this->getJson("/api/programs/{$p->id}")->assertOk();
        $this->getJson("/api/programs/{$other->id}")->assertStatus(404);

        // Changing the role replaces it (still one membership per person per program).
        $this->actingAs($this->admin)->putJson("/api/programs/{$p->id}/team/{$person->id}", ['role_id' => $this->roleId('aytam_field_worker')])->assertOk();
        $this->assertSame(1, ProgramUser::where('program_id', $p->id)->count());
        $this->assertFalse($person->fresh()->hasPermission('aytam.review', $p));

        $team = $this->getJson("/api/programs/{$p->id}/team")->assertOk()->json('data');
        $this->assertSame('Aytam Field Worker', $team['members'][0]['role']['name']);
        $this->assertEqualsCanonicalizing(['aytam_mushrif', 'aytam_field_worker'], array_column($team['roles'], 'key'));

        $this->deleteJson("/api/programs/{$p->id}/team/{$person->id}")->assertOk();
        $this->assertSame(0, ProgramUser::where('program_id', $p->id)->count());
        $this->assertTrue(\App\Models\AuditLog::where('action', 'program_user.removed')->exists());
    }

    public function test_team_assignment_rules(): void
    {
        $p = $this->makeProgram();
        $relief = $this->makeProgram('Relief', 'relief');
        $person = $this->makeUser('staff');
        $this->actingAs($this->admin);

        // A foundation role is not a program role.
        $this->putJson("/api/programs/{$p->id}/team/{$person->id}", ['role_id' => $this->roleId('staff')])->assertStatus(422);
        // Only people with programs.update manage teams.
        $this->actingAs($person)->putJson("/api/programs/{$p->id}/team/{$person->id}", ['role_id' => $this->roleId('aytam_mushrif')])->assertStatus(403);

        // Disabled people and other foundations' people cannot be added.
        $this->actingAs($this->admin);
        $off = $this->makeUser('staff');
        $off->update(['status' => 'disabled']);
        $this->putJson("/api/programs/{$p->id}/team/{$off->id}", ['role_id' => $this->roleId('aytam_mushrif')])->assertStatus(404);
        [$f2, $f2Admin] = $this->createFoundation('Other', 'other@example.test');
        $this->putJson("/api/programs/{$p->id}/team/{$f2Admin->id}", ['role_id' => $this->roleId('aytam_mushrif')])->assertStatus(404);

        // An Aytam role cannot be given on a program that is not an Aytam program.
        $this->putJson("/api/programs/{$relief->id}/team/{$person->id}", ['role_id' => $this->roleId('aytam_mushrif')])->assertStatus(422);
        // ...unless the foundation made a module-less program role.
        $custom = $this->postJson('/api/roles', ['name' => 'Coordinator', 'scope' => 'program', 'permissions' => []])->assertCreated()->json('data.id');
        $this->putJson("/api/programs/{$relief->id}/team/{$person->id}", ['role_id' => $custom])->assertCreated();
    }

    public function test_organizations_and_contacts(): void
    {
        $this->actingAs($this->admin);
        $org = $this->postJson('/api/organizations', ['name' => 'Dar Al Ber', 'type' => 'donor', 'country' => 'UAE', 'location' => 'Dubai', 'email' => 'info@darbd.example'])
            ->assertCreated()->assertJsonPath('data.status', 'active')->json('data.id');
        $this->postJson('/api/organizations', ['name' => 'X', 'type' => 'spaceship'])->assertStatus(422);

        $c1 = $this->postJson("/api/organizations/{$org}/contacts", ['name' => 'Ahmed', 'email' => 'a@d.example', 'is_primary' => true])->assertCreated()->json('data.id');
        $c2 = $this->postJson("/api/organizations/{$org}/contacts", ['name' => 'Bilal', 'is_primary' => true])->assertCreated()->json('data.id');
        $contacts = $this->getJson("/api/organizations/{$org}")->assertOk()->json('data.contacts');
        $this->assertSame([$c2], array_column(array_filter($contacts, fn ($c) => $c['is_primary']), 'id'), 'only one primary contact');
        $this->patchJson("/api/organizations/{$org}/contacts/{$c1}", ['title' => 'Director'])->assertOk()->assertJsonPath('data.title', 'Director');
        $this->deleteJson("/api/organizations/{$org}/contacts/{$c2}")->assertOk();

        $this->patchJson("/api/organizations/{$org}", ['status' => 'inactive'])->assertOk();
        $this->assertCount(0, $this->getJson('/api/organizations?status=active')->json('data'));
        $this->assertCount(1, $this->getJson('/api/organizations?q=dar')->json('data'));

        $this->actingAs($this->makeUser('staff'))->getJson('/api/organizations')->assertOk();       // staff may view
        $this->postJson('/api/organizations', ['name' => 'N', 'type' => 'ngo'])->assertStatus(403); // but not create
        $this->actingAs($this->makeUser('field_worker'))->getJson('/api/organizations')->assertStatus(403);
    }

    public function test_one_organization_serves_several_programs_and_mushrif_manages_aytam_partners(): void
    {
        $aytam = $this->makeProgram('Aytam Care', activate: true);
        $relief = $this->makeProgram('Relief', 'relief', activate: true);
        $org = $this->postJson('/api/organizations', ['name' => 'Partner A', 'type' => 'partner'])->json('data.id');
        $mushrif = $this->makeUser('volunteer');
        $this->putJson("/api/programs/{$aytam->id}/team/{$mushrif->id}", ['role_id' => $this->roleId('aytam_mushrif')])->assertCreated();

        // The Mushrif links partners to Aytam (aytam.configure) but not to other programs.
        $this->actingAs($mushrif)->postJson("/api/programs/{$aytam->id}/organizations", ['organization_id' => $org, 'relationship' => 'funder'])->assertCreated();
        $this->postJson("/api/programs/{$relief->id}/organizations", ['organization_id' => $org])->assertStatus(404);
        $this->postJson("/api/programs/{$aytam->id}/organizations", ['organization_id' => $org])->assertStatus(409);

        $this->actingAs($this->admin)->postJson("/api/programs/{$relief->id}/organizations", ['organization_id' => $org, 'relationship' => 'partner'])->assertCreated();
        $detail = $this->getJson("/api/organizations/{$org}")->json('data.programs');
        $this->assertEqualsCanonicalizing(['Aytam Care', 'Relief'], array_column($detail, 'name'));

        // The relationship config is per program; the organization is shared.
        $link = $this->getJson("/api/programs/{$aytam->id}/organizations")->json('data.links.0.id');
        $this->actingAs($mushrif)->patchJson("/api/programs/{$aytam->id}/organizations/{$link}", ['notes' => 'Sponsors 20 children', 'config' => ['reports' => 'quarterly']])->assertOk();
        $this->assertNull($this->getJson("/api/programs/{$aytam->id}/organizations")->json('data.links.0.organization.notes'));

        // Unlinking is allowed; deleting a linked organization is not.
        $this->actingAs($this->admin)->deleteJson("/api/organizations/{$org}")->assertStatus(409);
        $this->deleteJson("/api/programs/{$relief->id}/organizations/".$this->getJson("/api/programs/{$relief->id}/organizations")->json('data.links.0.id'))->assertOk();
        $this->actingAs($mushrif)->deleteJson("/api/programs/{$aytam->id}/organizations/{$link}")->assertOk();
        $this->actingAs($this->admin)->deleteJson("/api/organizations/{$org}")->assertOk();

        // Another foundation's organization cannot be linked.
        [$f2] = $this->createFoundation('Other', 'other@example.test');
        $theirs = $this->inFoundation($f2)->postJson('/api/organizations', [])->status();
        $theirOrg = app(\App\Tenancy\TenantContext::class)->runAs($f2->id, fn () => Organization::create(['name' => 'Theirs', 'type' => 'ngo'])->id);
        $this->inFoundation($this->foundation);
        $this->actingAs($this->admin)->postJson("/api/programs/{$aytam->id}/organizations", ['organization_id' => $theirOrg])->assertStatus(422);
    }

    public function test_another_foundation_cannot_see_or_touch_programs(): void
    {
        $p = $this->makeProgram('Aytam Care', activate: true);
        [$f2, $f2Admin] = $this->createFoundation('Other', 'other@example.test');

        $this->actingAs($f2Admin);
        $this->getJson('/api/programs')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/programs/{$p->id}")->assertStatus(404);
        $this->patchJson("/api/programs/{$p->id}", ['name' => 'Stolen'])->assertStatus(404);
        $this->postJson("/api/programs/{$p->id}/status", ['status' => 'archived'])->assertStatus(404);
        $this->getJson("/api/programs/{$p->id}/team")->assertStatus(404);
        $this->postJson('/api/programs', ['name' => 'Aytam Care', 'category' => 'aytam'])->assertCreated()->assertJsonPath('data.slug', 'aytam-care');   // slugs are per foundation
    }

    public function test_a_mushrif_can_pick_partners_without_seeing_the_whole_organization_list(): void
    {
        $aytam = $this->makeProgram('Aytam Care', activate: true);
        $a = $this->postJson('/api/organizations', ['name' => 'Partner A', 'type' => 'partner'])->json('data.id');
        $b = $this->postJson('/api/organizations', ['name' => 'Partner B', 'type' => 'donor'])->json('data.id');
        $this->patchJson("/api/organizations/{$b}", ['status' => 'inactive']);
        $mushrif = $this->makeUser('volunteer');
        $this->putJson("/api/programs/{$aytam->id}/team/{$mushrif->id}", ['role_id' => $this->roleId('aytam_mushrif')])->assertCreated();

        $this->actingAs($mushrif)->getJson('/api/organizations')->assertStatus(403);
        $options = $this->getJson("/api/programs/{$aytam->id}/organization-options")->assertOk()->json('data');
        $this->assertSame(['Partner A'], array_column($options, 'name'), 'only active organizations');

        $this->postJson("/api/programs/{$aytam->id}/organizations", ['organization_id' => $a])->assertCreated();
        $this->getJson("/api/programs/{$aytam->id}/organization-options")->assertJsonCount(0, 'data');

        $this->actingAs($this->makeUser('staff'))->getJson("/api/programs/{$aytam->id}/organization-options")->assertStatus(403);   // may see the program, not manage its partners
    }

    public function test_the_team_screen_offers_candidates_only_to_those_who_can_add_them(): void
    {
        $p = $this->makeProgram('Aytam Care', activate: true);
        $member = $this->makeUser('volunteer', 'member@example.test');
        $outsider = $this->makeUser('staff', 'outsider@example.test');
        $this->putJson("/api/programs/{$p->id}/team/{$member->id}", ['role_id' => $this->roleId('aytam_field_worker')])->assertCreated();

        $admin = $this->getJson("/api/programs/{$p->id}/team")->assertOk()->json('data');
        $this->assertTrue($admin['can_manage']);
        $this->assertContains('outsider@example.test', array_column($admin['candidates'], 'email'));
        $this->assertNotContains('member@example.test', array_column($admin['candidates'], 'email'));

        $this->actingAs($member);
        $theirs = $this->getJson("/api/programs/{$p->id}/team")->assertOk()->json('data');
        $this->assertFalse($theirs['can_manage']);
        $this->assertSame([], $theirs['candidates'], 'a member sees the team, not the foundation\'s directory');
    }

    public function test_a_mushrif_configures_aytam_requirements_but_not_the_program_itself(): void
    {
        $p = $this->makeProgram('Aytam Care', activate: true);
        $mushrif = $this->makeUser('volunteer');
        $worker = $this->makeUser('volunteer');
        $this->putJson("/api/programs/{$p->id}/team/{$mushrif->id}", ['role_id' => $this->roleId('aytam_mushrif')])->assertCreated();
        $this->putJson("/api/programs/{$p->id}/team/{$worker->id}", ['role_id' => $this->roleId('aytam_field_worker')])->assertCreated();

        $this->actingAs($mushrif)->patchJson("/api/programs/{$p->id}/config", ['config' => ['required_documents' => ['photo', 'passport'], 'code_prefix' => 'ORP']])->assertOk()
            ->assertJsonPath('data.config.code_prefix', 'ORP')->assertJsonPath('data.config.required_documents.1', 'passport');
        $this->patchJson("/api/programs/{$p->id}/config", ['config' => ['code_prefix' => 'bad']])->assertStatus(422);
        $this->patchJson("/api/programs/{$p->id}/config", ['config' => ['name' => 'Renamed']])->assertStatus(422);
        $this->patchJson("/api/programs/{$p->id}", ['name' => 'Renamed'])->assertStatus(403);              // name and dates stay with managers
        $this->postJson("/api/programs/{$p->id}/status", ['status' => 'archived'])->assertStatus(403);

        $this->actingAs($worker)->patchJson("/api/programs/{$p->id}/config", ['config' => ['code_prefix' => 'XXX']])->assertStatus(403);
        $this->actingAs($this->makeUser('viewer'))->patchJson("/api/programs/{$p->id}/config", ['config' => ['code_prefix' => 'XXX']])->assertStatus(403);   // may see the program, not configure it
        $this->assertSame('ORP', Program::find($p->id)->config['code_prefix']);
    }
}
