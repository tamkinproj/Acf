<?php

namespace App\Modules\Aytam\Import;

use App\Core\Audit\Auditor;
use App\Models\Aytam;
use App\Models\Family;
use App\Models\Guardian;
use App\Models\ImportBatch;
use App\Models\ImportRow;
use App\Models\Program;
use App\Models\User;
use App\Modules\Aytam\AytamRules;
use App\Modules\Aytam\AytamService;
use App\Modules\Aytam\DuplicateDetector;
use App\Modules\Aytam\NameNormalizer;
use App\Sync\RejectChange;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Bringing existing data in, safely: upload -> map columns -> validate (with duplicate detection) -> people decide ->
 * import in small resumable steps. Errors are shown BEFORE anything is created; uncertain matches are never merged
 * automatically; and what was uploaded is deleted once the import is finished or cancelled.
 */
class ImportService
{
    public const MAX_ROWS = 5000;
    public const DECISIONS = ['create_new', 'use_existing', 'skip'];

    public function __construct(
        private SpreadsheetReader $reader,
        private ImportMapper $mapper,
        private DuplicateDetector $duplicates,
        private AytamService $aytam,
        private Auditor $auditor,
    ) {}

    // ---- 1. upload ----

    /** @throws InvalidArgumentException with a message safe to show */
    public function stage(Program $program, UploadedFile $file): ImportBatch
    {
        $real = $file->getRealPath();
        $type = $this->reader->detect($real, $file->getClientOriginalName());
        $path = "imports/{$program->foundation_id}/{$program->getKey()}/".Str::lower(Str::random(40)).'.'.$type;
        Storage::disk('local')->put($path, fopen($real, 'rb'));

        try {
            return DB::transaction(function () use ($program, $file, $real, $type, $path) {
                $buffer = [];
                $batch = new ImportBatch;
                $batch->forceFill([
                    'program_id' => $program->getKey(), 'original_name' => mb_substr(basename(str_replace('\\', '/', $file->getClientOriginalName())), 0, 200),
                    'storage_path' => $path, 'file_type' => $type, 'status' => ImportBatch::UPLOADED, 'headers' => [], 'row_count' => 0,
                ])->save();

                $flush = function () use (&$buffer) {
                    if ($buffer) {
                        DB::table('import_rows')->insert($buffer);
                        $buffer = [];
                    }
                };
                $info = $this->reader->read($real, $type, self::MAX_ROWS, function (int $n, array $cells) use ($batch, &$buffer, $flush) {
                    $buffer[] = [
                        'id' => (string) Str::uuid7(), 'foundation_id' => $batch->foundation_id, 'batch_id' => $batch->getKey(), 'row_number' => $n,
                        'raw' => json_encode($cells, JSON_UNESCAPED_UNICODE), 'status' => ImportRow::PENDING,
                    ];
                    if (count($buffer) >= 200) {
                        $flush();
                    }
                });
                $flush();

                $batch->forceFill(['headers' => $info['headers'], 'row_count' => $info['count'], 'summary' => ['suggested_mapping' => $this->mapper->suggest($info['headers'])]])->save();

                return $batch;
            });
        } catch (\Throwable $e) {
            Storage::disk('local')->delete($path);
            throw $e;
        }
    }

    // ---- 2. map ----

    /**
     * @param  array<int|string,string>  $mapping  column index => target
     *
     * @throws RejectChange
     */
    public function map(ImportBatch $batch, array $mapping, string $dateFormat): ImportBatch
    {
        $this->assertOpen($batch);
        $targets = ImportMapper::targets();
        $clean = [];
        foreach ($mapping as $index => $target) {
            if ($target === null || $target === '') {
                continue;
            }
            if (! is_numeric($index) || (int) $index < 0 || (int) $index >= count($batch->headers)) {
                throw new RejectChange('invalid_mapping', 'A mapped column does not exist in the file.');
            }
            if (! isset($targets[$target])) {
                throw new RejectChange('invalid_mapping', "Unknown field \"{$target}\".");
            }
            $clean[(int) $index] = $target;
        }
        if (count(array_unique($clean)) !== count($clean)) {
            throw new RejectChange('invalid_mapping', 'Two columns are mapped to the same field.');
        }
        foreach (['aytam.first_name' => 'First name', 'aytam.last_name' => 'Last name'] as $required => $label) {
            if (! in_array($required, $clean, true)) {
                throw new RejectChange('mapping_incomplete', "Map a column to \"{$label}\".");
            }
        }
        if (! isset(ImportMapper::DATE_FORMATS[$dateFormat])) {
            throw new RejectChange('invalid_mapping', 'Choose how dates are written in the file.');
        }

        $batch->forceFill([
            'mapping' => $clean, 'status' => ImportBatch::MAPPED,
            'summary' => ['suggested_mapping' => $batch->summary['suggested_mapping'] ?? [], 'date_format' => $dateFormat],
        ])->save();
        ImportRow::query()->where('batch_id', $batch->getKey())->update(['status' => ImportRow::PENDING, 'data' => null, 'errors' => null, 'matches' => null, 'decision' => null, 'existing_aytam_id' => null]);

        return $batch;
    }

