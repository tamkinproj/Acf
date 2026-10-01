<?php

namespace App\Modules\Aytam\Import;

use App\Modules\Aytam\Registration\CanonicalFields;
use Illuminate\Support\Carbon;

/** Header guessing and value clean-up for imported rows. Ambiguity is reported, never guessed. */
class ImportMapper
{
    public const DATE_FORMATS = ['iso' => 'YYYY-MM-DD', 'dmy' => 'DD/MM/YYYY', 'mdy' => 'MM/DD/YYYY'];

    /** What a column can be mapped to: the canonical fields (minus composite and file ones) plus the old system's own identifier. */
    public static function targets(): array
    {
        $targets = [];
        foreach (CanonicalFields::all() as $key => $def) {
            if (in_array($key, ['aytam.photo', 'aytam.address', 'family.address'], true)) {
                continue;
            }
            $targets[$key] = ['key' => $key, 'label' => $def['label'], 'group' => $def['group']];
        }
        $targets['aytam.legacy_ref'] = ['key' => 'aytam.legacy_ref', 'label' => 'ID in the old system', 'group' => 'Child'];

        return $targets;
    }

    /** @param list<string> $headers @return array<int,string> column index => target */
    public function suggest(array $headers): array
    {
        $synonyms = [
            'aytam.first_name' => ['first name', 'firstname', 'given name', 'forename', 'name'],
            'aytam.middle_name' => ['middle name', 'middlename', 'middle'],
            'aytam.last_name' => ['last name', 'lastname', 'surname', 'family name'],
            'aytam.arabic_name' => ['arabic name', 'name in arabic', 'اسم'],
            'aytam.date_of_birth' => ['date of birth', 'dob', 'birthdate', 'birth date', 'birthday', 'born'],
            'aytam.gender' => ['gender', 'sex'],
            'aytam.nationality' => ['nationality', 'citizenship'],
            'aytam.country' => ['country'],
            'aytam.region' => ['region'],
            'aytam.province' => ['province', 'state'],
            'aytam.city' => ['city', 'municipality', 'town', 'city municipality'],
            'aytam.barangay' => ['barangay', 'brgy'],
            'aytam.address_detail' => ['address', 'street', 'street address', 'address detail'],
            'aytam.education_level' => ['education level', 'education', 'level'],
            'aytam.school' => ['school', 'school name'],
            'aytam.grade' => ['grade', 'year', 'grade level'],
            'aytam.phone' => ['phone', 'mobile', 'contact number', 'contact', 'telephone'],
            'aytam.email' => ['email', 'e-mail'],
            'aytam.legacy_ref' => ['id', 'ref', 'reference', 'legacy id', 'old id', 'case number', 'case no', 'record id'],
            'family.name' => ['family', 'family name'],
            'family.father_name' => ['father', 'father name', 'fathers name'],
            'family.father_status' => ['father status'],
            'family.mother_name' => ['mother', 'mother name', 'mothers name'],
            'family.mother_status' => ['mother status'],
            'guardian.full_name' => ['guardian', 'guardian name', 'caretaker'],
            'guardian.relationship' => ['relationship', 'guardian relationship'],
            'guardian.phone' => ['guardian phone', 'guardian contact', 'guardian mobile'],
            'guardian.email' => ['guardian email'],
            'guardian.address' => ['guardian address'],
        ];
        $lookup = [];
        foreach ($synonyms as $target => $words) {
            foreach ($words as $w) {
                $lookup[$this->key($w)] ??= $target;
            }
        }
        $used = [];
        $out = [];
        foreach ($headers as $i => $h) {
            $target = $lookup[$this->key($h)] ?? null;
            // A bare "name" column is the first name only if no better column claims it.
            if ($target && ! isset($used[$target])) {
                $out[$i] = $target;
                $used[$target] = true;
            }
        }

        return $out;
    }

