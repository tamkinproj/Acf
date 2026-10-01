<?php

namespace App\Modules\Aytam\Registration;

use App\Models\Family;

/**
 * The canonical model a form maps onto: "capture once, reuse everywhere". A form field says WHICH canonical field it
 * fills; nothing else about a form ever reaches the database schema. Address fields can fill the six address parts at once.
 */
final class CanonicalFields
{
    public const TEXT = ['short_text', 'dropdown'];
    public const CHOICE = ['dropdown', 'multiple_choice', 'short_text'];

    /** @return array<string,array{label:string,group:string,types:list<string>,enum?:list<string>,required_to_publish?:bool}> */
    public static function all(): array
    {
        $genders = ['male', 'female'];
        $status = Family::PARENT_STATUSES;

        return [
            'aytam.first_name' => ['label' => 'First name', 'group' => 'Child', 'types' => ['short_text'], 'required_to_publish' => true],
            'aytam.middle_name' => ['label' => 'Middle name', 'group' => 'Child', 'types' => ['short_text']],
            'aytam.last_name' => ['label' => 'Last name', 'group' => 'Child', 'types' => ['short_text'], 'required_to_publish' => true],
            'aytam.arabic_name' => ['label' => 'Name in Arabic', 'group' => 'Child', 'types' => ['short_text']],
            'aytam.date_of_birth' => ['label' => 'Date of birth', 'group' => 'Child', 'types' => ['date']],
            'aytam.gender' => ['label' => 'Gender', 'group' => 'Child', 'types' => self::CHOICE, 'enum' => $genders],
            'aytam.nationality' => ['label' => 'Nationality', 'group' => 'Child', 'types' => self::TEXT],
            'aytam.address' => ['label' => 'Address (all parts)', 'group' => 'Child address', 'types' => ['address']],
            'aytam.country' => ['label' => 'Country', 'group' => 'Child address', 'types' => self::TEXT],
            'aytam.region' => ['label' => 'Region', 'group' => 'Child address', 'types' => self::TEXT],
            'aytam.province' => ['label' => 'Province', 'group' => 'Child address', 'types' => self::TEXT],
            'aytam.city' => ['label' => 'City / municipality', 'group' => 'Child address', 'types' => self::TEXT],
            'aytam.barangay' => ['label' => 'Barangay', 'group' => 'Child address', 'types' => self::TEXT],
            'aytam.address_detail' => ['label' => 'Street / detailed address', 'group' => 'Child address', 'types' => ['short_text', 'long_text']],
            'aytam.education_level' => ['label' => 'Education level', 'group' => 'Education', 'types' => self::TEXT],
            'aytam.school' => ['label' => 'School', 'group' => 'Education', 'types' => ['short_text']],
            'aytam.grade' => ['label' => 'Grade / year', 'group' => 'Education', 'types' => self::TEXT],
            'aytam.education_notes' => ['label' => 'Education notes', 'group' => 'Education', 'types' => ['long_text', 'short_text']],
            'aytam.phone' => ['label' => 'Contact phone', 'group' => 'Contact', 'types' => ['phone', 'short_text']],
            'aytam.email' => ['label' => 'Contact email', 'group' => 'Contact', 'types' => ['email', 'short_text']],
            'aytam.photo' => ['label' => 'Photo of the child', 'group' => 'Child', 'types' => ['photo']],

            'family.name' => ['label' => 'Family name', 'group' => 'Family', 'types' => ['short_text']],
            'family.father_name' => ['label' => "Father's name", 'group' => 'Family', 'types' => ['short_text']],
            'family.father_status' => ['label' => "Father's status", 'group' => 'Family', 'types' => self::CHOICE, 'enum' => $status],
            'family.mother_name' => ['label' => "Mother's name", 'group' => 'Family', 'types' => ['short_text']],
            'family.mother_status' => ['label' => "Mother's status", 'group' => 'Family', 'types' => self::CHOICE, 'enum' => $status],
            'family.phone' => ['label' => 'Family phone', 'group' => 'Family', 'types' => ['phone', 'short_text']],
            'family.address' => ['label' => 'Family address (all parts)', 'group' => 'Family', 'types' => ['address']],

            'guardian.full_name' => ['label' => "Guardian's name", 'group' => 'Guardian', 'types' => ['short_text']],
            'guardian.relationship' => ['label' => 'Relationship to the child', 'group' => 'Guardian', 'types' => self::TEXT],
            'guardian.phone' => ['label' => "Guardian's phone", 'group' => 'Guardian', 'types' => ['phone', 'short_text']],
            'guardian.email' => ['label' => "Guardian's email", 'group' => 'Guardian', 'types' => ['email', 'short_text']],
            'guardian.address' => ['label' => "Guardian's address", 'group' => 'Guardian', 'types' => ['address', 'long_text', 'short_text']],
        ];
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function entity(string $key): string
    {
        return explode('.', $key, 2)[0];
    }

    public static function field(string $key): string
    {
        return explode('.', $key, 2)[1];
    }

    /** @return list<string> the six address parts a composite address answer fills */
    public static function addressParts(): array
    {
        return ['country', 'region', 'province', 'city', 'barangay', 'address_detail'];
    }

    /** The key under which an address answer's parts are stored (form field "detail" == column address_detail). */
    public static function addressInput(): array
    {
        return ['country' => 'Country', 'region' => 'Region', 'province' => 'Province', 'city' => 'City / municipality', 'barangay' => 'Barangay', 'address_detail' => 'Street / details'];
    }
}
