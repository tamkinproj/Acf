<?php

namespace App\Modules\Aytam\Registration;

use App\Models\Document;
use App\Models\RegistrationForm;
use App\Models\RegistrationFormField;
use App\Models\RegistrationFormSection;
use App\Models\RegistrationFormVersion;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The form builder's engine: saves a whole form structure in one go, checks it makes sense, and turns it into the
 * immutable snapshot that gets published. Drag-and-drop in the UI is just "send the structure again, in the new order".
 */
class FormBuilder
{
    /** Replace the form's sections and fields with $structure (ids are kept for sections/fields that still exist). */
    public function replace(RegistrationForm $form, array $structure): void
    {
        $sections = $structure['sections'] ?? [];
        $this->assertStructure($sections);

        DB::transaction(function () use ($form, $sections) {
            $keepSections = [];
            $keepFields = [];
            foreach (array_values($sections) as $sIndex => $s) {
                $section = (isset($s['id']) ? RegistrationFormSection::query()->where('form_id', $form->getKey())->find($s['id']) : null) ?? new RegistrationFormSection;
                $section->forceFill(['program_id' => $form->program_id, 'form_id' => $form->getKey(), 'title' => $s['title'], 'description' => $s['description'] ?? null, 'position' => $sIndex])->save();
                $keepSections[] = $section->getKey();

                foreach (array_values($s['fields'] ?? []) as $fIndex => $f) {
                    $field = (isset($f['id']) ? RegistrationFormField::query()->where('form_id', $form->getKey())->find($f['id']) : null) ?? new RegistrationFormField;
                    $field->forceFill([
                        'program_id' => $form->program_id, 'form_id' => $form->getKey(), 'section_id' => $section->getKey(),
                        'field_key' => $f['key'], 'label' => $f['label'], 'type' => $f['type'], 'required' => (bool) ($f['required'] ?? false),
                        'help_text' => $f['help_text'] ?? null, 'options' => FieldTypes::needsOptions($f['type']) ? array_values($f['options']) : null,
                        'maps_to' => $f['maps_to'] ?? null, 'document_type' => FieldTypes::isFile($f['type']) ? ($f['document_type'] ?? ($f['type'] === 'photo' ? 'photo' : 'other')) : null,
                        'position' => $fIndex,
                    ])->save();
                    $keepFields[] = $field->getKey();
                }
            }
            RegistrationFormField::query()->where('form_id', $form->getKey())->whereNotIn('id', $keepFields)->get()->each->delete();
            RegistrationFormSection::query()->where('form_id', $form->getKey())->whereNotIn('id', $keepSections)->get()->each->delete();
            $form->touch();
        });
    }

    /** @return array<string,mixed> the structure in builder shape (also what the UI loads) */
    public function structure(RegistrationForm $form): array
    {
        $fields = RegistrationFormField::query()->where('form_id', $form->getKey())->orderBy('position')->get()->groupBy('section_id');

        return ['sections' => RegistrationFormSection::query()->where('form_id', $form->getKey())->orderBy('position')->get()->map(fn ($s) => [
            'id' => $s->getKey(), 'title' => $s->title, 'description' => $s->description,
            'fields' => ($fields[$s->getKey()] ?? collect())->map(fn ($f) => [
                'id' => $f->getKey(), 'key' => $f->field_key, 'label' => $f->label, 'type' => $f->type, 'required' => $f->required, 'help_text' => $f->help_text,
                'options' => $f->options ?? [], 'maps_to' => $f->maps_to, 'document_type' => $f->document_type,
            ])->values()->all(),
        ])->values()->all()];
    }

    /** @return list<array{field:?string,message:string}> everything wrong with publishing the form as it is now */
    public function problems(RegistrationForm $form): array
    {
        $problems = [];
        $struct = $this->structure($form);
        $fields = collect($struct['sections'])->flatMap(fn ($s) => $s['fields']);
        if ($fields->isEmpty()) {
            return [['field' => null, 'message' => 'Add at least one question.']];
        }
        $mapped = $fields->pluck('maps_to')->filter()->all();
        foreach (CanonicalFields::all() as $key => $def) {
            if (($def['required_to_publish'] ?? false) && ! in_array($key, $mapped, true)) {
                $problems[] = ['field' => null, 'message' => "The form must collect the child's {$def['label']} (map a question to \"{$def['label']}\")."];
            }
        }
        foreach ($fields as $f) {
            if (in_array($f['maps_to'], ['aytam.first_name', 'aytam.last_name'], true) && ! $f['required']) {
                $problems[] = ['field' => $f['key'], 'message' => "\"{$f['label']}\" must be required."];
            }
        }
        foreach ($struct['sections'] as $s) {
            if ($s['fields'] === []) {
                $problems[] = ['field' => null, 'message' => "The section \"{$s['title']}\" has no questions."];
            }
        }

        return $problems;
    }