    // ---- 3. validate + find duplicates ----

    /** @return array{valid:int,invalid:int,duplicate:int,total:int} */
    public function validate(ImportBatch $batch, Program $program): array
    {
        $this->assertOpen($batch);
        if ($batch->mapping === null) {
            throw new RejectChange('mapping_required', 'Map the columns first.');
        }
        $format = $batch->summary['date_format'] ?? 'iso';
        $mapping = $batch->mapping;
        $seen = [];   // within the file: bucket => [{row,norm,dob,name}]

        ImportRow::query()->where('batch_id', $batch->getKey())->orderBy('row_number')->chunk(200, function ($rows) use ($program, $mapping, $format, &$seen) {
            foreach ($rows as $row) {
                $result = $this->validateRow($program, $row->raw, $mapping, $format);
                $matches = [];
                if (! $result['errors']) {
                    $candidate = $result['aytam'];
                    $matches = array_map(fn ($m) => ['type' => 'existing'] + $m, $this->duplicates->find($program, $candidate));
                    $matches = array_merge($matches, $this->inFileMatches($row->row_number, $candidate, $seen));
                }
                $row->forceFill([
                    'data' => $result['data'], 'errors' => $result['errors'] ?: null, 'matches' => $matches ?: null, 'decision' => null, 'existing_aytam_id' => null,
                    'status' => $result['errors'] ? ImportRow::INVALID : ($matches ? ImportRow::DUPLICATE : ImportRow::VALID),
                ])->save();
            }
        });

        $counts = ImportRow::query()->where('batch_id', $batch->getKey())->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $summary = ['valid' => (int) ($counts['valid'] ?? 0), 'invalid' => (int) ($counts['invalid'] ?? 0), 'duplicate' => (int) ($counts['duplicate'] ?? 0)];
        $batch->forceFill(['status' => ImportBatch::VALIDATED, 'summary' => array_merge($batch->summary ?? [], $summary)])->save();

        return $summary + ['total' => $batch->row_count];
    }

    /**
     * @param  list<string>  $cells
     * @return array{data:?array,errors:array<string,list<string>>,aytam:array}
     */
    private function validateRow(Program $program, array $cells, array $mapping, string $format): array
    {
        $parsed = $this->mapper->row($cells, $mapping, $format);
        $errors = $parsed['errors'];
        $parts = $this->mapper->split($parsed['values']);

        $aytamRules = collect(AytamRules::rules($program, true))->except(['family_id', 'guardian_id'])->all();
        $v = Validator::make($parts['aytam'], $aytamRules);
        foreach ($v->errors()->messages() as $field => $messages) {
            $errors['aytam.'.$field] = array_merge($errors['aytam.'.$field] ?? [], $messages);
        }
        if ($parts['guardian']) {
            foreach (Validator::make($parts['guardian'], AytamRules::guardianRules(true))->errors()->messages() as $field => $messages) {
                $errors['guardian.'.$field] = $messages;
            }
        }
        if (array_filter($parts['family'])) {
            $family = $parts['family'] + ['name' => ($parts['aytam']['last_name'] ?? 'Family').' family'];
            foreach (Validator::make($family, AytamRules::familyRules($program, true))->errors()->messages() as $field => $messages) {
                $errors['family.'.$field] = $messages;
            }
        }

        return ['data' => $parts, 'errors' => $errors, 'aytam' => $parts['aytam']];
    }

    /** @param array<string,list<array>> $seen @return list<array<string,mixed>> */
    private function inFileMatches(int $rowNumber, array $candidate, array &$seen): array
    {
        $norm = NameNormalizer::name($candidate['first_name'] ?? null, $candidate['middle_name'] ?? null, $candidate['last_name'] ?? null);
        $dob = $candidate['date_of_birth'] ?? null;
        $bucket = $dob ?: 'nodob:'.(NameNormalizer::tokens($candidate['first_name'] ?? '')[0] ?? '');
        $found = [];
        foreach ($seen[$bucket] ?? [] as $other) {
            $score = NameNormalizer::similarity($norm, $other['norm']);
            $needed = $dob ? 0.85 : 0.98;
            if ($score >= $needed) {
                $found[] = [
                    'type' => 'file', 'row_number' => $other['row'], 'name' => $other['name'], 'date_of_birth' => $dob, 'level' => $score >= 0.98 ? 'exact' : 'probable',
                    'score' => round($score, 2), 'reasons' => ['Also appears on row '.$other['row'].' of this file'],
                ];
            }
        }
        $seen[$bucket][] = ['row' => $rowNumber, 'norm' => $norm, 'name' => trim(($candidate['first_name'] ?? '').' '.($candidate['last_name'] ?? ''))];

        return $found;
    }