    /**
     * @param  list<string>  $cells
     * @param  array<int,string>  $mapping
     * @return array{values:array<string,mixed>,errors:array<string,list<string>>}  values keyed by target ("aytam.first_name")
     */
    public function row(array $cells, array $mapping, string $dateFormat): array
    {
        $values = [];
        $errors = [];
        foreach ($mapping as $index => $target) {
            $raw = trim((string) ($cells[$index] ?? ''));
            if ($raw === '') {
                continue;
            }
            [$value, $problem] = $this->normalize($target, $raw, $dateFormat);
            if ($problem) {
                $errors[$target][] = $problem;
            } elseif ($value !== null) {
                $values[$target] = $value;
            }
        }

        return ['values' => $values, 'errors' => $errors];
    }

    /** Group "aytam.first_name" style values into entity arrays. @return array{aytam:array,family:array,guardian:array} */
    public function split(array $values): array
    {
        $out = ['aytam' => [], 'family' => [], 'guardian' => []];
        foreach ($values as $target => $value) {
            [$entity, $field] = explode('.', $target, 2);
            $out[$entity][$field] = $value;
        }

        return $out;
    }

    /** @return array{0:?string,1:?string} value, problem */
    private function normalize(string $target, string $raw, string $dateFormat): array
    {
        switch ($target) {
            case 'aytam.date_of_birth':
                return $this->date($raw, $dateFormat);
            case 'aytam.gender':
                $g = mb_strtolower($raw);
                $map = ['m' => 'male', 'male' => 'male', 'boy' => 'male', 'man' => 'male', 'ذكر' => 'male', 'f' => 'female', 'female' => 'female', 'girl' => 'female', 'woman' => 'female', 'أنثى' => 'female', 'انثى' => 'female'];

                return isset($map[$g]) ? [$map[$g], null] : [null, "\"{$raw}\" is not a recognised gender (use male or female)."];
            case 'family.father_status':
            case 'family.mother_status':
                $s = mb_strtolower($raw);
                $map = ['living' => 'living', 'alive' => 'living', 'deceased' => 'deceased', 'dead' => 'deceased', 'late' => 'deceased', 'died' => 'deceased', 'passed away' => 'deceased', 'unknown' => 'unknown'];

                return isset($map[$s]) ? [$map[$s], null] : [null, "\"{$raw}\" is not a recognised status (living, deceased or unknown)."];
            default:
                return [mb_substr($raw, 0, 2000), null];
        }
    }

    /** @return array{0:?string,1:?string} */
    private function date(string $raw, string $format): array
    {
        // A spreadsheet date that lost its date formatting arrives as Excel's day counter (e.g. 41016 = 2012-04-17).
        // Only a pure five-digit number is read this way: no written date looks like that, so nothing is being guessed.
        if (preg_match('/^\d{5}$/', $raw) && (int) $raw >= 10000 && (int) $raw <= 60000) {
            return [Carbon::create(1899, 12, 30)->addDays((int) $raw)->toDateString(), null];
        }

        $patterns = ['iso' => ['/^(\d{4})-(\d{1,2})-(\d{1,2})$/', [1, 2, 3]], 'dmy' => ['/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', [3, 2, 1]], 'mdy' => ['/^(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4})$/', [3, 1, 2]]];
        // A date that is already ISO (Excel date cells arrive that way) is unambiguous whatever the chosen format.
        foreach ([$format, 'iso'] as $f) {
            [$regex, $order] = $patterns[$f] ?? $patterns['iso'];
            if (preg_match($regex, $raw, $m)) {
                [$y, $mo, $d] = [(int) $m[$order[0]], (int) $m[$order[1]], (int) $m[$order[2]]];
                if (checkdate($mo, $d, $y)) {
                    return [Carbon::create($y, $mo, $d)->toDateString(), null];
                }

                return [null, "\"{$raw}\" is not a real date."];
            }
        }

        return [null, "\"{$raw}\" is not a date in the chosen format (".(self::DATE_FORMATS[$format] ?? $format).').'];
    }

    private function key(string $s): string
    {
        return trim(preg_replace('/[^\p{L}\p{N}]+/u', ' ', mb_strtolower($s)) ?? '');
    }
}