    /** Freeze the current structure as the next published version. */
    public function publish(RegistrationForm $form, User $by): RegistrationFormVersion
    {
        if ($problems = $this->problems($form)) {
            throw ValidationException::withMessages(['form' => array_column($problems, 'message')]);
        }

        return DB::transaction(function () use ($form, $by) {
            $number = 1 + (int) RegistrationFormVersion::query()->where('form_id', $form->getKey())->max('version');
            $version = RegistrationFormVersion::create([
                'form_id' => $form->getKey(), 'version' => $number, 'published_by' => $by->getKey(), 'published_at' => now(),
                'schema' => [
                    'title' => $form->title, 'description' => $form->description, 'settings' => $form->settings ?? [],
                    'program_id' => $form->program_id, 'sections' => $this->structure($form)['sections'],
                ],
            ]);
            $form->forceFill([
                'status' => RegistrationForm::PUBLISHED, 'published_version' => $number,
                'public_token' => $form->public_token ?? Str::lower(Str::random(40)),
            ])->save();

            return $version;
        });
    }

    /** The ready-made starting point: everything an Aytam registration normally asks, already mapped. */
    public function applyStandardTemplate(RegistrationForm $form): void
    {
        $text = fn (string $key, string $label, string $maps, bool $req = false, array $extra = []) => ['key' => $key, 'label' => $label, 'type' => 'short_text', 'required' => $req, 'maps_to' => $maps] + $extra;
        $status = ['options' => ['living', 'deceased', 'unknown']];

        $this->replace($form, ['sections' => [
            ['title' => 'About the child', 'fields' => [
                $text('first_name', 'First name', 'aytam.first_name', true),
                $text('middle_name', 'Middle name', 'aytam.middle_name'),
                $text('last_name', 'Last name', 'aytam.last_name', true),
                $text('arabic_name', 'Name in Arabic', 'aytam.arabic_name'),
                ['key' => 'date_of_birth', 'label' => 'Date of birth', 'type' => 'date', 'required' => true, 'maps_to' => 'aytam.date_of_birth'],
                ['key' => 'gender', 'label' => 'Gender', 'type' => 'dropdown', 'required' => true, 'maps_to' => 'aytam.gender', 'options' => ['male', 'female']],
                $text('nationality', 'Nationality', 'aytam.nationality'),
            ]],
            ['title' => 'Where the child lives', 'fields' => [
                ['key' => 'address', 'label' => 'Home address', 'type' => 'address', 'required' => true, 'maps_to' => 'aytam.address'],
            ]],
            ['title' => 'School', 'fields' => [
                ['key' => 'education_level', 'label' => 'Education level', 'type' => 'dropdown', 'maps_to' => 'aytam.education_level', 'options' => ['Not in school', 'Pre-school', 'Elementary', 'High school', 'College']],
                $text('school', 'School name', 'aytam.school'),
                $text('grade', 'Grade / year', 'aytam.grade'),
            ]],
            ['title' => 'Family', 'fields' => [
                $text('father_name', "Father's name", 'family.father_name'),
                ['key' => 'father_status', 'label' => "Father's status", 'type' => 'dropdown', 'maps_to' => 'family.father_status'] + $status,
                $text('mother_name', "Mother's name", 'family.mother_name'),
                ['key' => 'mother_status', 'label' => "Mother's status", 'type' => 'dropdown', 'maps_to' => 'family.mother_status'] + $status,
            ]],
            ['title' => 'Guardian', 'description' => 'The adult who looks after the child.', 'fields' => [
                $text('guardian_name', "Guardian's full name", 'guardian.full_name', true),
                $text('guardian_relationship', 'Relationship to the child', 'guardian.relationship'),
                ['key' => 'guardian_phone', 'label' => "Guardian's phone", 'type' => 'phone', 'required' => true, 'maps_to' => 'guardian.phone'],
                ['key' => 'guardian_email', 'label' => "Guardian's email", 'type' => 'email', 'maps_to' => 'guardian.email'],
            ]],
            ['title' => 'Documents', 'description' => 'Clear photos or scans (PDF, JPEG or PNG, up to 10 MB each).', 'fields' => [
                ['key' => 'photo', 'label' => 'Photo of the child', 'type' => 'photo', 'required' => true, 'maps_to' => 'aytam.photo', 'document_type' => 'photo'],
                ['key' => 'birth_certificate', 'label' => 'Birth certificate', 'type' => 'file_upload', 'required' => true, 'document_type' => 'birth_certificate'],
                ['key' => 'death_certificate', 'label' => "Parent's death certificate (if available)", 'type' => 'file_upload', 'document_type' => 'other'],
                ['key' => 'school_record', 'label' => 'School record (Form 137 / transcript)', 'type' => 'file_upload', 'document_type' => 'transcript'],
            ]],
        ]]);
    }