    // ---- 4. decisions ----

    public function decide(ImportRow $row, ?string $decision, ?string $existingAytamId, Program $program): ImportRow
    {
        if (! in_array($row->status, [ImportRow::VALID, ImportRow::DUPLICATE], true)) {
            throw new RejectChange('not_decidable', 'Only rows without errors can be decided.');
        }
        if ($decision !== null && ! in_array($decision, self::DECISIONS, true)) {
            throw new RejectChange('invalid_decision', 'Unknown decision.');
        }
        if ($row->status === ImportRow::VALID && $decision !== null && $decision !== 'skip') {
            throw new RejectChange('invalid_decision', 'This row has no possible duplicate: it is imported as new unless you skip it.');
        }
        if ($decision === 'use_existing') {
            $existing = Aytam::query()->where('program_id', $program->getKey())->find($existingAytamId)
                ?? throw new RejectChange('existing_required', 'Choose the existing record this row belongs to.');
            $existingAytamId = $existing->getKey();
        }
        $row->forceFill(['decision' => $decision, 'existing_aytam_id' => $decision === 'use_existing' ? $existingAytamId : null])->save();

        return $row;
    }

    /** Apply one decision to every row currently in the given state (e.g. "skip all possible duplicates"). */
    public function decideAll(ImportBatch $batch, string $decision): int
    {
        if (! in_array($decision, ['create_new', 'skip'], true)) {
            throw new RejectChange('invalid_decision', 'Only "create new" or "skip" can be applied to many rows at once.');
        }

        return ImportRow::query()->where('batch_id', $batch->getKey())->where('status', ImportRow::DUPLICATE)->whereNull('decision')->update(['decision' => $decision]);
    }

    // ---- 5. import ----

    /**
     * Imports the next rows that are ready, at most $limit per call, so a large file never runs into a server time limit.
     * Call it again until `remaining` is 0.
     *
     * @return array{processed:int,created:int,linked:int,skipped:int,remaining:int,finished:bool}
     */
    public function commit(ImportBatch $batch, Program $program, User $actor, string $status = Aytam::ACTIVE, int $limit = 100): array
    {
        if ($batch->status !== ImportBatch::VALIDATED) {
            throw new RejectChange('not_validated', $batch->status === ImportBatch::IMPORTED ? 'This file has already been imported.' : 'Validate the file before importing.');
        }
        if (! in_array($status, [Aytam::DRAFT, Aytam::APPROVED, Aytam::ACTIVE], true)) {
            throw new RejectChange('invalid_status', 'Imported records can start as draft, approved or active.');
        }

        $ready = fn () => ImportRow::query()->where('batch_id', $batch->getKey())->where(function ($q) {
            $q->where('status', ImportRow::VALID)->orWhere(fn ($d) => $d->where('status', ImportRow::DUPLICATE)->whereNotNull('decision'));
        });
        $counts = ['created' => 0, 'linked' => 0, 'skipped' => 0];
        $families = [];
        $guardians = [];

        foreach ($ready()->orderBy('row_number')->limit(max(1, min($limit, 500)))->get() as $row) {
            try {
                DB::transaction(function () use ($row, $program, $actor, $status, &$counts, &$families, &$guardians) {
                    if ($row->decision === 'skip') {
                        $row->forceFill(['status' => ImportRow::SKIPPED])->save();
                        $counts['skipped']++;

                        return;
                    }
                    if ($row->decision === 'use_existing') {
                        $row->forceFill(['status' => ImportRow::IMPORTED, 'aytam_id' => $row->existing_aytam_id])->save();
                        $counts['linked']++;

                        return;
                    }
                    $data = $row->data;
                    [$family, $guardian] = $this->familyAndGuardian($program, $data, $families, $guardians);
                    $created = $this->aytam->create($program, $data['aytam'] + [
                        'family_id' => $family?->getKey(),
                        'guardian_id' => $guardian && $family?->guardian_id !== $guardian->getKey() ? $guardian->getKey() : null,
                    ], $actor, 'import', null, $status);
                    $row->forceFill(['status' => ImportRow::IMPORTED, 'aytam_id' => $created->getKey()])->save();
                    $counts['created']++;
                });
            } catch (\Throwable $e) {
                report($e);
                $row->forceFill(['status' => ImportRow::INVALID, 'errors' => ['row' => ['Could not be imported: '.Str::limit($e->getMessage(), 160)]]])->save();
            }
        }

        $remaining = $ready()->count();
        $finished = $remaining === 0;
        if ($finished) {
            $this->finish($batch, $actor);
        }

        return ['processed' => array_sum($counts), 'remaining' => $remaining, 'finished' => $finished] + $counts;
    }

