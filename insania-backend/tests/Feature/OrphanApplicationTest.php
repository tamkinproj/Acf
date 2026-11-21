<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\ChildApplication;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class OrphanApplicationTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function body(array $over = []): array
    {
        return $over + [
            'name_en' => 'Abdulrahman Test', 'name_ar' => 'عبد الرحمن', 'gender' => 'm', 'birth_date' => now()->subYears(9)->toDateString(), 'nationality' => 'الفلبين - Philippines',
            'health_status' => 'good', 'health_details' => 'بصحة جيدة', 'study_level' => 'primary', 'grade' => 'الثالث', 'madrasa_grade' => 'الصف الثالث', 'quran_memorization' => 'جزء عم',
            'birth_country' => 'الفلبين', 'residence_country' => 'الفلبين', 'city' => 'Manila', 'address_ar' => 'عنوان تجريبي', 'address_en' => 'Sample address',
            'mother_name_ar' => 'أم تجريبية', 'mother_name' => 'Sample Mother', 'mother_marital_status' => 'Widow', 'mother_works' => 'no', 'mother_salary' => 0, 'father_death_date' => '2020-01-01',
            'brothers' => 2, 'sisters' => 1, 'aided_siblings' => 0, 'guardian_name' => 'Sample Guardian A', 'guardian_relation' => 'Uncle', 'guardian_phone' => '09171234567',
            'responsible_name_ar' => 'مسؤول تجريبي', 'responsible_name_en' => 'Sample Responsible', 'responsible_relation' => 'العم', 'responsible_since' => '2025-03-01',
            'bank_holder' => 'Sample Guardian A', 'bank_name' => 'AUB', 'bank_last4' => '2381',
        ];
    }

    private function png(string $name = 'p.png'): UploadedFile
    {
        $im = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    public function test_anyone_with_the_link_can_apply_and_it_lands_in_the_review_queue(): void
    {
        Storage::fake('local');
        $this->world();
        config(['orphan_fields.apply.per_hour' => 1000]);
        $this->getJson('/api/public/apply')->assertOk()->assertJsonPath('data.open', true);
        $before = Child::withoutGlobalScopes()->count();

        $r = $this->post('/api/public/apply', $this->body() + ['photos' => [$this->png('a.png'), $this->png('b.png')]], ['Accept' => 'application/json'])->assertCreated();
        $this->assertMatchesRegularExpression('/^APP-\d{5}$/', $r->json('data.reference'));
        $a = ChildApplication::withoutGlobalScopes()->first();
        $this->assertSame('new', $a->status);
        $this->assertSame('Abdulrahman Test', $a->data['name_en']);
        $this->assertCount(2, $a->photos);
        Storage::disk('local')->assertExists($a->photos[0]['path']);
        $this->assertSame($before, Child::withoutGlobalScopes()->count(), 'nothing becomes a record until staff approve it');
        $this->assertNotSame('127.0.0.1', $a->ip_hash);
    }

    public function test_the_form_refuses_bad_answers_staff_only_fields_and_robots(): void
    {
        Storage::fake('local');
        $this->world();
        config(['orphan_fields.apply.per_hour' => 1000]);
        $h = ['Accept' => 'application/json'];
        $this->postJson('/api/public/apply', [])->assertStatus(422);
        $this->postJson('/api/public/apply', $this->body(['guardian_phone' => 'abc']))->assertStatus(422)->assertJsonValidationErrors('guardian_phone');
        $this->postJson('/api/public/apply', $this->body(['birth_date' => now()->addDay()->toDateString()]))->assertStatus(422);
        $this->postJson('/api/public/apply', $this->body(['bank_last4' => '12']))->assertStatus(422);
        $this->postJson('/api/public/apply', $this->body(['name_en' => null, 'name_ar' => null]))->assertStatus(422);
        $this->post('/api/public/apply', $this->body() + ['photos' => [$this->png('a.png'), $this->png('b.png'), $this->png('c.png')]], $h)->assertStatus(422);
        $this->post('/api/public/apply', $this->body() + ['photos' => [UploadedFile::fake()->createWithContent('x.png', '<?php echo 1;')]], $h)->assertStatus(422);
        $this->assertSame(0, ChildApplication::withoutGlobalScopes()->count());

        // answers only the foundation fills in are ignored, never stored
        $this->postJson('/api/public/apply', $this->body(['status' => 'ended', 'sponsor_name' => 'Hacker', 'organization_comment' => 'x', 'performance' => 'excellent']))->assertCreated();
        $data = ChildApplication::withoutGlobalScopes()->first()->data;
        foreach (['status', 'sponsor_name', 'organization_comment', 'performance'] as $k) {
            $this->assertArrayNotHasKey($k, $data);
        }
        // the hidden "website" field is for robots: looks like success, stores nothing
        $this->postJson('/api/public/apply', $this->body(['website' => 'http://spam']))->assertCreated();
        $this->assertSame(1, ChildApplication::withoutGlobalScopes()->count());
    }

    public function test_the_form_can_be_closed_and_is_rate_limited(): void
    {
        $this->world();
        config(['features.public_apply' => false]);
        $this->postJson('/api/public/apply', $this->body())->assertStatus(503);
        $this->getJson('/api/public/apply')->assertJsonPath('data.open', false);

        config(['features.public_apply' => true, 'orphan_fields.apply.per_day' => 2, 'orphan_fields.apply.per_hour' => 1000]);
        $this->postJson('/api/public/apply', $this->body())->assertCreated();
        $this->postJson('/api/public/apply', $this->body())->assertCreated();
        $this->postJson('/api/public/apply', $this->body())->assertStatus(429);
    }

    public function test_one_address_can_only_send_a_few_per_hour(): void
    {
        $this->world();
        config(['orphan_fields.apply.per_hour' => 2]);
        $this->postJson('/api/public/apply', $this->body())->assertCreated();
        $this->postJson('/api/public/apply', $this->body())->assertCreated();
        $this->postJson('/api/public/apply', $this->body())->assertStatus(429);
    }

    public function test_aytam_staff_review_approve_and_reject_and_nobody_else_can(): void
    {
        Storage::fake('local');
        $this->world();
        config(['orphan_fields.apply.per_hour' => 1000]);
        $this->post('/api/public/apply', $this->body() + ['photos' => [$this->png()]], ['Accept' => 'application/json'])->assertCreated();
        $this->postJson('/api/public/apply', $this->body(['name_en' => 'Second Applicant']))->assertCreated();
        [$first, $second] = ChildApplication::withoutGlobalScopes()->orderBy('id')->get()->all();

        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $this->getJson('/api/applications')->assertOk();   // the President reviews applications too
        foreach (['security_admin', 'staff:finance', 'staff:projects', 'user', 'system_admin', 'admin_maintenance'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->getJson('/api/applications')->assertForbidden();
            $this->postJson("/api/applications/{$first->id}/approve")->assertForbidden();
            $this->getJson("/api/applications/{$first->id}/photos/0")->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        $this->actingAsRole('staff:aytam');
        $this->getJson('/api/applications')->assertOk()->assertJsonPath('data.counts.new', 2)->assertJsonCount(2, 'data.items');
        $this->getJson("/api/applications/{$first->id}")->assertOk()->assertJsonPath('data.details.guardian_phone', '09171234567')->assertJsonPath('data.photos', 1);
        $this->get("/api/applications/{$first->id}/photos/0", ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');

        $made = $this->postJson("/api/applications/{$first->id}/approve")->assertOk()->json('data.child');
        $c = Child::find($made['id']);
        $this->assertSame('Abdulrahman Test', $c->name_en);
        $this->assertSame('active', $c->status);
        $this->assertNotNull($c->form_submitted_at);
        $this->assertSame('Sample Guardian A', $c->guardian->name);
        $this->assertSame('2381', $c->bankAccount->last4);
        $this->assertSame('الثالث', $c->grade);
        $this->assertSame(1, $c->photos()->count());
        $p = $this->getJson("/api/children/{$c->id}")->assertOk()->assertJsonPath('data.details.mother_name_ar', 'أم تجريبية')->assertJsonPath('data.details.address_en', 'Sample address')->assertJsonPath('data.details.bank_last4', '2381')->assertJsonPath('data.photos.0', $c->photos()->first()->id)->json('data');
        $this->get("/api/children/{$c->id}/photos/{$p['photos'][0]}", ['Accept' => 'application/json'])->assertOk();
        $this->postJson("/api/applications/{$first->id}/approve")->assertStatus(422)->assertJsonPath('code', 'ALREADY_REVIEWED');

        $this->postJson("/api/applications/{$second->id}/reject", ['note' => 'Incomplete'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->getJson('/api/applications?status=new')->assertOk()->assertJsonCount(0, 'data.items');
        $this->postJson("/api/applications/{$second->id}/approve")->assertStatus(422);
        $this->getJson('/api/dashboard')->assertOk()->assertJsonPath('data.applications.new', 0);
    }

    public function test_staff_can_edit_every_field_and_remove_only_the_samples(): void
    {
        $this->world();
        config(['features.switches' => (require base_path('config/features.php'))['switches']]);   // as shipped
        $this->actingAsRole('staff:aytam');
        $id = $this->postJson('/api/children', ['name_en' => 'Manual Orphan', 'guardian' => ['name' => 'G']])->assertCreated()->json('data.id');
        $this->patchJson("/api/children/$id", ['nationality' => 'Philippines', 'performance' => 'very_good', 'sponsor_file_no' => 'SP-9', 'mother_works' => 'yes', 'mother_salary' => 5000, 'responsible_since' => '2025-03-01', 'expected_send_date' => '2026-12-01', 'delay_reason' => 'Bank delay'])
            ->assertOk()->assertJsonPath('data.details.performance', 'very_good')->assertJsonPath('data.details.mother_salary', '5000.00')->assertJsonPath('data.details.responsible_since', '2025-03-01');
        $this->patchJson("/api/children/$id", ['performance' => 'amazing'])->assertStatus(422);
        $this->patchJson("/api/children/$id", ['mother_works' => 'maybe'])->assertStatus(422);

        Child::where('id', '!=', $id)->take(2)->update(['is_sample' => true]);
        $real = Child::count() - 2;
        $this->deleteJson('/api/children/samples')->assertOk()->assertJsonPath('data.removed', 2);
        $this->assertSame($real, Child::count());
        $this->assertNotNull(Child::find($id));

        foreach (['staff:finance', 'system_admin'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->deleteJson('/api/children/samples')->assertForbidden();
        }
    }

    public function test_documents_can_be_sent_with_an_application_and_only_reviewers_see_them(): void
    {
        Storage::fake('local');
        $this->world();
        config(['orphan_fields.apply.per_hour' => 1000]);
        $this->getJson('/api/public/apply')->assertJsonPath('data.max_documents', 5);
        $docs = array_map(fn ($i) => $this->png("d$i.png"), range(1, 3));
        $this->post('/api/public/apply', $this->body() + ['method' => 'documents', 'documents' => $docs], ['Accept' => 'application/json'])->assertCreated();
        $a = ChildApplication::withoutGlobalScopes()->first();
        $this->assertSame('documents', $a->method);
        $this->assertCount(3, $a->documents);
        $this->post('/api/public/apply', $this->body() + ['documents' => array_map(fn ($i) => $this->png("e$i.png"), range(1, 6))], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/api/public/apply', $this->body() + ['method' => 'robot'], ['Accept' => 'application/json'])->assertStatus(422);

        $this->actingAsRole('staff:finance');
        $this->getJson("/api/applications/{$a->id}/documents/0")->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('staff:aytam');
        $this->getJson('/api/applications')->assertJsonPath('data.items.0.documents', 3)->assertJsonPath('data.items.0.method', 'documents');
        $this->get("/api/applications/{$a->id}/documents/2", ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->getJson("/api/applications/{$a->id}/documents/3")->assertNotFound();
    }

    public function test_staff_can_correct_the_bank_account_and_photos_and_it_must_be_verified_again(): void
    {
        Storage::fake('local');
        $this->world();
        config(['features.switches' => (require base_path('config/features.php'))['switches']]);
        $this->actingAsRole('staff:aytam');
        $id = $this->postJson('/api/children', ['name_en' => 'Bank Fix', 'bank' => ['holder' => 'A', 'bank_name' => 'AUB', 'last4' => '1111']])->assertCreated()->json('data.id');
        $this->putJson("/api/children/$id/bank/verification", ['verified' => true])->assertOk();
        $this->patchJson("/api/children/$id", ['grade' => 'Grade 2'])->assertOk()->assertJsonPath('data.bank.verified', true);          // other edits keep it verified
        $this->patchJson("/api/children/$id", ['bank' => ['holder' => 'A', 'bank_name' => 'AUB', 'last4' => '1111']])->assertOk()->assertJsonPath('data.bank.verified', true);   // same values: still verified
        $this->patchJson("/api/children/$id", ['bank' => ['holder' => 'A', 'bank_name' => 'Hello Money', 'last4' => '2222']])->assertOk()
            ->assertJsonPath('data.bank.last4', '2222')->assertJsonPath('data.bank.verified', false);
        $this->patchJson("/api/children/$id", ['bank' => ['holder' => 'A', 'bank_name' => 'X', 'last4' => '12']])->assertStatus(422);
        $id2 = $this->postJson('/api/children', ['name_en' => 'No Bank Yet'])->assertCreated()->json('data.id');
        $this->patchJson("/api/children/$id2", ['bank' => ['holder' => 'B', 'bank_name' => 'AUB', 'last4' => '3333']])->assertOk()->assertJsonPath('data.bank.last4', '3333');

        $pid = $this->post("/api/children/$id/photos", ['photo' => $this->png()], ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->getJson("/api/children/$id")->assertJsonPath('data.photos.0', $pid);
        $this->post("/api/children/$id/photos", ['photo' => UploadedFile::fake()->createWithContent('x.png', 'not an image')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->deleteJson("/api/children/$id/photos/$pid")->assertOk();
        $this->getJson("/api/children/$id")->assertJsonPath('data.photos', []);

        $this->app['auth']->forgetGuards();
        $this->actingAsRole('staff:finance');
        $this->post("/api/children/$id/photos", ['photo' => $this->png()], ['Accept' => 'application/json'])->assertForbidden();
        $this->patchJson("/api/children/$id", ['bank' => ['holder' => 'Z', 'bank_name' => 'Z', 'last4' => '9999']])->assertForbidden();
    }

    public function test_an_upload_over_the_server_limit_gets_a_clear_message(): void
    {
        $r = $this->call('POST', '/api/public/apply', [], [], [], ['CONTENT_LENGTH' => 1024 * 1024 * 1024, 'HTTP_ACCEPT' => 'application/json']);
        $r->assertStatus(413)->assertJsonPath('code', 'TOO_LARGE');
        $this->assertStringContainsString('post_max_size', $r->json('message'));
    }
}
