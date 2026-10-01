<?php

namespace App\Modules\Aytam;

use App\Models\Family;
use App\Models\Guardian;
use App\Models\Program;
use App\Tenancy\TenantRule;
use Illuminate\Validation\Rule;

/** The canonical Aytam rules. Manual entry, registration approval and import all validate through here. */
final class AytamRules
{
    /** @return array<string,array> */
    public static function rules(Program $program, bool $creating): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];
        $inProgram = fn ($q) => $q->where('program_id', $program->getKey());

        return [
            'first_name' => [...$req, 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'last_name' => [...$req, 'string', 'max:100'],
            'arabic_name' => ['nullable', 'string', 'max:200'],
            'date_of_birth' => ['nullable', 'date', 'after:1900-01-01', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'nationality' => ['nullable', 'string', 'max:80'],
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'address_detail' => ['nullable', 'string', 'max:2000'],
            'family_id' => ['nullable', 'uuid', TenantRule::exists(Family::class, 'id', $inProgram)],
            'guardian_id' => ['nullable', 'uuid', TenantRule::exists(Guardian::class, 'id', $inProgram)],
            'education_level' => ['nullable', 'string', 'max:80'],
            'school' => ['nullable', 'string', 'max:200'],
            'grade' => ['nullable', 'string', 'max:40'],
            'education_notes' => ['nullable', 'string', 'max:5000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'legacy_ref' => ['nullable', 'string', 'max:100'],
        ];
    }

    /** @return list<string> every field a user can enter on a record */
    public static function fields(): array
    {
        return ['first_name', 'middle_name', 'last_name', 'arabic_name', 'date_of_birth', 'gender', 'nationality', 'country', 'region',
            'province', 'city', 'barangay', 'address_detail', 'family_id', 'guardian_id', 'education_level', 'school', 'grade',
            'education_notes', 'phone', 'email', 'legacy_ref'];
    }

    public static function familyRules(Program $program, bool $creating): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];

        return [
            'name' => [...$req, 'string', 'max:200'],
            'father_name' => ['nullable', 'string', 'max:200'],
            'father_status' => ['nullable', Rule::in(Family::PARENT_STATUSES)],
            'mother_name' => ['nullable', 'string', 'max:200'],
            'mother_status' => ['nullable', Rule::in(Family::PARENT_STATUSES)],
            'guardian_id' => ['nullable', 'uuid', TenantRule::exists(Guardian::class, 'id', fn ($q) => $q->where('program_id', $program->getKey()))],
            'phone' => ['nullable', 'string', 'max:40'],
            'country' => ['nullable', 'string', 'max:100'],
            'region' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'barangay' => ['nullable', 'string', 'max:100'],
            'address_detail' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }

    public static function guardianRules(bool $creating): array
    {
        $req = $creating ? ['required'] : ['sometimes', 'required'];

        return [
            'full_name' => [...$req, 'string', 'max:200'],
            'relationship' => ['nullable', 'string', 'max:60'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'address' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
