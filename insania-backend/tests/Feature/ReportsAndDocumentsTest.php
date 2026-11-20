<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class ReportsAndDocumentsTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_the_report_summary_matches_the_payment_list(): void
    {
        $this->world();
        $this->actingAsRole('staff:finance');

        $this->getJson('/api/reports/summary?month=2026-11')->assertOk()->assertJsonPath('data.paid_count', 8)->assertJsonPath('data.paid_total', 1500)->assertJsonPath('data.due_total', 600);
        $this->getJson('/api/reports/summary?month=2026-11&scope=cash')->assertJsonPath('data.children', 4)->assertJsonPath('data.paid_total', 400)->assertJsonPath('data.due_total', 300);
        $this->getJson('/api/reports/summary?month=bad')->assertStatus(422);
    }

    public function test_the_csv_export_is_a_proper_file_and_is_audited(): void
    {
        $this->world();
        $this->actingAsRole('staff:finance');

        $res = $this->get('/api/reports/payments.csv?month=2026-11&scope=cash')->assertOk();
        $body = $res->getContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF\"ref\",\"name_en\",\"method\",\"status\",\"amount\"", $body);
        $this->assertCount(5 + 1, explode("\r\n", $body), 'header + 4 cash children + trailing newline');
        $this->assertStringContainsString('text/csv', $res->headers->get('Content-Type'));
        $this->assertStringContainsString('payments-2026-11.csv', $res->headers->get('Content-Disposition'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.exported']);
    }

    public function test_a_hostile_name_cannot_become_a_spreadsheet_formula(): void
    {
        $this->world();
        DB::table('children')->where('ref', 'ACF-0003')->update(['name_en' => '=HYPERLINK("http://evil.test","click")']);
        $this->actingAsRole('staff:finance');

        $body = $this->get('/api/reports/payments.csv?month=2026-11')->getContent();

        $this->assertStringContainsString('"\'=HYPERLINK(', $body);
        $this->assertStringNotContainsString(',"=HYPERLINK', $body);
    }

    public function test_a_valid_document_is_stored_privately_and_replaces_the_old_one(): void
    {
        $this->world();
        Storage::fake('local');
        $this->actingAsRole('staff:aytam');
        $id = $this->childId('ACF-0001');

        $first = $this->postJson("/api/children/$id/documents", ['type' => 'birth_certificate', 'file' => UploadedFile::fake()->create('birth.pdf', 120, 'application/pdf')])->assertCreated();
        $this->assertArrayNotHasKey('path', $first->json('data'));
        $path1 = DB::table('child_documents')->where('child_id', $id)->where('type', 'birth_certificate')->value('path');
        Storage::disk('local')->assertExists($path1);
        $this->assertStringNotContainsString('birth.pdf', $path1, 'stored under a random name, never the uploaded one');

        $this->postJson("/api/children/$id/documents", ['type' => 'birth_certificate', 'file' => UploadedFile::fake()->image('scan.png')])->assertCreated();
        Storage::disk('local')->assertMissing($path1);
        $this->assertSame(1, DB::table('child_documents')->where('child_id', $id)->count());

        $listed = collect($this->getJson("/api/children/$id/documents")->json('data'))->firstWhere('type', 'birth_certificate');
        $this->assertTrue($listed['on_file']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.uploaded', 'subject_id' => $id]);
    }

    public function test_downloads_are_audited_and_missing_documents_are_404(): void
    {
        $this->world();
        Storage::fake('local');
        $this->actingAsRole('president_admin');
        $id = $this->childId('ACF-0001');
        $this->postJson("/api/children/$id/documents", ['type' => 'school_record', 'file' => UploadedFile::fake()->create('r.pdf', 50, 'application/pdf')])->assertCreated();

        $this->get("/api/children/$id/documents/school_record/download")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertDatabaseHas('audit_logs', ['action' => 'document.downloaded', 'subject_id' => $id]);
        $this->getJson("/api/children/$id/documents/guardian_id/download")->assertNotFound();
    }

    public function test_dangerous_or_wrong_files_are_refused(): void
    {
        $this->world();
        Storage::fake('local');
        $this->actingAsRole('staff:aytam');
        $id = $this->childId('ACF-0001');
        $url = "/api/children/$id/documents";

        foreach (['shell.php', 'shell.php.jpg', 'page.html', 'logo.svg', '.htaccess', 'run.exe'] as $name) {
            $this->postJson($url, ['type' => 'school_record', 'file' => UploadedFile::fake()->create($name, 10, 'application/octet-stream')])
                ->assertStatus(422)->assertJsonPath('code', 'UNSAFE_FILE');
        }

        $this->postJson($url, ['type' => 'school_record', 'file' => UploadedFile::fake()->create('big.pdf', 6000, 'application/pdf')])->assertStatus(422);
        $this->postJson($url, ['type' => 'school_record', 'file' => UploadedFile::fake()->create('notes.txt', 10, 'text/plain')])->assertStatus(422);
        $this->postJson($url, ['type' => 'passport', 'file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')])->assertStatus(422);
        $this->postJson($url, ['type' => 'school_record'])->assertStatus(422);

        $this->assertSame(0, DB::table('child_documents')->count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
}