    public function cancel(ImportBatch $batch): void
    {
        if ($batch->status === ImportBatch::IMPORTED) {
            throw new RejectChange('already_imported', 'An imported file cannot be cancelled.');
        }
        $this->purge($batch);
        $batch->forceFill(['status' => ImportBatch::CANCELLED])->save();
    }

    private function finish(ImportBatch $batch, User $actor): void
    {
        $totals = ImportRow::query()->where('batch_id', $batch->getKey())->selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status');
        $created = ImportRow::query()->where('batch_id', $batch->getKey())->where('status', ImportRow::IMPORTED)->whereNotNull('aytam_id')->get(['aytam_id', 'decision']);
        $summary = array_merge($batch->summary ?? [], [
            'imported' => (int) ($totals['imported'] ?? 0), 'skipped' => (int) ($totals['skipped'] ?? 0), 'invalid' => (int) ($totals['invalid'] ?? 0), 'held' => (int) ($totals['duplicate'] ?? 0),
            'linked_to_existing' => $created->where('decision', 'use_existing')->count(),
        ]);
        $this->purge($batch);
        $batch->forceFill(['status' => ImportBatch::IMPORTED, 'imported_at' => now(), 'summary' => $summary])->save();
        $this->auditor->record('import.performed', "Imported {$summary['imported']} of {$batch->row_count} rows from a {$batch->file_type} file", 'import_batches', $batch->getKey(), null, $summary, $actor);
    }

    /** The uploaded file and the personal data copied out of it are not kept once they have served their purpose. */
    private function purge(ImportBatch $batch): void
    {
        Storage::disk('local')->delete($batch->storage_path);
        ImportRow::query()->where('batch_id', $batch->getKey())->update(['raw' => json_encode([]), 'data' => null, 'matches' => null]);
    }

    /** @return array{0:?Family,1:?Guardian} reuses a family / guardian already created for an earlier row (or already on file) */
    private function familyAndGuardian(Program $program, array $data, array &$families, array &$guardians): array
    {
        $guardian = null;
        if (filled($data['guardian']['full_name'] ?? null)) {
            $g = $data['guardian'];
            $key = NameNormalizer::name($g['full_name']).'|'.preg_replace('/\D+/', '', (string) ($g['phone'] ?? ''));
            $guardian = $guardians[$key] ??= Guardian::query()->where('program_id', $program->getKey())->whereRaw('lower(full_name) = ?', [mb_strtolower($g['full_name'])])
                ->when(filled($g['phone'] ?? null), fn ($q) => $q->where('phone', $g['phone']))->first()
                ?? tap(new Guardian, fn (Guardian $n) => $n->forceFill($g + ['program_id' => $program->getKey()])->save());
        }

        $family = null;
        if (array_filter($data['family'] ?? [])) {
            $f = $data['family'] + ['name' => ($data['aytam']['last_name'] ?? 'Family').' family'];
            $key = mb_strtolower($f['name'].'|'.($f['father_name'] ?? '').'|'.($f['mother_name'] ?? ''));
            $family = $families[$key] ??= Family::query()->where('program_id', $program->getKey())->whereRaw('lower(name) = ?', [mb_strtolower($f['name'])])
                ->whereRaw("lower(coalesce(father_name, '')) = ?", [mb_strtolower($f['father_name'] ?? '')])
                ->whereRaw("lower(coalesce(mother_name, '')) = ?", [mb_strtolower($f['mother_name'] ?? '')])->first()
                ?? tap(new Family, fn (Family $n) => $n->forceFill($f + ['program_id' => $program->getKey(), 'guardian_id' => $guardian?->getKey()])->save());
            if ($guardian && $family->guardian_id === null) {
                $family->update(['guardian_id' => $guardian->getKey()]);
            }
        }

        return [$family, $guardian];
    }

    private function assertOpen(ImportBatch $batch): void
    {
        if (in_array($batch->status, [ImportBatch::IMPORTED, ImportBatch::CANCELLED], true)) {
            throw new RejectChange('closed', 'This import is finished.');
        }
    }
}