    // ---- validation of the builder payload ----

    /** @throws ValidationException */
    private function assertStructure(array $sections): void
    {
        $errors = [];
        $keys = [];
        foreach ($sections as $i => $s) {
            if (trim((string) ($s['title'] ?? '')) === '') {
                $errors["sections.{$i}.title"] = 'Every section needs a title.';
            }
            foreach ($s['fields'] ?? [] as $j => $f) {
                $at = "sections.{$i}.fields.{$j}";
                $type = $f['type'] ?? null;
                if (trim((string) ($f['label'] ?? '')) === '') {
                    $errors["{$at}.label"] = 'Every question needs a label.';
                }
                if (! in_array($type, FieldTypes::all(), true)) {
                    $errors["{$at}.type"] = 'Unknown question type.';
                    continue;
                }
                $key = $f['key'] ?? '';
                if (! preg_match('/^[a-z][a-z0-9_]{0,58}$/', (string) $key)) {
                    $errors["{$at}.key"] = 'The question key must be lower-case letters, digits and underscores.';
                } elseif (isset($keys[$key])) {
                    $errors["{$at}.key"] = "The key \"{$key}\" is used twice.";
                }
                $keys[$key] = true;

                if (FieldTypes::needsOptions($type)) {
                    $options = $f['options'] ?? [];
                    if (! is_array($options) || count($options) < 1 || count($options) > 50 || count(array_unique(array_map('strval', $options))) !== count($options)
                        || collect($options)->contains(fn ($o) => ! is_string($o) || trim($o) === '' || mb_strlen($o) > 120)) {
                        $errors["{$at}.options"] = 'Give 1 to 50 different, non-empty options.';
                    }
                }
                if (FieldTypes::isFile($type) && isset($f['document_type']) && ! in_array($f['document_type'], Document::TYPES, true)) {
                    $errors["{$at}.document_type"] = 'Unknown document type.';
                }
                if (($map = $f['maps_to'] ?? null) !== null && $map !== '') {
                    $def = CanonicalFields::all()[$map] ?? null;
                    if (! $def) {
                        $errors["{$at}.maps_to"] = 'Unknown field to map to.';
                    } elseif (! in_array($type, $def['types'], true)) {
                        $errors["{$at}.maps_to"] = "\"{$def['label']}\" cannot be filled by a ".strtolower(FieldTypes::LABELS[$type]).' question.';
                    } elseif (isset($def['enum']) && FieldTypes::needsOptions($type) && array_diff($f['options'] ?? [], $def['enum'])) {
                        $errors["{$at}.options"] = "\"{$def['label']}\" only accepts: ".implode(', ', $def['enum']).'.';
                    }
                }
            }
        }
        $mapped = collect($sections)->flatMap(fn ($s) => $s['fields'] ?? [])->pluck('maps_to')->filter();
        if ($mapped->contains('aytam.address') && $mapped->first(fn ($m) => in_array(CanonicalFields::field($m), CanonicalFields::addressParts(), true) && CanonicalFields::entity($m) === 'aytam')) {
            $errors['maps_to'] = 'Use either the full child address or its separate parts, not both.';
        }
        if ($dup = $mapped->duplicates()->first()) {
            $errors['maps_to'] = "Two questions are mapped to \"".(CanonicalFields::all()[$dup]['label'] ?? $dup).'".';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
