<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Aytam;
use App\Models\Document;
use App\Models\Registration;
use App\Models\Role;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

/**
 * Phase 1, section 38: the whole journey in one test, as the people involved would live it - Platform Admin creates a
 * foundation, its administrator builds the team and programs, a Mushrif publishes a form, an applicant applies, the
 * Mushrif approves, documents are verified, a Field Worker is assigned - then tenant isolation and the audit trail are
 * checked against a second foundation.
 */
class Phase1FlowTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
        Storage::disk('local')->deleteDirectory('documents');
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('documents');
        parent::tearDown();
    }

    private function signInAs(string $email, string $password): void
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/auth/login', ['email' => $email, 'password' => $password])->assertOk();
    }

    public function test_the_complete_phase_one_journey(): void
    {
        // 1-2. Platform Admin creates a foundation; its administrator takes over with a password of their own.
        $created = $this->actingAs($this->platformAdmin)->postJson('/api/platform/foundations', [
            'name' => 'Hope Foundation', 'short_name' => 'Hope', 'country' => 'Philippines',
            'admin' => ['name' => 'Aisha Santos', 'email' => 'aisha@hope.example'],
        ])->assertCreated();
        $hopeId = $created->json('data.id');
        $this->signInAs('aisha@hope.example', $created->json('data.administrator.temporary_password'));
        $this->putJson('/api/auth/password', ['current_password' => $created->json('data.administrator.temporary_password'), 'password' => 'Brand-new-pass-1', 'password_confirmation' => 'Brand-new-pass-1'])->assertOk();

        // 3. Foundation Admin creates people (a Mushrif candidate and a Field Worker candidate).
        $roleId = fn (string $key) => app(TenantContext::class)->runAs($hopeId, fn () => Role::where('key', $key)->value('id'));
        $mushrifId = $this->postJson('/api/users', ['name' => 'Yusuf Hassan', 'email' => 'yusuf@hope.example', 'role_id' => $roleId('volunteer')])->assertCreated()->json('data.id');
        $workerId = $this->postJson('/api/users', ['name' => 'Layla Ortiz', 'email' => 'layla@hope.example', 'role_id' => $roleId('volunteer')])->assertCreated()->json('data.id');
        $this->patchJson("/api/users/{$workerId}", ['name' => 'Layla Ortiz-Cruz'])->assertOk();

        // 4. Organizations (foundation-level master) with a contact.
        $orgId = $this->postJson('/api/organizations', ['name' => 'Al-Noor Orphanage', 'type' => 'partner', 'city' => 'Cotabato City'])->assertCreated()->json('data.id');
        $this->postJson("/api/organizations/{$orgId}/contacts", ['name' => 'Salma', 'role' => 'Director', 'phone' => '0917 000 0000'])->assertCreated();

        // 5. Several programs, only one of them Aytam.
        $relief = $this->postJson('/api/programs', ['name' => 'Flood relief', 'category' => 'relief'])->assertCreated()->json('data.id');
        $this->postJson('/api/programs', ['name' => 'Scholarships', 'category' => 'education'])->assertCreated();
        $aytamId = $this->postJson('/api/programs', ['name' => 'Aytam Care', 'category' => 'aytam'])->assertCreated()->json('data.id');
        $this->postJson("/api/programs/{$relief}/status", ['status' => 'active'])->assertOk();

        // 6. Activate Aytam, link the organization, give the people their program roles.
        $this->postJson("/api/programs/{$aytamId}/status", ['status' => 'active'])->assertOk()->assertJsonPath('data.status', 'active');
        $this->postJson("/api/programs/{$aytamId}/organizations", ['organization_id' => $orgId, 'role' => 'partner'])->assertSuccessful();
        $this->putJson("/api/programs/{$aytamId}/team/{$mushrifId}", ['role_id' => $roleId('aytam_mushrif')])->assertSuccessful();
        $this->putJson("/api/programs/{$aytamId}/team/{$workerId}", ['role_id' => $roleId('aytam_field_worker')])->assertSuccessful();
        $base = "/api/programs/{$aytamId}";

        // 7. Mushrif signs in (temporary password), builds the form and publishes the link.
        $mushrif = app(TenantContext::class)->runAs($hopeId, fn () => User::findOrFail($mushrifId));
        $mushrif->forceFill(['password' => 'Mushrif-pass-1', 'must_change_password' => false])->save();
        $this->signInAs('yusuf@hope.example', 'Mushrif-pass-1');
        $form = $this->postJson("{$base}/forms", ['title' => 'Aytam registration', 'template' => 'standard'])->assertCreated()->json('data');
        $form = $this->postJson("{$base}/forms/{$form['id']}/publish")->assertOk()->json('data');
        $token = basename($form['link']);

        // 8. An applicant, with no account, submits through the public link.
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->post('/apply/'.$token, ['f' => [
            'first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '2012-04-17', 'gender' => 'male', 'nationality' => 'Filipino',
            'address' => ['country' => 'Philippines', 'province' => 'Maguindanao', 'city' => 'Cotabato City', 'barangay' => 'Rosary Heights', 'address_detail' => 'Block 4'],
            'education_level' => 'Elementary', 'school' => 'Al-Noor School', 'grade' => '5',
            'father_name' => 'Abdullah', 'father_status' => 'deceased', 'mother_name' => 'Maryam', 'mother_status' => 'living',
            'guardian_name' => 'Aunt Salma', 'guardian_relationship' => 'aunt', 'guardian_phone' => '0917 111 1111', 'guardian_email' => 'salma@example.test',
        ], 'files' => [
            'photo' => UploadedFile::fake()->image('child.jpg', 300, 300),
            'birth_certificate' => UploadedFile::fake()->createWithContent('birth.pdf', "%PDF-1.4\n".str_repeat('b', 2000)."\n%%EOF"),
        ]])->assertRedirect('/apply/'.$token.'/done');
        app(TenantContext::class)->setTenant($hopeId);
        $registration = Registration::firstOrFail();
        $this->assertSame('pending_review', $registration->status);
        $this->assertSame(0, Aytam::count(), 'nothing becomes a permanent record before review');

        // 9. Mushrif reviews and approves: a permanent Aytam ID, and the uploaded documents travel with it.
        $this->signInAs('yusuf@hope.example', 'Mushrif-pass-1');
        $this->getJson("{$base}/registrations/{$registration->id}")->assertOk();
        $approved = $this->postJson("{$base}/registrations/{$registration->id}/approve")->assertOk()->assertJsonPath('data.aytam.aytam_code', 'AYT-000001');
        $recordId = $approved->json('data.aytam.id');
        $this->assertSame(2, Document::where('aytam_id', $recordId)->count());

        // 10. More documents, verified.
        $up = $this->post("{$base}/aytam/{$recordId}/documents", ['type' => 'passport', 'file' => UploadedFile::fake()->createWithContent('p.pdf', "%PDF-1.4\n".str_repeat('p', 1500)."\n%%EOF")], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson("{$base}/documents/".$up->json('data.id').'/decision', ['decision' => 'verified'])->assertOk();
        $this->patchJson("{$base}/aytam/{$recordId}", ['school' => 'Al-Noor Academy'])->assertOk();

        // 11. Assign the Field Worker; they see exactly that record and cannot administer anything.
        $this->putJson("{$base}/aytam/{$recordId}/assignments", ['user_ids' => [$workerId]])->assertOk();
        $this->postJson("{$base}/aytam", ['first_name' => 'Unassigned', 'last_name' => 'Child', 'date_of_birth' => '2014-01-01', 'gender' => 'female'])->assertCreated();
        $worker = app(TenantContext::class)->runAs($hopeId, fn () => User::findOrFail($workerId));
        $worker->forceFill(['password' => 'Worker-pass-1', 'must_change_password' => false])->save();
        $this->signInAs('layla@hope.example', 'Worker-pass-1');
        $this->getJson("{$base}/aytam")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/users')->assertStatus(403);
        $this->postJson('/api/programs', ['name' => 'Nope', 'category' => 'relief'])->assertStatus(403);
        $this->getJson("{$base}/registrations")->assertStatus(403);

        // 12. Tenant isolation: another foundation sees none of it, and cannot reach it by id.
        [$other, $otherAdmin] = $this->createFoundation('Other Foundation', 'owner@other.example');
        $this->actingAs($otherAdmin);
        $this->getJson('/api/programs')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/organizations')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson($base)->assertStatus(404);
        $this->getJson("{$base}/aytam/{$recordId}")->assertStatus(404);
        $this->getJson('/api/users')->assertOk()->assertJsonCount(1, 'data');

        // 13. The audit trail records each step, only inside its own foundation, without contact details or secrets.
        $tenant = app(TenantContext::class);
        $actions = $tenant->runAs($hopeId, fn () => AuditLog::pluck('action')->all());
        foreach (['foundation.provisioned', 'user.created', 'user.updated', 'organization.created', 'program.created', 'program.updated',
            'aytam.created', 'aytam.updated', 'registration.submitted', 'registration.approved', 'document.created', 'document.updated'] as $expected) {
            $this->assertContains($expected, $actions, "audit trail lacks {$expected}");
        }
        $this->assertNotEmpty($tenant->runAs($hopeId, fn () => AuditLog::where('action', 'like', 'role%')->orWhere('action', 'like', 'program_user%')->pluck('action')), 'role/membership changes are audited');
        $this->assertNotContains('registration.submitted', $tenant->runAs($other->getKey(), fn () => AuditLog::pluck('action')->all()), 'the other foundation never sees these entries');
        $hopeLog = json_encode($tenant->runAs($hopeId, fn () => AuditLog::all()->toArray()));
        // Records are named in the trail (so it reads); contact details, addresses and secrets are not.
        foreach (['Block 4', '0917 111 1111', '0917 000 0000', 'salma@example.test', basename($form['link']), 'Brand-new-pass-1', 'Mushrif-pass-1'] as $private) {
            $this->assertStringNotContainsString($private, $hopeLog, "audit trail leaked: {$private}");
        }
        $this->assertTrue($tenant->asPlatform(fn () => AuditLog::where('action', 'foundation.created')->exists()), 'the platform keeps its own record of the foundation being created');
    }
}
