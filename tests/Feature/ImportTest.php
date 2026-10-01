<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Aytam;
use App\Models\Family;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Tests\Concerns\BootsAytam;
use Tests\TestCase;

class ImportTest extends TestCase
{
    use BootsAytam;

    private const HEADER = ['ID', 'First Name', 'Middle Name', 'Last Name', 'Date of Birth', 'Sex', 'City', 'School', 'Grade', 'Father', 'Mother', 'Guardian', 'Guardian Phone', 'Email'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootAytam();
        Storage::disk('local')->deleteDirectory('imports');
    }

    protected function tearDown(): void
    {
        Storage::disk('local')->deleteDirectory('imports');
        parent::tearDown();
    }

    private function csv(array $rows, array $header = self::HEADER, string $delimiter = ',', string $name = 'children.csv'): UploadedFile
    {
        $lines = array_map(fn ($r) => implode($delimiter, array_map(fn ($c) => '"'.str_replace('"', '""', (string) $c).'"', $r)), array_merge([$header], $rows));

        return UploadedFile::fake()->createWithContent($name, "\xEF\xBB\xBF".implode("\r\n", $lines)."\r\n");
    }

    private function xlsx(array $rows, array $header = self::HEADER): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'imp').'.xlsx';
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($header));
        $dateStyle = (new \OpenSpout\Common\Entity\Style\Style)->withFormat('yyyy-mm-dd');
        foreach ($rows as $r) {
            // Real Excel date cells carry a date number format - that is what makes a reader treat them as dates.
            $writer->addRow(new Row(array_map(fn ($v) => \OpenSpout\Common\Entity\Cell::fromValue($v, $v instanceof \DateTimeInterface ? $dateStyle : null), $r)));
        }
        $writer->close();

        return new UploadedFile($path, 'children.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function rows(): array
    {
        return [
            ['L-001', 'Ahmad', 'bin', 'Abdullah', '2012-04-17', 'M', 'Cotabato City', 'Al-Noor School', '5', 'Abdullah', 'Maryam', 'Aunt Salma', '0917 111 1111', 'a@example.test'],
            ['L-002', 'Bilal', '', 'Abdullah', '2014-02-02', 'Male', 'Cotabato City', 'Al-Noor School', '3', 'Abdullah', 'Maryam', 'Aunt Salma', '0917 111 1111', ''],
            ['L-003', 'Fatima', '', 'Yusuf', '2010-09-30', 'F', 'Marawi', 'Dar School', '7', 'Yusuf', 'Aisha', 'Uncle Omar', '0918 222 2222', ''],
        ];
    }

    private function upload(UploadedFile $file)
    {
        return $this->actingAs($this->mushrif)->post($this->base().'/imports', ['file' => $file], ['Accept' => 'application/json']);
    }

    private function stage(?UploadedFile $file = null, array $mapOverride = [], string $dateFormat = 'iso'): string
    {
        $batch = $this->upload($file ?? $this->csv($this->rows()))->assertCreated()->json('data');
        $mapping = $mapOverride + (array) $batch['suggested_mapping'];
        $this->putJson($this->base()."/imports/{$batch['id']}/mapping", ['mapping' => $mapping, 'date_format' => $dateFormat])->assertOk();

        return $batch['id'];
    }

    private function importAll(string $id, string $status = 'active'): array
    {
        $this->postJson($this->base()."/imports/{$id}/validate")->assertOk();

        return $this->postJson($this->base()."/imports/{$id}/commit", ['status' => $status])->assertOk()->json('data');
    }

    // ---- the happy path ----

    public function test_csv_upload_suggests_a_mapping_validates_and_imports_with_families_and_guardians_shared(): void
    {
        $batch = $this->upload($this->csv($this->rows()))->assertCreated()->json('data');
        $this->assertSame(['csv', 3, 'uploaded'], [$batch['file_type'], $batch['row_count'], $batch['status']]);
        $this->assertSame(self::HEADER, $batch['headers']);
        $this->assertSame('L-001', $batch['sample'][0][0]);
        $this->assertSame(['aytam.legacy_ref', 'aytam.first_name', 'aytam.middle_name', 'aytam.last_name', 'aytam.date_of_birth', 'aytam.gender', 'aytam.city', 'aytam.school', 'aytam.grade', 'family.father_name', 'family.mother_name', 'guardian.full_name', 'guardian.phone', 'aytam.email'], array_values((array) $batch['suggested_mapping']));
        $this->assertContains('family.father_status', array_column($batch['targets'], 'key'));
        $this->assertNotContains('aytam.photo', array_column($batch['targets'], 'key'));
        $this->assertSame(['iso', 'dmy', 'mdy'], array_keys($batch['date_formats']));

        $id = $batch['id'];
        $this->putJson($this->base()."/imports/{$id}/mapping", ['mapping' => (array) $batch['suggested_mapping'], 'date_format' => 'iso'])->assertOk()->assertJsonPath('data.status', 'mapped');
        $v = $this->postJson($this->base()."/imports/{$id}/validate")->assertOk()->json('data');
        $this->assertSame([3, 0, 0, 3], [$v['valid'], $v['invalid'], $v['duplicate'], $v['total']]);
        $this->assertSame(0, Aytam::count(), 'validation creates nothing');

        $r = $this->postJson($this->base()."/imports/{$id}/commit", ['status' => 'active'])->assertOk()->json('data');
        $this->assertSame([3, 0, 0, true], [$r['created'], $r['remaining'], $r['skipped'], $r['finished']]);

        $children = Aytam::orderBy('aytam_code')->get();
        $this->assertSame(['AYT-000001', 'AYT-000002', 'AYT-000003'], $children->pluck('aytam_code')->all());
        $this->assertSame(['L-001', 'import', 'active', 'male', 'bin'], [$children[0]->legacy_ref, $children[0]->source, $children[0]->status, $children[0]->gender, $children[0]->middle_name]);
        $this->assertSame('female', $children[2]->gender);
        $this->assertSame('2012-04-17', $children[0]->date_of_birth->toDateString());

        $this->assertSame(2, Family::count(), 'the two brothers share one family');
        $this->assertSame(2, Guardian::count(), 'and one guardian');
        $this->assertSame($children[0]->family_id, $children[1]->family_id);
        $this->assertNotSame($children[0]->family_id, $children[2]->family_id);
        $this->assertSame('Abdullah family', Family::find($children[0]->family_id)->name);
        $this->assertSame('Aunt Salma', Guardian::find(Family::find($children[0]->family_id)->guardian_id)->full_name);
        $this->assertNull($children[0]->guardian_id, 'the guardian is inherited from the family, not copied onto each child');

        // Housekeeping and accountability.
        $batchModel = ImportBatch::find($id);
        $this->assertSame(['imported', 3], [$batchModel->status, $batchModel->summary['imported']]);
        Storage::disk('local')->assertMissing($batchModel->storage_path);
        $this->assertSame([[]], ImportRow::where('batch_id', $id)->pluck('raw')->unique()->values()->all(), 'the uploaded personal data is purged once imported');
        $this->assertTrue(AuditLog::where('action', 'import.performed')->exists());
        $this->assertSame(3, AuditLog::where('action', 'aytam.created')->count());
        $this->postJson($this->base()."/imports/{$id}/commit")->assertStatus(422)->assertJsonPath('code', 'NOT_VALIDATED');
        $this->putJson($this->base()."/imports/{$id}/mapping", ['mapping' => (array) $batch['suggested_mapping'], 'date_format' => 'iso'])->assertStatus(422)->assertJsonPath('code', 'CLOSED');
    }

    public function test_excel_files_import_including_real_date_cells_and_numeric_cells(): void
    {
        $rows = [
            ['L-1', 'Ahmad', '', 'Abdullah', new \DateTimeImmutable('2012-04-17'), 'M', 'Cotabato City', 'School', 5, 'Abdullah', 'Maryam', 'Aunt Salma', 9171111111, ''],
            ['L-2', 'Ali', '', 'Yusuf', new \DateTimeImmutable('2009-12-01'), 'M', 'Marawi', 'School', 8.0, '', '', '', '', ''],
        ];
        $batch = $this->upload($this->xlsx($rows))->assertCreated()->json('data');
        $this->assertSame(['xlsx', 2], [$batch['file_type'], $batch['row_count']]);
        $this->assertSame(['L-1', 'Ahmad', '', 'Abdullah', '2012-04-17', 'M'], array_slice($batch['sample'][0], 0, 6), 'real date cells arrive as ISO dates');

        $id = $batch['id'];
        $this->putJson($this->base()."/imports/{$id}/mapping", ['mapping' => (array) $batch['suggested_mapping'], 'date_format' => 'dmy'])->assertOk();   // an ISO value is unambiguous whatever the chosen format
        $this->postJson($this->base()."/imports/{$id}/validate")->assertJsonPath('data.valid', 2);
        $this->postJson($this->base()."/imports/{$id}/commit")->assertOk()->assertJsonPath('data.created', 2);
        $this->assertSame('9171111111', Guardian::first()->phone, 'numeric cells become text');
        $this->assertSame('8', Aytam::where('legacy_ref', 'L-2')->first()->grade);
    }

    public function test_odd_csv_files_still_read(): void
    {
        // Semicolon-separated (how many European and Filipino spreadsheet setups save CSV), and Windows-1252 text.
        $header = ['First Name', 'Last Name', 'Date of Birth', 'Sex'];
        $rows = [['José', 'Muñoz', '2011-05-05', 'M']];
        $lines = array_map(fn ($r) => implode(';', $r), array_merge([$header], $rows));
        $file = UploadedFile::fake()->createWithContent('latin.csv', mb_convert_encoding(implode("\n", $lines), 'Windows-1252', 'UTF-8'));
        $id = $this->stage($file);
        $this->importAll($id);
        $this->assertSame(['José', 'Muñoz'], [Aytam::first()->first_name, Aytam::first()->last_name]);
    }

    // ---- errors are shown before anything is created ----

    public function test_errors_are_reported_per_row_and_bad_rows_are_never_imported(): void
    {
        $rows = $this->rows();
        $rows[] = ['L-004', 'Zaid', '', '', '2011-01-01', 'M', 'X', '', '', '', '', '', '', ''];              // no last name
        $rows[] = ['L-005', 'Omar', '', 'Hassan', '17/04/2012', 'M', 'X', '', '', '', '', '', '', ''];       // wrong date format
        $rows[] = ['L-006', 'Hadi', '', 'Karim', '2011-02-30', 'M', 'X', '', '', '', '', '', '', ''];        // not a real date
        $rows[] = ['L-007', 'Nur', '', 'Karim', '2011-02-03', 'robot', 'X', '', '', '', '', '', '', ''];     // gender
        $rows[] = ['L-008', 'Sami', '', 'Karim', '2999-01-01', 'M', 'X', '', '', '', '', '', '', 'not-an-email'];
        $id = $this->stage($this->csv($rows));

        $v = $this->postJson($this->base()."/imports/{$id}/validate")->assertOk()->json('data');
        $this->assertSame([3, 5], [$v['valid'], $v['invalid']]);

        $bad = $this->getJson($this->base()."/imports/{$id}/rows?status=invalid")->assertOk();
        $byRow = collect($bad->json('data'))->keyBy('row_number');
        $this->assertSame([4, 5, 6, 7, 8], $byRow->keys()->all());
        $this->assertArrayHasKey('aytam.last_name', (array) $byRow[4]['errors']);
        $this->assertStringContainsString('not a date in the chosen format', $byRow[5]['errors']['aytam.date_of_birth'][0]);
        $this->assertStringContainsString('not a real date', $byRow[6]['errors']['aytam.date_of_birth'][0]);
        $this->assertStringContainsString('not a recognised gender', $byRow[7]['errors']['aytam.gender'][0]);
        $this->assertArrayHasKey('aytam.email', (array) $byRow[8]['errors']);
        $this->assertArrayHasKey('aytam.date_of_birth', (array) $byRow[8]['errors']);
        $this->assertSame('Zaid', $byRow[4]['cells'][1], 'the original cells are shown so the file can be fixed');

        $this->assertSame(0, Aytam::count());
        $this->postJson($this->base()."/imports/{$id}/commit")->assertOk()->assertJsonPath('data.created', 3)->assertJsonPath('data.finished', true);
        $this->assertSame(3, Aytam::count(), 'only the good rows are imported');
        $this->assertSame(5, ImportBatch::find($id)->summary['invalid']);
    }

    public function test_dates_are_never_guessed(): void
    {
        $rows = [['L-1', 'Ahmad', '', 'Abdullah', '04/05/2012', 'M', '', '', '', '', '', '', '', '']];
        $month = fn (string $format) => (function () use ($rows, $format) {
            Aytam::query()->forceDelete();
            $id = $this->stage($this->csv($rows), dateFormat: $format);
            $this->importAll($id);

            return Aytam::first()?->date_of_birth?->toDateString();
        })();
        $this->assertSame('2012-05-04', $month('dmy'));
        $this->assertSame('2012-04-05', $month('mdy'));

        $id = $this->stage($this->csv($rows), dateFormat: 'iso');
        $this->postJson($this->base()."/imports/{$id}/validate")->assertJsonPath('data.invalid', 1);
    }

    // ---- duplicates ----

    public function test_possible_duplicates_are_held_until_a_person_decides(): void
    {
        $existing = $this->createAytam(['first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '2012-04-17']);
        $rows = $this->rows();
        $rows[] = ['L-009', 'Ahmad', '', 'Abdullah', '2012-04-17', 'M', '', '', '', '', '', '', '', ''];   // same as row 1 AND the existing record
        $id = $this->stage($this->csv($rows));

        $v = $this->postJson($this->base()."/imports/{$id}/validate")->assertOk()->json('data');
        $this->assertSame(2, $v['duplicate'], 'rows 1 and 4 both look like the record already on file');
        $dupes = collect($this->getJson($this->base()."/imports/{$id}/rows?status=duplicate")->json('data'))->keyBy('row_number');
        $this->assertSame($existing, $dupes[1]['matches'][0]['aytam_id']);
        $this->assertSame('existing', $dupes[1]['matches'][0]['type']);
        $this->assertSame('probable', $dupes[1]['matches'][0]['level'], 'same birth date and one name contained in the other');
        $this->assertContains('file', array_column($dupes[4]['matches'], 'type'), 'row 4 is also a copy of row 1 inside the file');

        // Nothing is merged or created for uncertain rows; the clear rows go through.
        $r = $this->postJson($this->base()."/imports/{$id}/commit")->assertOk()->json('data');
        $this->assertSame([2, 0, true], [$r['created'], $r['linked'], $r['finished']]);
        $this->assertSame(3, Aytam::count());
        $this->assertSame(2, ImportBatch::find($id)->summary['held'], 'held rows are reported, not silently dropped');
    }

    public function test_each_duplicate_gets_a_decision_use_existing_create_new_or_skip(): void
    {
        $existing = $this->createAytam(['first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '2012-04-17']);
        $rows = [
            ['L-1', 'Ahmad', '', 'Abdullah', '2012-04-17', 'M', '', '', '', '', '', '', '', ''],
            ['L-2', 'Ahmad', '', 'Abdullah', '2012-04-17', 'M', '', '', '', '', '', '', '', ''],
            ['L-3', 'Ahmad', '', 'Abdullah', '2012-04-17', 'M', '', '', '', '', '', '', '', ''],
            ['L-4', 'Fresh', '', 'Child', '2013-03-03', 'M', '', '', '', '', '', '', '', ''],
        ];
        $id = $this->stage($this->csv($rows));
        $this->postJson($this->base()."/imports/{$id}/validate")->assertOk();
        $row = fn (int $n) => ImportRow::where('batch_id', $id)->where('row_number', $n)->first();

        // A clear row can only be skipped; a decision needs the right extra information.
        $this->postJson($this->base()."/imports/{$id}/rows/{$row(4)->id}/decision", ['decision' => 'create_new'])->assertStatus(422)->assertJsonPath('code', 'INVALID_DECISION');
        $this->postJson($this->base()."/imports/{$id}/rows/{$row(1)->id}/decision", ['decision' => 'use_existing'])->assertStatus(422)->assertJsonPath('code', 'EXISTING_REQUIRED');
        $this->postJson($this->base()."/imports/{$id}/rows/{$row(1)->id}/decision", ['decision' => 'use_existing', 'existing_aytam_id' => (string) \Illuminate\Support\Str::uuid()])->assertStatus(422);
        $this->postJson($this->base()."/imports/{$id}/rows/{$row(1)->id}/decision", ['decision' => 'use_existing', 'existing_aytam_id' => $existing])->assertOk();
        $this->postJson($this->base()."/imports/{$id}/rows/{$row(2)->id}/decision", ['decision' => 'create_new'])->assertOk();
        $this->postJson($this->base()."/imports/{$id}/rows/{$row(3)->id}/decision", ['decision' => 'skip'])->assertOk();
        $this->postJson($this->base()."/imports/{$id}/rows/{$row(3)->id}/decision", ['decision' => 'maybe'])->assertStatus(422);

        $r = $this->postJson($this->base()."/imports/{$id}/commit")->assertOk()->json('data');
        $this->assertSame([2, 1, 1, true], [$r['created'], $r['linked'], $r['skipped'], $r['finished']]);
        $this->assertSame(3, Aytam::count(), 'the existing child, one genuine new one, and the fresh child');
        $this->assertSame($existing, $row(1)->aytam_id);
        $this->assertNotNull($row(2)->aytam_id);
        $this->assertNotSame($existing, $row(2)->aytam_id);
        $this->assertSame('skipped', $row(3)->status);
        $this->assertSame(1, ImportBatch::find($id)->summary['linked_to_existing']);
    }

    public function test_a_decision_can_be_applied_to_all_undecided_duplicates(): void
    {
        $this->createAytam(['first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '2012-04-17']);
        $rows = array_fill(0, 3, ['L', 'Ahmad', '', 'Abdullah', '2012-04-17', 'M', '', '', '', '', '', '', '', '']);
        $id = $this->stage($this->csv($rows));
        $this->postJson($this->base()."/imports/{$id}/validate")->assertJsonPath('data.duplicate', 3);

        $this->postJson($this->base()."/imports/{$id}/decisions", ['decision' => 'use_existing'])->assertStatus(422);
        $this->postJson($this->base()."/imports/{$id}/decisions", ['decision' => 'skip'])->assertOk()->assertJsonPath('data.updated', 3);
        $this->assertSame(0, $this->getJson($this->base()."/imports/{$id}/rows?undecided=1")->json('meta.total'));
        $this->postJson($this->base()."/imports/{$id}/commit")->assertOk()->assertJsonPath('data.skipped', 3);
        $this->assertSame(1, Aytam::count());
    }

    public function test_different_children_with_the_same_name_are_not_flagged_as_certain_duplicates(): void
    {
        $this->createAytam(['first_name' => 'Ahmad', 'last_name' => 'Abdullah', 'date_of_birth' => '1999-01-01']);
        $id = $this->stage($this->csv([['L-1', 'Ahmad', '', 'Abdullah', '2012-04-17', 'M', '', '', '', '', '', '', '', '']]));
        $this->postJson($this->base()."/imports/{$id}/validate")->assertJsonPath('data.valid', 1);
    }

    // ---- bulk behaviour ----

    public function test_a_large_file_imports_in_small_resumable_steps(): void
    {
        $rows = [];
        for ($i = 1; $i <= 7; $i++) {
            $rows[] = ['L-'.$i, 'Child'.chr(64 + $i), '', 'Family'.chr(70 + $i), '2012-01-0'.$i, 'M', '', '', '', '', '', '', '', ''];
        }
        $id = $this->stage($this->csv($rows));
        $this->postJson($this->base()."/imports/{$id}/validate")->assertJsonPath('data.valid', 7);

        $steps = [];
        do {
            $r = $this->postJson($this->base()."/imports/{$id}/commit", ['limit' => 3])->assertOk()->json('data');
            $steps[] = [$r['created'], $r['remaining']];
        } while (! $r['finished']);
        $this->assertSame([[3, 4], [3, 1], [1, 0]], $steps);
        $this->assertSame(7, Aytam::count());
        $this->assertSame(range(1, 7), Aytam::orderBy('aytam_code')->get()->map(fn ($a, $i) => $i + 1)->all());
    }

    public function test_the_status_new_records_start_with_is_chosen_by_the_importer(): void
    {
        $id = $this->stage();
        $this->importAll($id, 'draft');
        $this->assertSame(['draft'], Aytam::pluck('status')->unique()->values()->all());
        $this->assertSame(0, Aytam::whereNotNull('approved_at')->count());
        $id2 = $this->stage($this->csv([['X-1', 'New', '', 'Child', '2013-03-03', 'M', '', '', '', '', '', '', '', '']]));
        $this->postJson($this->base()."/imports/{$id2}/validate")->assertOk();
        $this->postJson($this->base()."/imports/{$id2}/commit", ['status' => 'archived'])->assertStatus(422);
    }

    // ---- mapping rules ----

    public function test_mapping_rules(): void
    {
        $batch = $this->upload($this->csv($this->rows()))->assertCreated()->json('data');
        $id = $batch['id'];
        $put = fn (array $mapping, string $fmt = 'iso') => $this->putJson($this->base()."/imports/{$id}/mapping", ['mapping' => $mapping, 'date_format' => $fmt]);

        $put(['1' => 'aytam.first_name'])->assertStatus(422)->assertJsonPath('code', 'MAPPING_INCOMPLETE');
        $put(['1' => 'aytam.first_name', '3' => 'aytam.last_name', '2' => 'aytam.last_name'])->assertStatus(422)->assertJsonPath('code', 'INVALID_MAPPING');
        $put(['1' => 'aytam.first_name', '3' => 'aytam.last_name', '4' => 'aytam.shoe_size'])->assertStatus(422);
        $put(['1' => 'aytam.first_name', '3' => 'aytam.last_name', '99' => 'aytam.city'])->assertStatus(422);
        $put(['1' => 'aytam.first_name', '3' => 'aytam.last_name', '5' => 'aytam.photo'])->assertStatus(422);
        $put(['1' => 'aytam.first_name', '3' => 'aytam.last_name'], 'mm-dd')->assertStatus(422);
        $this->postJson($this->base()."/imports/{$id}/validate")->assertStatus(422)->assertJsonPath('code', 'MAPPING_REQUIRED');
        $put(['1' => 'aytam.first_name', '3' => 'aytam.last_name', '6' => ''])->assertOk()->assertJsonPath('data.mapping', ['1' => 'aytam.first_name', '3' => 'aytam.last_name']);

        // Remapping after validating starts the checks again.
        $this->postJson($this->base()."/imports/{$id}/validate")->assertOk();
        $put(['1' => 'aytam.first_name', '3' => 'aytam.last_name', '6' => 'aytam.city'])->assertOk()->assertJsonPath('data.status', 'mapped');
        $this->assertSame(0, ImportRow::where('batch_id', $id)->whereIn('status', ['valid', 'invalid'])->count());
    }

    // ---- hostile or broken files ----

    public function test_unsuitable_files_are_refused_before_anything_is_stored(): void
    {
        $bad = [
            'old excel' => UploadedFile::fake()->createWithContent('list.xls', "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1".str_repeat("\0", 100)),
            'php' => UploadedFile::fake()->createWithContent('shell.php', '<?php system($_GET["c"]);'),
            'binary as csv' => UploadedFile::fake()->createWithContent('data.csv', "a,b\0c\x01\x02\x03"),
            'zip as xlsx' => UploadedFile::fake()->createWithContent('data.xlsx', "PK\x03\x04".str_repeat('x', 200)),
            'plain zip named csv' => UploadedFile::fake()->createWithContent('data.csv', "PK\x03\x04".str_repeat('x', 200)),
            'empty' => UploadedFile::fake()->createWithContent('empty.csv', ''),
            'header only' => $this->csv([]),
            'html' => UploadedFile::fake()->createWithContent('page.html', '<html></html>'),
        ];
        foreach ($bad as $why => $file) {
            $this->upload($file)->assertStatus(422);
        }
        $this->assertSame(0, ImportBatch::count());
        $this->assertSame([], Storage::disk('local')->allFiles('imports'));
        $this->actingAs($this->mushrif)->post($this->base().'/imports', [], ['Accept' => 'application/json'])->assertStatus(422);
    }

    public function test_row_and_size_limits(): void
    {
        $rows = array_fill(0, 5001, ['', 'A', '', 'B', '2012-01-01', 'M', '', '', '', '', '', '', '', '']);
        $this->upload($this->csv($rows))->assertStatus(422)->assertJsonPath('errors.file.0', 'The file has more than 5000 rows. Split it into smaller files.');
        $this->assertSame(0, ImportBatch::count());
        $this->assertSame(0, ImportRow::count());
    }

    // ---- access ----

    public function test_only_importers_of_this_program_and_foundation_can_use_it(): void
    {
        $id = $this->stage();
        $this->actingAs($this->worker)->getJson($this->base().'/imports')->assertStatus(403);
        $this->actingAs($this->worker)->post($this->base().'/imports', ['file' => $this->csv($this->rows())], ['Accept' => 'application/json'])->assertStatus(403);
        $this->actingAs($this->outsider)->getJson($this->base()."/imports/{$id}")->assertStatus(403);

        // A different Aytam program in the same foundation cannot see this batch.
        $other = \App\Models\Program::find($this->actingAs($this->admin)->postJson('/api/programs', ['name' => 'Aytam North', 'category' => 'aytam'])->json('data.id'));
        $this->postJson('/api/programs/'.$other->id.'/status', ['status' => 'active']);
        $this->getJson($this->base($other)."/imports/{$id}")->assertStatus(404);
        $this->postJson($this->base($other)."/imports/{$id}/commit")->assertStatus(404);

        [, $foreign] = $this->createFoundation('Other', 'other@example.test');
        $this->actingAs($foreign)->getJson($this->base()."/imports/{$id}")->assertStatus(404);
        $this->inFoundation($this->foundation);
        $this->assertNotNull(ImportBatch::find($id));
    }

    public function test_cancelling_removes_the_uploaded_data(): void
    {
        $id = $this->stage();
        $path = ImportBatch::find($id)->storage_path;
        Storage::disk('local')->assertExists($path);

        $this->deleteJson($this->base()."/imports/{$id}")->assertOk();
        Storage::disk('local')->assertMissing($path);
        $this->assertSame('cancelled', ImportBatch::find($id)->status);
        $this->assertSame([[]], ImportRow::where('batch_id', $id)->pluck('raw')->unique()->values()->all());
        $this->postJson($this->base()."/imports/{$id}/validate")->assertStatus(422)->assertJsonPath('code', 'CLOSED');
        $this->assertSame(0, Aytam::count());
    }

    public function test_imports_need_an_active_program(): void
    {
        $id = $this->stage();
        $this->actingAs($this->admin)->postJson('/api/programs/'.$this->program->id.'/status', ['status' => 'inactive'])->assertOk();
        $this->actingAs($this->mushrif)->postJson($this->base()."/imports/{$id}/validate")->assertStatus(409)->assertJsonPath('code', 'PROGRAM_NOT_ACTIVE');
        $this->getJson($this->base()."/imports/{$id}")->assertOk();
    }

    public function test_a_date_that_lost_its_formatting_is_read_from_the_excel_day_counter(): void
    {
        $rows = [['L-1', 'Ahmad', '', 'Abdullah', 41016, 'M', '', '', '', '', '', '', '', '']];   // a plain number, not a date cell
        $id = $this->stage($this->xlsx($rows));
        $this->importAll($id);
        $this->assertSame('2012-04-17', Aytam::first()->date_of_birth->toDateString());
    }
}
