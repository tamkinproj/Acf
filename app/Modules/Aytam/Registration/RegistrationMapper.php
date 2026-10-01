<?php

namespace App\Modules\Aytam\Registration;

/** Turns a registration's answers into canonical Aytam / Family / Guardian values, using the version they were submitted against. */
class RegistrationMapper
{
    /**
     * @param  array<string,mixed>  $schema
     * @param  array<string,mixed>  $answers
     * @param  array<string,mixed>  $overrides  reviewer corrections keyed by canonical field ("aytam.first_name")
     * @return array{aytam:array<string,mixed>,family:array<string,mixed>,guardian:array<string,mixed>}
     */
    public function toCanonical(array $schema, array $answers, array $overrides = []): array
    {
        $out = ['aytam' => [], 'family' => [], 'guardian' => []];

        foreach (collect($schema['sections'])->flatMap(fn ($s) => $s['fields']) as $f) {
            $map = $f['maps_to'] ?? null;
            if (! $map || FieldTypes::isFile($f['type'])) {
                continue;
            }
            $value = $answers[$f['key']] ?? null;
            if ($value === null || $value === '' || $value === []) {
                continue;
            }
            [$entity, $field] = [CanonicalFields::entity($map), CanonicalFields::field($map)];

            if ($f['type'] === 'address') {
                if ($entity === 'guardian') {
                    $out['guardian']['address'] = implode(', ', array_filter(array_map(fn ($p) => $value[$p] ?? null, array_reverse(CanonicalFields::addressParts()))));
                } else {
                    foreach (CanonicalFields::addressParts() as $part) {
                        if (isset($value[$part])) {
                            $out[$entity][$part] = $value[$part];
                        }
                    }
                }
                continue;
            }
            $out[$entity][$field] = is_array($value) ? implode(', ', $value) : $value;
        }

        foreach ($overrides as $key => $value) {
            if (CanonicalFields::exists($key) && ! str_ends_with($key, '.photo')) {
                $out[CanonicalFields::entity($key)][CanonicalFields::field($key)] = $value === '' ? null : $value;
            }
        }
        // Lower-case enumerations the way the model stores them.
        foreach (['aytam' => ['gender'], 'family' => ['father_status', 'mother_status']] as $entity => $fields) {
            foreach ($fields as $f) {
                if (isset($out[$entity][$f]) && is_string($out[$entity][$f])) {
                    $out[$entity][$f] = mb_strtolower(trim($out[$entity][$f]));
                }
            }
        }

        return $out;
    }

    /** The field key whose file becomes the child's photo (if any). */
    public function photoFieldKey(array $schema): ?string
    {
        foreach (collect($schema['sections'])->flatMap(fn ($s) => $s['fields']) as $f) {
            if (($f['maps_to'] ?? null) === 'aytam.photo') {
                return $f['key'];
            }
        }

        return null;
    }

    public function applicantName(array $schema, array $answers): ?string
    {
        $c = $this->toCanonical($schema, $answers)['aytam'];
        $name = trim(implode(' ', array_filter([$c['first_name'] ?? null, $c['middle_name'] ?? null, $c['last_name'] ?? null])));

        return $name !== '' ? $name : null;
    }
}
