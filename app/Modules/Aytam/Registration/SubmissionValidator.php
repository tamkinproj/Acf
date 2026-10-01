<?php

namespace App\Modules\Aytam\Registration;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Validates an applicant's submission against the PUBLISHED snapshot of the form (never the live, possibly edited, draft).
 * Anything the form did not ask for is ignored, so an applicant cannot write fields the Mushrif did not intend.
 */
class SubmissionValidator
{
    /**
     * @param  array<string,mixed>  $schema  a form version's schema
     * @param  array<string,bool>  $alreadyHasFile  field key => a file was supplied earlier (correction round)
     * @return array{answers:array<string,mixed>,files:array<string,\Illuminate\Http\UploadedFile>}
     *
     * @throws ValidationException errors are keyed by "f.<key>" / "files.<key>"
     */
    public function validate(array $schema, Request $request, array $alreadyHasFile = []): array
    {
        $rules = [];
        $labels = [];
        $fields = collect($schema['sections'])->flatMap(fn ($s) => $s['fields']);

        foreach ($fields as $f) {
            $key = $f['key'];
            $req = $f['required'] ? ['required'] : ['nullable'];
            $labels["f.{$key}"] = $labels["files.{$key}"] = $f['label'];

            if (FieldTypes::isFile($f['type'])) {
                $rules["files.{$key}"] = [($f['required'] && ! ($alreadyHasFile[$key] ?? false)) ? 'required' : 'nullable', 'file', 'max:'.config('foundation.uploads.document_max_kb')];
                continue;
            }

            $rules["f.{$key}"] = match ($f['type']) {
                'short_text' => [...$req, 'string', 'max:255'],
                'long_text' => [...$req, 'string', 'max:5000'],
                'number' => [...$req, 'numeric', 'between:-999999999,999999999'],
                'date' => [...$req, 'date', 'after:1900-01-01', ...($f['maps_to'] === 'aytam.date_of_birth' ? ['before_or_equal:today'] : ['before:2200-01-01'])],
                'dropdown', 'multiple_choice' => [...$req, 'string', Rule::in($f['options'])],
                'checkbox' => [...$req, 'array', 'max:50'],
                'yes_no' => [...$req, Rule::in(['yes', 'no'])],
                'phone' => [...$req, 'string', 'max:25', 'regex:/^[0-9+()\-. ]{5,25}$/'],
                'email' => [...$req, 'string', 'email:rfc', 'max:190'],
                'address' => [...$req, 'array'],
            };
            if ($f['type'] === 'checkbox') {
                $rules["f.{$key}.*"] = ['string', Rule::in($f['options'])];
            }
            if ($f['type'] === 'address') {
                foreach (CanonicalFields::addressParts() as $part) {
                    $rules["f.{$key}.{$part}"] = ['nullable', 'string', 'max:'.($part === 'address_detail' ? 2000 : 100)];
                }
                if ($f['required']) {
                    $rules["f.{$key}"][] = function ($attribute, $value, $fail) use ($f) {
                        if (! filled($value['city'] ?? null) && ! filled($value['address_detail'] ?? null)) {
                            $fail('Please give at least the city or a street address for "'.$f['label'].'".');
                        }
                    };
                }
            }
        }

        $validator = Validator::make($request->all(), $rules, [
            'regex' => 'The :attribute is not a valid phone number.',
            'in' => 'Please choose one of the listed options for :attribute.',
        ], $labels);
        $validator->validate();

        $answers = [];
        $files = [];
        foreach ($fields as $f) {
            $key = $f['key'];
            if (FieldTypes::isFile($f['type'])) {
                if ($file = $request->file("files.{$key}")) {
                    $files[$key] = $file;
                }
                continue;
            }
            $value = $request->input("f.{$key}");
            $answers[$key] = $this->clean($f['type'], $value);
        }

        return ['answers' => $answers, 'files' => $files];
    }

    private function clean(string $type, mixed $value): mixed
    {
        if ($type === 'address') {
            $parts = [];
            foreach (CanonicalFields::addressParts() as $part) {
                if (($text = $this->text(is_array($value) ? ($value[$part] ?? null) : null)) !== null) {
                    $parts[$part] = $text;
                }
            }

            return $parts ?: null;
        }
        if ($type === 'checkbox') {
            return array_values(array_unique(array_map('strval', (array) $value))) ?: null;
        }
        if ($type === 'number') {
            return $value === null || $value === '' ? null : $value + 0;
        }

        return $this->text($value);
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $text = trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', (string) $value) ?? '');

        return $text === '' ? null : $text;
    }
}
