<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Document;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BootsAytam;
use Tests\TestCase;

class DocumentsTest extends TestCase
{
    use BootsAytam;

    private string $aytam;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAytam();
        Storage::disk('local')->deleteDirectory('documents');
        $this->aytam = $this->createAytam();
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('documents');
        parent::tearDown();
    }

    private function pdf(string $name = 'passport.pdf', int $kb = 4): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\n".str_repeat('x', $kb * 1024)."\n%%EOF");
    }

    private function upload(array $extra = [], ?UploadedFile $file = null, ?string $id = null)
    {
        return $this->post($this->base().'/aytam/'.($id ?? $this->aytam).'/documents', $extra + ['type' => 'passport', 'file' => $file ?? $this->pdf()], ['Accept' => 'application/json']);
    }

    public function test_a_valid_document_is_stored_privately_with_metadata(): void
    {
        $this->actingAs($this->mushrif);
        $r = $this->upload(['expires_on' => now()->addYear()->toDateString(), 'notes' => 'Renewed 2026'])->assertCreated();

        $this->assertSame(['passport', 1, true, 'pending', 'application/pdf'], [$r->json('data.type'), $r->json('data.doc_version'), $r->json('data.is_current'), $r->json('data.status'), $r->json('data.mime')]);
        $doc = Document::find($r->json('data.id'));
        $this->assertMatchesRegularExpression('#^documents/'.$this->foundation->id.'/'.$this->program->id.'/[a-z0-9]{40}\.pdf$#', $doc->storage_path);
        Storage::disk('local')->assertExists($doc->storage_path);
        $this->assertSame($this->mushrif->id, $doc->created_by);
        $this->assertSame(64, strlen($doc->sha256));
        $this->assertArrayNotHasKey('storage_path', $r->json('data'), 'the storage path never leaves the server');
        $this->assertStringNotContainsString($doc->storage_path, $this->getJson($this->base().'/aytam/'.$this->aytam.'/documents')->getContent());
        $this->assertStringNotContainsString($doc->storage_path, json_encode(\App\Models\SyncChange::where('entity_id', $doc->id)->get()->toArray()));
        $this->assertTrue(AuditLog::where('action', 'document.created')->where('subject_id', $doc->id)->exists());
    }

    public function test_dangerous_and_mislabelled_files_are_refused_and_nothing_is_stored(): void
    {
        $this->actingAs($this->mushrif);
        $bad = [
            'php script as pdf' => UploadedFile::fake()->createWithContent('passport.pdf', '<?php system($_GET["c"]); ?>'),
            'php script as jpg' => UploadedFile::fake()->createWithContent('photo.jpg', "GIF89a<?php system('id'); ?>"),
            'executable' => UploadedFile::fake()->createWithContent('setup.exe', "MZ\x90\x00\x03\x00\x00\x00"),
            'shell script' => UploadedFile::fake()->createWithContent('run.sh', "#!/bin/sh\nrm -rf /\n"),
            'html' => UploadedFile::fake()->createWithContent('page.html', '<html><script>alert(1)</script></html>'),
            'svg' => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            'png named pdf' => UploadedFile::fake()->image('passport.pdf'),
            'pdf named png' => $this->pdf('scan.png'),
            'double extension' => UploadedFile::fake()->createWithContent('passport.pdf.php', "%PDF-1.4\n"),
            'empty' => UploadedFile::fake()->createWithContent('empty.pdf', ''),
            'truncated image' => UploadedFile::fake()->createWithContent('photo.png', substr(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='), 0, 40)),
        ];
        foreach ($bad as $why => $file) {
            $this->upload(['type' => 'other'], $file)->assertStatus(422);
        }
        $this->upload(['type' => 'other'], $this->pdf('big.pdf', kb: 11 * 1024))->assertStatus(422)->assertJsonPath('errors.file.0', 'The file is larger than 10 MB.');
        $this->assertSame([], Storage::disk('local')->allFiles('documents'), 'rejected uploads leave nothing on disk');
        $this->assertSame(0, Document::count());

        $this->upload(['type' => 'spaceship'])->assertStatus(422);
        $this->upload(['expires_on' => '2001-01-01'])->assertStatus(422);
        $this->upload(['type' => 'photo'], $this->pdf())->assertStatus(422);   // a photo must be an image
        $this->upload(['type' => 'photo'], UploadedFile::fake()->image('me.jpg', 300, 300))->assertCreated();
        $this->post($this->base().'/aytam/'.$this->aytam.'/documents', ['type' => 'passport'], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_file_names_are_sanitised(): void
    {
        $this->actingAs($this->mushrif);
        $r = $this->upload([], $this->pdf('../../etc/<b>pass"port</b>;rm -rf.pdf'))->assertCreated();
        $name = $r->json('data.original_name');
        $this->assertStringEndsWith('.pdf', $name);
        $this->assertDoesNotMatchRegularExpression('#[/\\\\<>";]#', $name);
        $this->assertStringNotContainsString('..', $name);
    }

    public function test_a_replacement_becomes_a_new_version_and_history_is_kept(): void
    {
        $this->actingAs($this->mushrif);
        $v1 = $this->upload()->json('data');
        $v2 = $this->upload(['replaces' => $v1['id']], $this->pdf('renewed.pdf', 6))->assertCreated()->json('data');
        $v3 = $this->upload(['replaces' => $v2['id']], $this->pdf('renewed-again.pdf', 8))->assertCreated()->json('data');

        $this->assertSame([2, 3], [$v2['doc_version'], $v3['doc_version']]);
        $this->assertSame($v1['group_id'], $v3['group_id']);
        $this->assertSame([false, false, true], [Document::find($v1['id'])->is_current, Document::find($v2['id'])->is_current, Document::find($v3['id'])->is_current]);
        $current = $this->getJson($this->base().'/aytam/'.$this->aytam.'/documents')->json('data.current');
        $this->assertCount(1, $current);
        $this->assertSame([$v3['id'], 3], [$current[0]['id'], $current[0]['versions']]);

        $history = $this->getJson($this->base().'/documents/'.$v3['id'].'/history')->assertOk()->json('data');
        $this->assertSame([3, 2, 1], array_column($history, 'doc_version'));
        // Old versions remain downloadable.
        $this->get($this->base().'/documents/'.$v1['id'].'/download')->assertOk();
        Storage::disk('local')->assertExists(Document::find($v1['id'])->storage_path);

        // A new version cannot change the kind of document, nor replace a superseded one, nor another record's.
        $this->upload(['replaces' => $v3['id'], 'type' => 'diploma'])->assertStatus(422)->assertJsonPath('code', 'TYPE_MISMATCH');
        $this->upload(['replaces' => $v1['id']])->assertStatus(404);
        $other = $this->createAytam(['first_name' => 'Other']);
        $this->upload(['replaces' => $v3['id']], null, $other)->assertStatus(404);
    }

    public function test_a_new_photo_replaces_the_current_one_automatically(): void
    {
        $this->actingAs($this->mushrif);
        $p1 = $this->upload(['type' => 'photo'], UploadedFile::fake()->image('a.jpg', 200, 200))->assertCreated()->json('data');
        $p2 = $this->upload(['type' => 'photo'], UploadedFile::fake()->image('b.png', 220, 220))->assertCreated()->json('data');
        $this->assertSame([$p1['group_id'], 2], [$p2['group_id'], $p2['doc_version']]);
        $this->assertSame(1, Document::where('type', 'photo')->where('is_current', true)->count());
    }

    public function test_verification_rejection_and_expiry(): void
    {
        $this->actingAs($this->mushrif);
        $id = $this->upload(['expires_on' => now()->addDay()->toDateString()])->json('data.id');

        $this->postJson($this->base()."/documents/{$id}/decision", ['decision' => 'rejected'])->assertStatus(422)->assertJsonPath('code', 'REASON_REQUIRED');
        $this->postJson($this->base()."/documents/{$id}/decision", ['decision' => 'rejected', 'reason' => 'Blurry'])->assertOk()
            ->assertJsonPath('data.status', 'rejected')->assertJsonPath('data.rejection_reason', 'Blurry');
        $this->postJson($this->base()."/documents/{$id}/decision", ['decision' => 'verified'])->assertOk()->assertJsonPath('data.status', 'verified')->assertJsonPath('data.rejection_reason', null);
        $doc = Document::find($id);
        $this->assertSame([$this->mushrif->id, true], [$doc->verified_by, $doc->verified_at !== null]);

        // A verified document whose expiry date has passed reads as expired, with no background job needed.
        $doc->update(['expires_on' => now()->subDay()->toDateString()]);
        $this->getJson($this->base().'/aytam/'.$this->aytam.'/documents')->assertJsonPath('data.current.0.status', 'expired');
        $this->postJson($this->base()."/documents/{$id}/decision", ['decision' => 'pending'])->assertStatus(422);

        // Only people with documents.verify decide; a superseded version cannot be decided.
        $this->actingAs($this->admin)->putJson($this->base()."/aytam/{$this->aytam}/assignments", ['user_ids' => [$this->worker->id]])->assertOk();
        $this->actingAs($this->worker)->postJson($this->base()."/documents/{$id}/decision", ['decision' => 'verified'])->assertStatus(403);
        $this->actingAs($this->mushrif);
        $newer = $this->upload(['replaces' => $id])->json('data.id');
        $this->postJson($this->base()."/documents/{$id}/decision", ['decision' => 'verified'])->assertStatus(422)->assertJsonPath('code', 'SUPERSEDED');
        $this->postJson($this->base()."/documents/{$newer}/decision", ['decision' => 'verified'])->assertOk();
        $this->assertTrue(AuditLog::where('action', 'document.verified')->exists() && AuditLog::where('action', 'document.rejected')->exists());
    }

    public function test_downloads_are_authorised_and_served_safely(): void
    {
        $this->actingAs($this->mushrif);
        $id = $this->upload([], $this->pdf('Birth cert.pdf'))->json('data.id');
        $png = $this->upload(['type' => 'photo'], UploadedFile::fake()->image('me.png', 100, 100))->json('data.id');

        $r = $this->get($this->base()."/documents/{$id}/download")->assertOk();
        $this->assertStringStartsWith('attachment;', $r->headers->get('Content-Disposition'));
        $this->assertSame('nosniff', $r->headers->get('X-Content-Type-Options'));
        $this->assertStringContainsString('sandbox', $r->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->assertSame('application/pdf', $r->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $r->streamedContent());

        $this->assertStringStartsWith('inline;', $this->get($this->base()."/documents/{$png}/download?inline=1")->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('attachment;', $this->get($this->base()."/documents/{$id}/download?inline=1")->headers->get('Content-Disposition'), 'only images are ever shown inline');

        // An unassigned field worker and another foundation get "not found"; someone with no role in the program is simply refused.
        $this->actingAs($this->worker)->get($this->base()."/documents/{$id}/download")->assertStatus(404);
        $this->actingAs($this->outsider)->get($this->base()."/documents/{$id}/download")->assertStatus(403);   // foundation staff with no role in this program
        [, $foreign] = $this->createFoundation('Other', 'other@example.test');
        $this->actingAs($foreign)->get($this->base()."/documents/{$id}/download")->assertStatus(404);
        auth()->logout();
        $this->flushSession();
        $this->app['auth']->forgetGuards();
        $this->get($this->base()."/documents/{$id}/download")->assertStatus(401);

        // Once assigned, the field worker can read and upload - but not verify.
        $this->actingAs($this->mushrif)->putJson($this->base()."/aytam/{$this->aytam}/assignments", ['user_ids' => [$this->worker->id]])->assertOk();
        $this->actingAs($this->worker)->get($this->base()."/documents/{$id}/download")->assertOk();
        $this->upload(['type' => 'diploma'])->assertCreated();
        $this->postJson($this->base()."/documents/{$id}/decision", ['decision' => 'verified'])->assertStatus(403);
    }

    public function test_storage_is_not_reachable_from_the_web(): void
    {
        $this->actingAs($this->mushrif);
        $doc = Document::find($this->upload()->json('data.id'));
        $this->assertStringStartsWith(storage_path('app/private'), Storage::disk('local')->path($doc->storage_path));
        $this->assertFalse(str_starts_with(Storage::disk('local')->path($doc->storage_path), public_path()));
        foreach (['/storage/'.$doc->storage_path, '/'.$doc->storage_path, '/documents/'.basename($doc->storage_path)] as $url) {
            $this->get($url)->assertDontSee('%PDF-');
        }
    }

    public function test_required_documents_show_as_missing_until_provided(): void
    {
        $this->actingAs($this->mushrif);
        $missing = fn () => collect($this->getJson($this->base().'/aytam/'.$this->aytam)->json('data.required_documents'))->where('missing', true)->pluck('type')->all();
        $this->assertEqualsCanonicalizing(['photo', 'birth_certificate'], $missing());

        $photo = $this->upload(['type' => 'photo'], UploadedFile::fake()->image('me.jpg', 100, 100))->json('data.id');
        $this->assertSame(['birth_certificate'], $missing());
        $cert = $this->upload(['type' => 'birth_certificate'])->json('data.id');
        $this->assertSame([], $missing());
        $this->assertSame(0, $this->getJson($this->base().'/aytam-dashboard')->json('data.missing_documents'));

        // A rejected document does not count.
        $this->postJson($this->base()."/documents/{$cert}/decision", ['decision' => 'rejected', 'reason' => 'Wrong person'])->assertOk();
        $this->assertSame(['birth_certificate'], $missing());
        $this->assertSame(1, $this->getJson($this->base().'/aytam-dashboard')->json('data.missing_documents'));
    }

    public function test_other_foundations_documents_are_unreachable(): void
    {
        $this->actingAs($this->mushrif);
        $id = $this->upload()->json('data.id');
        $this->assertSame(1, Document::count());
        [$other] = $this->createFoundation('Other', 'other@example.test');
        $this->inFoundation($other);
        $this->assertSame(0, Document::count());
        $this->inFoundation($this->foundation);
        $this->assertNotNull(Document::find($id));
    }
}
