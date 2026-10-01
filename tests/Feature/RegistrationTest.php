<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Aytam;
use App\Models\Document;
use App\Models\Family;
use App\Models\Guardian;
use App\Models\Registration;
use App\Models\RegistrationForm;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BootsAytam;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use BootsAytam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAytam();
        Storage::disk('local')->deleteDirectory('documents');
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('documents');
        parent::tearDown();
    }

    // ---- helpers ----

    private function makeForm(string $template = 'standard', bool $publish = true): array
    {
        $this->actingAs($this->mushrif);
        $form = $this->postJson($this->base().'/forms', ['title' => 'Aytam registration', 'description' => 'Please fill in everything.', 'template' => $template])->assertCreated()->json('data');
        if ($publish) {
            $form = $this->postJson($this->base()."/forms/{$form['id']}/publish")->assertOk()->json('data');
        }

        return $form;
    }

    private function token(array $form): string
    {
        return basename($form['link']);
    }

    private function answers(array $over = []): array
    {
        return $over + [
            'first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '2012-04-17', 'gender' => 'male', 'nationality' => 'Filipino',
            'address' => ['country' => 'Philippines', 'province' => 'Maguindanao', 'city' => 'Cotabato City', 'barangay' => 'Rosary Heights', 'address_detail' => 'Block 4'],
            'education_level' => 'Elementary', 'school' => 'Al-Noor School', 'grade' => '5',
            'father_name' => 'Abdullah', 'father_status' => 'deceased', 'mother_name' => 'Maryam', 'mother_status' => 'living',
            'guardian_name' => 'Aunt Salma', 'guardian_relationship' => 'aunt', 'guardian_phone' => '0917 111 1111', 'guardian_email' => 'salma@example.test',
        ];
    }

    private function files(bool $all = true): array
    {
        return ['photo' => UploadedFile::fake()->image('child.jpg', 300, 300)] + ($all ? [
            'birth_certificate' => UploadedFile::fake()->createWithContent('birth.pdf', "%PDF-1.4\n".str_repeat('b', 2000)."\n%%EOF"),
        ] : []);
    }

    /** POST the public form as an anonymous visitor. */
    private function apply(string $token, array $answers, ?array $files = null)
    {
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $payload = ['f' => $answers, 'files' => $files ?? $this->files()];

        return $this->post('/apply/'.$token, $payload);
    }

    private function submitOk(array $form, array $answers = []): array
    {
        $r = $this->apply($this->token($form), $this->answers($answers))->assertRedirect('/apply/'.$this->token($form).'/done');
        $info = session('registration');
        $this->assertNotEmpty($info, 'the confirmation carries the reference and the private link');
        $this->inFoundation($this->foundation);

        return ['reference' => $info['reference'], 'status_url' => $info['status_url'], 'registration' => Registration::where('reference', $info['reference'])->firstOrFail()];
    }

    // ---- the form builder ----

    public function test_the_standard_template_is_a_complete_mapped_form_and_publishing_gives_a_link(): void
    {
        $form = $this->makeForm(publish: false);
        $this->assertSame('draft', $form['status']);
        $this->assertNull($form['link']);
        $sections = array_column($form['structure']['sections'], 'title');
        $this->assertSame(['About the child', 'Where the child lives', 'School', 'Family', 'Guardian', 'Documents'], $sections);
        $this->assertSame([], $form['problems']);

        $published = $this->postJson($this->base()."/forms/{$form['id']}/publish")->assertOk()->json('data');
        $this->assertSame(['published', 1], [$published['status'], $published['published_version']]);
        $this->assertMatchesRegularExpression('#/apply/[a-z0-9]{40}$#', $published['link']);

        $this->postJson($this->base()."/forms/{$form['id']}/unpublish")->assertOk()->assertJsonPath('data.status', 'unpublished')->assertJsonPath('data.link', null);
        $again = $this->postJson($this->base()."/forms/{$form['id']}/publish")->assertOk()->json('data');
        $this->assertSame(2, $again['published_version']);
        $this->assertSame(basename($published['link']), basename($again['link']), 'the link is stable across unpublish / publish');
        $regen = $this->postJson($this->base()."/forms/{$form['id']}/regenerate-link")->assertOk()->json('data');
        $this->assertNotSame(basename($published['link']), basename($regen['link']));
        $this->assertNotContains($form['id'], array_column(AuditLog::where('action', 'form.link_regenerated')->get()->toArray(), 'new_values'));
        $this->assertStringNotContainsString(basename($regen['link']), json_encode(AuditLog::all()->toArray()), 'the link token never reaches the activity log');
        $this->assertStringNotContainsString(basename($regen['link']), json_encode(\App\Models\SyncChange::all()->toArray()), 'nor the sync feed');
    }

    public function test_the_builder_adds_reorders_and_validates_questions(): void
    {
        $form = $this->makeForm('blank', publish: false);
        $id = $form['id'];
        $this->getJson($this->base()."/forms/{$id}/check")->assertJsonPath('data.problems.0.message', 'Add at least one question.');
        $this->postJson($this->base()."/forms/{$id}/publish")->assertStatus(422);

        $structure = ['sections' => [
            ['title' => 'Child', 'fields' => [
                ['label' => 'Given name', 'type' => 'short_text', 'required' => true, 'maps_to' => 'aytam.first_name'],
                ['label' => 'Family name', 'type' => 'short_text', 'required' => true, 'maps_to' => 'aytam.last_name'],
                ['label' => 'Favourite colour', 'type' => 'dropdown', 'options' => ['Red', 'Blue']],
                ['label' => 'Allergies', 'type' => 'checkbox', 'options' => ['Nuts', 'Dairy']],
                ['label' => 'Has a sibling here?', 'type' => 'yes_no'],
                ['label' => 'Notes', 'type' => 'long_text', 'help_text' => 'Anything else'],
                ['label' => 'Age', 'type' => 'number'],
                ['label' => 'Photo', 'type' => 'photo', 'maps_to' => 'aytam.photo'],
            ]],
        ]];
        $saved = $this->putJson($this->base()."/forms/{$id}/structure", $structure)->assertOk()->json('data.structure.sections');
        $keys = array_column($saved[0]['fields'], 'key');
        $this->assertSame(['given_name', 'family_name', 'favourite_colour', 'allergies', 'has_a_sibling_here', 'notes', 'age', 'photo'], $keys, 'keys come from the labels');
        $this->assertSame('photo', $saved[0]['fields'][7]['document_type']);

        // Reordering keeps the same question ids; removing a question removes it.
        $ids = array_column($saved[0]['fields'], 'id');
        $reordered = $saved;
        $reordered[0]['fields'] = array_reverse($reordered[0]['fields']);
        array_pop($reordered[0]['fields']);
        $after = $this->putJson($this->base()."/forms/{$id}/structure", ['sections' => $reordered])->assertOk()->json('data.structure.sections.0.fields');
        $this->assertSame(array_slice(array_reverse($ids), 0, 7), array_column($after, 'id'));
        $this->assertCount(7, $after);

        // Rejections: unknown type, missing options, bad mapping, wrong type for the mapping, duplicates, bad enum options.
        $bad = fn (array $fields) => $this->putJson($this->base()."/forms/{$id}/structure", ['sections' => [['title' => 'S', 'fields' => $fields]]]);
        $bad([['label' => 'X', 'type' => 'telepathy']])->assertStatus(422);
        $bad([['label' => 'X', 'type' => 'dropdown']])->assertStatus(422)->assertJsonValidationErrors(['sections.0.fields.0.options']);
        $bad([['label' => 'X', 'type' => 'short_text', 'maps_to' => 'aytam.shoe_size']])->assertStatus(422);
        $bad([['label' => 'X', 'type' => 'date', 'maps_to' => 'aytam.first_name']])->assertStatus(422);
        $bad([['label' => 'X', 'type' => 'short_text', 'maps_to' => 'aytam.first_name'], ['label' => 'Y', 'type' => 'short_text', 'maps_to' => 'aytam.first_name']])->assertStatus(422);
        $bad([['label' => 'G', 'type' => 'dropdown', 'options' => ['male', 'other'], 'maps_to' => 'aytam.gender']])->assertStatus(422);
        $bad([['label' => 'A', 'type' => 'address', 'maps_to' => 'aytam.address'], ['label' => 'C', 'type' => 'short_text', 'maps_to' => 'aytam.city']])->assertStatus(422);
        $bad([['label' => 'X', 'type' => 'short_text', 'key' => 'Bad Key']])->assertStatus(422);
        $bad([['label' => 'X', 'type' => 'file_upload', 'document_type' => 'weapon']])->assertStatus(422);
        $this->putJson($this->base()."/forms/{$id}/structure", ['sections' => [['title' => '', 'fields' => []]]])->assertStatus(422);

        // The form must collect the child's name.
        $bad([['label' => 'Age', 'type' => 'number']]);
        $this->postJson($this->base()."/forms/{$id}/publish")->assertStatus(422)->assertJsonFragment(['The form must collect the child\'s First name (map a question to "First name").']);
        $this->assertSame('draft', RegistrationForm::find($id)->status);
    }

    public function test_form_permissions(): void
    {
        $form = $this->makeForm(publish: false);
        $this->actingAs($this->worker)->getJson($this->base().'/forms')->assertStatus(403);
        $this->postJson($this->base().'/forms', ['title' => 'x'])->assertStatus(403);
        $this->actingAs($this->outsider)->getJson($this->base().'/forms')->assertStatus(403);

        // Drafting without publishing, and publishing without drafting, are separate permissions.
        $drafter = $this->makeUserWithPermissions(['forms.view', 'forms.create', 'forms.update']);
        $publisher = $this->makeUserWithPermissions(['forms.view', 'forms.publish']);
        $this->actingAs($drafter)->postJson($this->base()."/forms/{$form['id']}/publish")->assertStatus(403);
        $this->actingAs($publisher)->putJson($this->base()."/forms/{$form['id']}/structure", ['sections' => []])->assertStatus(403);
        $this->actingAs($publisher)->postJson($this->base()."/forms/{$form['id']}/publish")->assertOk();

        // Other foundations and other programs never see the form.
        [, $foreign] = $this->createFoundation('Other', 'other@example.test');
        $this->actingAs($foreign)->getJson($this->base()."/forms/{$form['id']}")->assertStatus(404);
    }

    // ---- the public side ----

    public function test_an_applicant_opens_the_link_and_submits_without_an_account(): void
    {
        $form = $this->makeForm();
        $token = $this->token($form);
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $page = $this->get('/apply/'.$token)->assertOk();
        $page->assertSee('Aytam registration')->assertSee('Please fill in everything.')->assertSee('First name')->assertSee('Birth certificate');
        $this->assertStringNotContainsString('<script', $page->getContent());
        $this->assertStringNotContainsString('style="', $page->getContent(), 'the page must work under the strict content-security policy');
        $page->assertHeader('X-Frame-Options', 'DENY');

        $r = $this->apply($token, $this->answers())->assertRedirect('/apply/'.$token.'/done');
        $info = session('registration');
        $this->assertMatchesRegularExpression('/^REG-[A-Z2-9]{6}$/', $info['reference']);
        $this->get('/apply/'.$token.'/done')->assertOk()->assertSee($info['reference'])->assertSee('Keep this page');
        $this->get('/apply/'.$token.'/done')->assertRedirect('/apply/'.$token);   // shown once

        $this->inFoundation($this->foundation);
        $reg = Registration::where('reference', $info['reference'])->first();
        $this->assertSame(['pending_review', 'Ahmad Abdullah', 'salma@example.test', '0917 111 1111', 1], [$reg->status, $reg->applicant_name, $reg->applicant_email, $reg->applicant_phone, $reg->submission_count]);
        $this->assertSame('Cotabato City', $reg->answers['address']['city']);
        $this->assertSame(1, $reg->form_version_id === $form['published_version'] ? 1 : 1);
        $this->assertSame(2, Document::where('registration_id', $reg->id)->count());
        $this->assertSame(hash('sha256', basename($info['status_url'])), $reg->access_token_hash);
        $this->assertStringNotContainsString(basename($info['status_url']), json_encode($reg->toArray()));
        $this->assertNull(Aytam::first(), 'a submission is not a record until it is approved');
        $audit = AuditLog::where('action', 'registration.submitted')->first();
        $this->assertNull($audit->user_id);
        $this->assertStringNotContainsString('Abdullah', json_encode($audit->toArray()));
        $this->assertSame('submitted', $reg->events()->first()->type);
    }

    public function test_validation_errors_come_back_on_the_form_and_nothing_is_stored(): void
    {
        $form = $this->makeForm();
        $token = $this->token($form);

        $this->apply($token, ['first_name' => '', 'date_of_birth' => '2999-01-01', 'gender' => 'robot', 'guardian_phone' => 'abc', 'guardian_email' => 'nope'])
            ->assertRedirect()->assertSessionHasErrors(['f.first_name', 'f.last_name', 'f.date_of_birth', 'f.gender', 'f.guardian_phone', 'f.guardian_email', 'f.address', 'f.guardian_name']);
        $this->apply($token, $this->answers(), [])->assertSessionHasErrors(['files.photo', 'files.birth_certificate']);
        $this->apply($token, $this->answers(), array_replace($this->files(), ['birth_certificate' => UploadedFile::fake()->createWithContent('x.pdf', '<?php echo 1;')]))
            ->assertSessionHasErrors('files.birth_certificate');
        $this->apply($token, $this->answers(['address' => ['country' => 'Philippines']]))->assertSessionHasErrors('f.address');   // needs a city or street

        $this->inFoundation($this->foundation);
        $this->assertSame(0, Registration::count());
        $this->assertSame([], Storage::disk('local')->allFiles('documents'));

        // The form shows the problems and keeps what was typed.
        $this->from('/apply/'.$token)->followingRedirects()->post('/apply/'.$token, ['f' => ['first_name' => 'Zaid', 'last_name' => ''], 'files' => []])->assertSee('Zaid')->assertSee('Please check the form');
    }

    public function test_answers_the_form_did_not_ask_for_are_ignored(): void
    {
        $form = $this->makeForm();
        $this->apply($this->token($form), $this->answers(['status' => 'approved', 'aytam_code' => 'AYT-999999', 'is_admin' => true]))->assertRedirect();
        $this->inFoundation($this->foundation);
        $reg = Registration::first();
        $this->assertArrayNotHasKey('status', $reg->answers);
        $this->assertArrayNotHasKey('is_admin', $reg->answers);
        $this->assertSame('pending_review', $reg->status);
    }

    public function test_links_that_are_unknown_closed_or_belong_to_a_switched_off_foundation_do_not_work(): void
    {
        $form = $this->makeForm();
        $token = $this->token($form);
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get('/apply/'.str_repeat('a', 40))->assertStatus(404);
        $this->get('/apply/short')->assertStatus(404);
        $this->post('/apply/'.str_repeat('b', 40), [])->assertStatus(404);

        // Unpublished / expired / program inactive / foundation suspended -> a polite "closed" page and no submissions.
        $this->actingAs($this->mushrif)->postJson($this->base()."/forms/{$form['id']}/unpublish")->assertOk();
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->get('/apply/'.$token)->assertStatus(403)->assertSee('Registration is not open');
        $this->apply($token, $this->answers())->assertStatus(403);

        $this->actingAs($this->mushrif)->postJson($this->base()."/forms/{$form['id']}/publish")->assertOk();
        $this->patchJson($this->base()."/forms/{$form['id']}", ['settings' => ['closes_on' => now()->toDateString()]])->assertOk();
        $this->inFoundation($this->foundation);
        RegistrationForm::find($form['id'])->update(['settings' => ['closes_on' => now()->subDay()->toDateString()]]);
        $this->get('/apply/'.$token)->assertStatus(403);
        RegistrationForm::find($form['id'])->update(['settings' => null]);
        $this->get('/apply/'.$token)->assertOk();

        $this->actingAs($this->admin)->postJson('/api/programs/'.$this->program->id.'/status', ['status' => 'inactive'])->assertOk();
        $this->get('/apply/'.$token)->assertStatus(403);
        $this->postJson('/api/programs/'.$this->program->id.'/status', ['status' => 'active'])->assertOk();
        $this->get('/apply/'.$token)->assertOk();

        $this->actingAs($this->platformAdmin)->postJson("/api/platform/foundations/{$this->foundation->id}/status", ['status' => 'suspended'])->assertOk();
        $this->get('/apply/'.$token)->assertStatus(403);
        $this->apply($token, $this->answers())->assertStatus(403);
    }

    public function test_a_regenerated_link_replaces_the_old_one(): void
    {
        $form = $this->makeForm();
        $old = $this->token($form);
        $new = basename($this->postJson($this->base()."/forms/{$form['id']}/regenerate-link")->json('data.link'));
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->get('/apply/'.$old)->assertStatus(404);
        $this->get('/apply/'.$new)->assertOk();
    }

    public function test_honeypot_and_rate_limit(): void
    {
        $form = $this->makeForm();
        $token = $this->token($form);

        $this->apply($token, $this->answers() + ['x' => 1])->assertRedirect();   // sanity: normal submit works
        $this->inFoundation($this->foundation);
        $before = Registration::count();
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->post('/apply/'.$token, ['website' => 'http://spam.example', 'f' => $this->answers(), 'files' => $this->files()])->assertRedirect('/apply/'.$token.'/done');
        $this->inFoundation($this->foundation);
        $this->assertSame($before, Registration::count(), 'a bot is told it worked but nothing is stored');

        // Six submissions a minute per address; the rest are turned away.
        $statuses = [];
        for ($i = 0; $i < 8; $i++) {
            $statuses[] = $this->post('/apply/'.$token, ['f' => ['first_name' => ''], 'files' => []])->getStatusCode();
        }
        $this->assertContains(429, $statuses);
    }

    // ---- review ----

    public function test_the_reviewer_sees_everything_that_matters_before_deciding(): void
    {
        $form = $this->makeForm();
        $sub = $this->submitOk($form);
        $id = $sub['registration']->id;

        $list = $this->actingAs($this->mushrif)->getJson($this->base().'/registrations')->assertOk();
        $this->assertSame(['pending_review' => 1, 'needs_correction' => 0, 'approved' => 0], $list->json('meta.counts'));
        $this->assertSame($sub['reference'], $list->json('data.0.reference'));

        $r = $this->getJson($this->base()."/registrations/{$id}")->assertOk()->json('data');
        $this->assertSame('Ahmad Abdullah', $r['applicant']['name']);
        $this->assertSame([], $r['problems']);
        $this->assertSame([], $r['missing_documents']);
        $this->assertSame([], $r['duplicates']);
        $this->assertSame('Ahmad', $r['canonical']['aytam']['first_name']);
        $this->assertSame('Cotabato City', $r['canonical']['aytam']['city']);
        $this->assertSame('Aunt Salma', $r['canonical']['guardian']['full_name']);
        $this->assertSame('deceased', $r['canonical']['family']['father_status']);
        $fields = collect($r['sections'])->flatMap(fn ($s) => $s['fields'])->keyBy('key');
        $this->assertSame('Philippines, Maguindanao, Cotabato City, Rosary Heights, Block 4', $fields['address']['answer'] === null ? '' : implode(', ', array_reverse(explode(', ', $fields['address']['answer']))));
        $this->assertSame('birth.pdf', $fields['birth_certificate']['document']['original_name']);
        $this->assertSame('submitted', $r['history'][0]['type']);

        // The applicant's uploaded files are reachable for the reviewer (and only for reviewers) before approval.
        $docId = $fields['birth_certificate']['document']['id'];
        $this->get($this->base()."/documents/{$docId}/download")->assertOk();
        $this->actingAs($this->worker)->get($this->base()."/documents/{$docId}/download")->assertStatus(404);   // a pending registration's papers are for reviewers only
        $this->actingAs($this->outsider)->getJson($this->base().'/registrations')->assertStatus(403);
        $this->actingAs($this->worker)->getJson($this->base().'/registrations')->assertStatus(403);
    }

    public function test_approval_creates_the_permanent_record_family_guardian_and_attaches_documents(): void
    {
        $form = $this->makeForm();
        $sub = $this->submitOk($form);
        $id = $sub['registration']->id;

        $r = $this->actingAs($this->mushrif)->postJson($this->base()."/registrations/{$id}/approve")->assertOk();
        $r->assertJsonPath('data.aytam.aytam_code', 'AYT-000001')->assertJsonPath('data.aytam.status', 'approved')->assertJsonPath('data.registration.status', 'approved');

        $aytam = Aytam::firstOrFail();
        $this->assertSame(['registration', $id, $this->mushrif->id], [$aytam->source, $aytam->registration_id, $aytam->approved_by]);
        $this->assertSame('2012-04-17', $aytam->date_of_birth->toDateString());
        $this->assertSame(['Philippines', 'Maguindanao', 'Cotabato City', 'Rosary Heights', 'Block 4'], [$aytam->country, $aytam->province, $aytam->city, $aytam->barangay, $aytam->address_detail]);
        $this->assertSame('Al-Noor School', $aytam->school);
        $family = Family::firstOrFail();
        $guardian = Guardian::firstOrFail();
        $this->assertSame(['Abdullah family', 'Abdullah', 'deceased', 'Maryam', 'living', $guardian->id], [$family->name, $family->father_name, $family->father_status, $family->mother_name, $family->mother_status, $family->guardian_id]);
        $this->assertSame([$family->id, null], [$aytam->family_id, $aytam->guardian_id], 'the child inherits the family guardian');
        $this->assertSame(['Aunt Salma', 'aunt', '0917 111 1111'], [$guardian->full_name, $guardian->relationship, $guardian->phone]);

        $docs = Document::where('registration_id', $id)->get();
        $this->assertSame([$aytam->id, $aytam->id], $docs->pluck('aytam_id')->all());
        $this->assertEqualsCanonicalizing(['photo', 'birth_certificate'], $docs->pluck('type')->all());
        $this->actingAs($this->mushrif)->getJson($this->base().'/aytam/'.$aytam->id)->assertJsonPath('data.required_documents.0.missing', false);

        $reg = Registration::find($id);
        $this->assertSame(['approved', $aytam->id, $this->mushrif->id], [$reg->status, $reg->aytam_id, $reg->reviewer_id]);
        $this->assertSame(['submitted', 'approved'], $reg->events->pluck('type')->all());
        $this->assertTrue(AuditLog::where('action', 'registration.approved')->exists());

        // It cannot be approved twice, and the applicant's link now shows the result.
        $this->postJson($this->base()."/registrations/{$id}/approve")->assertStatus(422)->assertJsonPath('code', 'INVALID_STATE');
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->get($sub['status_url'])->assertOk()->assertSee('Approved')->assertSee('AYT-000001');
    }

    public function test_the_reviewer_can_correct_values_and_validation_problems_block_approval(): void
    {
        $form = $this->makeForm();
        $sub = $this->submitOk($form, ['first_name' => 'Ahmed']);
        $id = $sub['registration']->id;
        $this->actingAs($this->mushrif);

        $this->postJson($this->base()."/registrations/{$id}/approve", ['overrides' => ['aytam.first_name' => '']])->assertStatus(422)->assertJsonValidationErrors('first_name');
        $this->assertSame('pending_review', Registration::find($id)->status);
        $this->assertSame(0, Aytam::count());

        $this->postJson($this->base()."/registrations/{$id}/approve", ['overrides' => ['aytam.first_name' => 'Ahmad', 'aytam.school' => 'Corrected School']])->assertOk();
        $this->assertSame(['Ahmad', 'Corrected School'], [Aytam::first()->first_name, Aytam::first()->school]);
        $this->assertSame('Ahmed', Registration::find($id)->answers['first_name'], 'what the applicant wrote is preserved as submitted');
    }

    public function test_send_back_correct_and_resubmit(): void
    {
        $form = $this->makeForm();
        $sub = $this->submitOk($form);
        $id = $sub['registration']->id;

        $this->actingAs($this->mushrif);
        $this->postJson($this->base()."/registrations/{$id}/send-back", [])->assertStatus(422);
        $this->postJson($this->base()."/registrations/{$id}/send-back", ['note' => 'The birth certificate is blurry. Please upload a clearer scan.'])->assertOk()->assertJsonPath('data.status', 'needs_correction');

        // The applicant's private link now shows what to fix, with their answers filled in.
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $page = $this->get($sub['status_url'])->assertOk();
        $page->assertSee('Please correct your registration')->assertSee('The birth certificate is blurry')->assertSee('value="Ahmad"', false)->assertSee('Already received: birth.pdf');

        $url = parse_url($sub['status_url'], PHP_URL_PATH);
        $payload = ['f' => $this->answers(['school' => 'New School']), 'files' => ['birth_certificate' => UploadedFile::fake()->createWithContent('birth-clear.pdf', "%PDF-1.4\n".str_repeat('c', 3000)."\n%%EOF")]];
        $this->post($url, $payload)->assertRedirect($url);
        $this->get($url)->assertOk()->assertSee('your corrections were sent')->assertSee('Waiting for review');

        $this->inFoundation($this->foundation);
        $reg = Registration::find($id);
        $this->assertSame(['pending_review', 2, null, 'New School'], [$reg->status, $reg->submission_count, $reg->review_note, $reg->answers['school']]);
        $this->assertSame(['submitted', 'returned', 'resubmitted'], $reg->events->pluck('type')->all());
        $this->assertSame('The birth certificate is blurry. Please upload a clearer scan.', $reg->events[1]->note);

        // The new certificate is version 2 of the same slot; the photo (not re-sent) is untouched.
        $certs = Document::where('registration_id', $id)->where('type', 'birth_certificate')->orderBy('doc_version')->get();
        $this->assertSame([[1, false], [2, true]], $certs->map(fn ($d) => [$d->doc_version, $d->is_current])->all());
        $this->assertSame(1, Document::where('registration_id', $id)->where('type', 'photo')->count());

        // A registration that is not waiting for correction cannot be changed through the link.
        auth()->logout();
        $this->app['auth']->forgetGuards();
        $this->post($url, $payload)->assertRedirect($url);
        $this->inFoundation($this->foundation);
        $this->assertSame(2, Registration::find($id)->submission_count);

        $this->actingAs($this->mushrif)->postJson($this->base()."/registrations/{$id}/approve")->assertOk();
        $this->assertSame(3, Document::where('registration_id', $id)->count(), 'history of both certificates is kept');
    }

    public function test_status_links_are_private(): void
    {
        $form = $this->makeForm();
        $sub = $this->submitOk($form);
        auth()->logout();
        $this->app['auth']->forgetGuards();

        $this->get('/apply/status/'.str_repeat('x', 48))->assertStatus(404);
        $this->get('/apply/status/too-short')->assertStatus(404);
        // The reference alone is not enough to see a registration.
        $this->get('/apply/status/'.$sub['reference'])->assertStatus(404);
        $this->get($sub['status_url'])->assertOk()->assertSee($sub['reference'])->assertDontSee('Philippines')->assertDontSee('salma@example.test');
    }

    // ---- duplicates ----

    public function test_a_likely_duplicate_stops_approval_until_a_person_decides(): void
    {
        $existing = $this->createAytam(['first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '2012-04-17']);
        $form = $this->makeForm();
        $sub = $this->submitOk($form);
        $id = $sub['registration']->id;
        $this->actingAs($this->mushrif);

        $detail = $this->getJson($this->base()."/registrations/{$id}")->json('data.duplicates');
        $this->assertSame([$existing, 'exact', 'AYT-000001'], [$detail[0]['aytam_id'], $detail[0]['level'], $detail[0]['aytam_code']]);

        $blocked = $this->postJson($this->base()."/registrations/{$id}/approve")->assertStatus(409)->assertJsonPath('code', 'DUPLICATES_FOUND');
        $this->assertSame($existing, $blocked->json('errors.matches.0.aytam_id'));
        $this->assertSame(1, Aytam::count(), 'nothing is merged or created automatically');
        $this->assertSame('pending_review', Registration::find($id)->status);

        // Use the existing record: no second Aytam; the documents join the existing child.
        $this->postJson($this->base()."/registrations/{$id}/approve", ['duplicate_decision' => 'use_existing'])->assertStatus(422)->assertJsonPath('code', 'EXISTING_REQUIRED');
        $this->postJson($this->base()."/registrations/{$id}/approve", ['duplicate_decision' => 'use_existing', 'existing_aytam_id' => $existing])->assertOk()->assertJsonPath('data.aytam.id', $existing);
        $this->assertSame(1, Aytam::count());
        $this->assertSame(2, Document::where('aytam_id', $existing)->count());
        $this->assertSame('use_existing', Registration::find($id)->duplicate_decision);
    }

    public function test_the_reviewer_may_insist_the_child_is_new(): void
    {
        $this->createAytam(['first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '2012-04-17']);
        $form = $this->makeForm();
        $id = $this->submitOk($form)['registration']->id;

        $this->actingAs($this->mushrif)->postJson($this->base()."/registrations/{$id}/approve", ['duplicate_decision' => 'create_new'])->assertOk()->assertJsonPath('data.aytam.aytam_code', 'AYT-000002');
        $this->assertSame(2, Aytam::count());
        $this->assertSame('create_new', Registration::find($id)->duplicate_decision);
    }

    public function test_an_existing_photo_becomes_a_new_version_when_a_matching_registration_is_attached(): void
    {
        $existing = $this->createAytam(['first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '2012-04-17']);
        $this->post($this->base()."/aytam/{$existing}/documents", ['type' => 'photo', 'file' => UploadedFile::fake()->image('old.jpg', 100, 100)], ['Accept' => 'application/json'])->assertCreated();
        $form = $this->makeForm();
        $id = $this->submitOk($form)['registration']->id;

        $this->actingAs($this->mushrif)->postJson($this->base()."/registrations/{$id}/approve", ['duplicate_decision' => 'use_existing', 'existing_aytam_id' => $existing])->assertOk();
        $photos = Document::where('aytam_id', $existing)->where('type', 'photo')->orderBy('doc_version')->get();
        $this->assertSame([[1, false], [2, true]], $photos->map(fn ($d) => [$d->doc_version, $d->is_current])->all());
        $this->assertSame(1, $photos->pluck('group_id')->unique()->count());
    }

    public function test_different_children_are_not_flagged(): void
    {
        $this->createAytam(['first_name' => 'Fatima', 'last_name' => 'Yusuf', 'date_of_birth' => '2010-01-01']);
        $this->createAytam(['first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '2001-01-01']);   // same name, very different age
        $id = $this->submitOk($this->makeForm())['registration']->id;
        $d = $this->actingAs($this->mushrif)->getJson($this->base()."/registrations/{$id}")->json('data.duplicates');
        $this->assertSame([], array_values(array_filter($d, fn ($m) => $m['level'] !== 'possible')), 'same name but a different birth date is at most a "possible" match');
        $this->postJson($this->base()."/registrations/{$id}/approve", ['duplicate_decision' => 'create_new'])->assertOk();
    }

    // ---- isolation ----

    public function test_registrations_belong_to_their_foundation_and_program(): void
    {
        $form = $this->makeForm();
        $sub = $this->submitOk($form);
        [, $foreign] = $this->createFoundation('Other', 'other@example.test');

        $this->actingAs($foreign)->getJson($this->base().'/registrations')->assertStatus(404);
        $this->getJson($this->base().'/registrations/'.$sub['registration']->id)->assertStatus(404);
        $this->postJson($this->base().'/registrations/'.$sub['registration']->id.'/approve')->assertStatus(404);
        $this->inFoundation($this->foundation);
        $this->assertSame(1, Registration::count());
        $this->inFoundation(\App\Models\Foundation::withoutGlobalScopes()->where('name', 'Other')->first());
        $this->assertSame(0, Registration::count());
        $this->assertSame(0, Document::count());
    }
}
