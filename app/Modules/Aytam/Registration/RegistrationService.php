<?php

namespace App\Modules\Aytam\Registration;

use App\Core\Audit\Auditor;
use App\Core\Documents\DocumentService;
use App\Core\Documents\DocumentStorage;
use App\Models\Aytam;
use App\Models\Document;
use App\Models\Family;
use App\Models\Guardian;
use App\Models\Program;
use App\Models\Registration;
use App\Models\RegistrationEvent;
use App\Models\RegistrationForm;
use App\Models\RegistrationFormVersion;
use App\Models\User;
use App\Modules\Aytam\AytamRules;
use App\Modules\Aytam\AytamService;
use App\Modules\Aytam\DuplicateDetector;
use App\Sync\RejectChange;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The life of a registration: submitted by an applicant, possibly sent back for correction, finally approved into the
 * permanent Aytam record. Every step is written to the review history.
 */
class RegistrationService
{
    private const REF_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(
        private RegistrationMapper $mapper,
        private DocumentService $documents,
        private DocumentStorage $storage,
        private DuplicateDetector $duplicates,
        private AytamService $aytam,
        private Auditor $auditor,
    ) {}

    /**
     * @param  array<string,mixed>  $answers
     * @param  array<string,UploadedFile>  $files  field key => file
     * @return array{registration:Registration,access_token:string}
     */
    public function submit(RegistrationForm $form, RegistrationFormVersion $version, array $answers, array $files, ?string $ip): array
    {
        $program = Program::query()->findOrFail($form->program_id);
        $token = Str::random(48);
        $stored = [];

        try {
            $registration = DB::transaction(function () use ($form, $version, $answers, $files, $ip, $token, $program, &$stored) {
                $contact = $this->mapper->toCanonical($version->schema, $answers);
                $reg = new Registration;
                $reg->forceFill([
                    'program_id' => $program->getKey(), 'form_id' => $form->getKey(), 'form_version_id' => $version->getKey(),
                    'reference' => $this->newReference(), 'status' => Registration::PENDING_REVIEW,
                    'applicant_name' => $this->mapper->applicantName($version->schema, $answers),
                    'applicant_email' => $contact['guardian']['email'] ?? $contact['aytam']['email'] ?? null,
                    'applicant_phone' => $contact['guardian']['phone'] ?? $contact['aytam']['phone'] ?? null,
                    'answers' => $answers, 'access_token_hash' => hash('sha256', $token),
                    'submitted_at' => now(), 'last_submitted_at' => now(), 'submission_count' => 1, 'submitted_ip' => $ip,
                ])->save();

                $reg->answers = $answers + $this->attach($reg, $version->schema, $files, [], $program, $stored);
                $reg->save();
                $this->event($reg, 'submitted');
                $this->auditor->record('registration.submitted', "Registration {$reg->reference} submitted through \"{$form->title}\"", 'registrations', $reg->getKey());

                return $reg;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                $this->storage->delete($path);
            }
            throw $e;
        }

        return ['registration' => $registration, 'access_token' => $token];
    }

    /** The applicant fixes what was asked of them and sends it again. */
    public function resubmit(Registration $reg, array $schema, array $answers, array $files): Registration
    {
        if ($reg->status !== Registration::NEEDS_CORRECTION) {
            throw new RejectChange('not_open', 'This registration is not waiting for corrections.');
        }
        $program = Program::query()->findOrFail($reg->program_id);
        $stored = [];

        try {
            return DB::transaction(function () use ($reg, $schema, $answers, $files, $program, &$stored) {
                $existing = collect($reg->answers)->filter(fn ($v) => is_array($v) && isset($v['document_id']))->all();
                $reg->answers = $answers + $this->attach($reg, $schema, $files, $existing, $program, $stored) + $existing;
                $reg->forceFill([
                    'status' => Registration::PENDING_REVIEW, 'last_submitted_at' => now(), 'submission_count' => $reg->submission_count + 1,
                    'review_note' => null, 'applicant_name' => $this->mapper->applicantName($schema, $answers) ?? $reg->applicant_name,
                ])->save();
                $this->event($reg, 'resubmitted');
                $this->auditor->record('registration.resubmitted', "Registration {$reg->reference} corrected and sent again", 'registrations', $reg->getKey());

                return $reg;
            });
        } catch (\Throwable $e) {
            foreach ($stored as $path) {
                $this->storage->delete($path);
            }
            throw $e;
        }
    }

    public function sendBack(Registration $reg, User $actor, string $note): Registration
    {
        if ($reg->status !== Registration::PENDING_REVIEW) {
            throw new RejectChange('invalid_state', 'Only registrations waiting for review can be sent back.');
        }
        if (trim($note) === '') {
            throw new RejectChange('reason_required', 'Say what the applicant needs to correct.');
        }
        $reg->forceFill(['status' => Registration::NEEDS_CORRECTION, 'review_note' => mb_substr(trim($note), 0, 500), 'reviewer_id' => $actor->getKey(), 'reviewed_at' => now()])->save();
        $this->event($reg, 'returned', $actor, $reg->review_note);
        $this->auditor->record('registration.returned', "Registration {$reg->reference} sent back for correction", 'registrations', $reg->getKey(), null, null, $actor);

        return $reg;
    }

    /**
     * Turn the registration into the permanent Aytam record (or attach it to an existing one the reviewer chose).
     *
     * @param  array{overrides?:array<string,mixed>,duplicate_decision?:?string,existing_aytam_id?:?string,family_id?:?string,guardian_id?:?string}  $opts
     *
     * @throws RejectChange|ValidationException|DuplicatesFound
     */
    public function approve(Registration $reg, Program $program, User $actor, array $opts = []): Aytam
    {
        if ($reg->status !== Registration::PENDING_REVIEW) {
            throw new RejectChange('invalid_state', 'Only registrations waiting for review can be approved.');
        }
        $schema = $reg->formVersion->schema;
        $canonical = $this->mapper->toCanonical($schema, $reg->answers, $opts['overrides'] ?? []);

        $data = $canonical['aytam'];
        $rules = collect(AytamRules::rules($program, true))->except(['family_id', 'guardian_id', 'legacy_ref'])->all();
        Validator::make($data, $rules)->validate();

        $decision = $opts['duplicate_decision'] ?? null;
        $matches = $this->duplicates->find($program, $data);
        if ($matches !== [] && $decision === null) {
            throw new DuplicatesFound($matches);
        }
        $existing = null;
        if ($decision === 'use_existing') {
            $existing = Aytam::query()->where('program_id', $program->getKey())->find($opts['existing_aytam_id'] ?? null)
                ?? throw new RejectChange('existing_required', 'Choose which existing record this registration belongs to.');
        }

        return DB::transaction(function () use ($reg, $program, $actor, $opts, $canonical, $data, $decision, $existing) {
            if ($existing) {
                $aytam = $existing;
            } else {
                [$family, $guardian] = $this->familyAndGuardian($program, $canonical, $opts);
                $aytam = $this->aytam->create($program, $data + [
                    'family_id' => $family?->getKey(), 'guardian_id' => $family && $family->guardian_id === $guardian?->getKey() ? null : $guardian?->getKey(),
                ], $actor, 'registration', $reg->getKey(), Aytam::APPROVED);
            }

            Document::query()->where('registration_id', $reg->getKey())->get()->each(fn (Document $d) => $this->documents->attachToAytam($d, $aytam));

            $reg->forceFill([
                'status' => Registration::APPROVED, 'aytam_id' => $aytam->getKey(), 'reviewer_id' => $actor->getKey(), 'reviewed_at' => now(),
                'duplicate_decision' => $existing ? 'use_existing' : ($decision === 'create_new' ? 'create_new' : null), 'review_note' => null,
            ])->save();
            $this->event($reg, 'approved', $actor, null, ['aytam_code' => $aytam->aytam_code, 'decision' => $reg->duplicate_decision]);
            $this->auditor->record('registration.approved', "Registration {$reg->reference} approved as {$aytam->aytam_code}", 'registrations', $reg->getKey(), null, ['aytam_code' => $aytam->aytam_code], $actor);

            return $aytam;
        });
    }

    // ---- internals ----

    /** @return array{0:?Family,1:?Guardian} */
    private function familyAndGuardian(Program $program, array $canonical, array $opts): array
    {
        $guardian = null;
        if (! empty($opts['guardian_id'])) {
            $guardian = Guardian::query()->where('program_id', $program->getKey())->findOrFail($opts['guardian_id']);
        } elseif (filled($canonical['guardian']['full_name'] ?? null)) {
            $values = Validator::make($canonical['guardian'], AytamRules::guardianRules(true))->validate();
            $guardian = new Guardian;
            $guardian->forceFill($values + ['program_id' => $program->getKey()])->save();
        }

        $family = null;
        if (! empty($opts['family_id'])) {
            $family = Family::query()->where('program_id', $program->getKey())->findOrFail($opts['family_id']);
        } elseif (array_filter($canonical['family'])) {
            $values = $canonical['family'] + ['name' => ($canonical['aytam']['last_name'] ?? 'Family').' family'];
            $values = Validator::make($values, AytamRules::familyRules($program, true))->validate();
            $family = new Family;
            $family->forceFill($values + ['program_id' => $program->getKey(), 'guardian_id' => $guardian?->getKey()])->save();
        }

        return [$family, $guardian];
    }

    /**
     * Store uploaded files as documents of this registration.
     *
     * @param  array<string,mixed>  $existing  previous file answers (field key => {document_id})
     * @param  list<string>  $stored  collects storage paths so a failure can remove them
     * @return array<string,array{document_id:string,name:string}>
     */
    private function attach(Registration $reg, array $schema, array $files, array $existing, Program $program, array &$stored): array
    {
        $fields = collect($schema['sections'])->flatMap(fn ($s) => $s['fields'])->keyBy('key');
        $answers = [];
        foreach ($files as $key => $file) {
            $field = $fields[$key] ?? null;
            if (! $field) {
                continue;
            }
            $replaces = isset($existing[$key]['document_id']) ? Document::query()->where('registration_id', $reg->getKey())->where('is_current', true)->find($existing[$key]['document_id']) : null;
            try {
                $doc = $this->documents->upload($program, $file, ['type' => $field['document_type'] ?? 'other'], null, null, $reg->getKey(), $replaces);
            } catch (RejectChange $e) {
                throw ValidationException::withMessages(["files.{$key}" => $e->getMessage()]);
            }
            $stored[] = $doc->storage_path;
            $answers[$key] = ['document_id' => $doc->getKey(), 'name' => $doc->original_name];
        }

        return $answers;
    }

    private function event(Registration $reg, string $type, ?User $actor = null, ?string $note = null, ?array $data = null): void
    {
        RegistrationEvent::create([
            'registration_id' => $reg->getKey(), 'type' => $type, 'actor_id' => $actor?->getKey(), 'actor_name' => $actor?->name,
            'note' => $note, 'data' => $data, 'created_at' => now(),
        ]);
    }

    private function newReference(): string
    {
        do {
            $ref = 'REG-';
            for ($i = 0; $i < 6; $i++) {
                $ref .= self::REF_ALPHABET[random_int(0, strlen(self::REF_ALPHABET) - 1)];
            }
        } while (Registration::withTrashed()->where('reference', $ref)->exists());

        return $ref;
    }
}
