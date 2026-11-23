<?php

namespace App\Services\OrphanReports;

use App\Models\Child;
use App\Models\OrphanReport;
use App\Support\Pdf\Document;
use App\Support\Pdf\Form;
use Illuminate\Support\Carbon;

/**
 * Dar Al Ber's periodic orphan report ("التقرير الدوري لليتيم"): what goes in each field of their form, taken from the
 * orphan's record and the year's report, and the filled PDF. Only the fields are filled; the form is not changed.
 */
final class DarAlBerReport
{
    public const PARTNER = 'dar_al_ber';

    /** Fields a template must have to be accepted as this form. */
    public const REQUIRED = ['Number1', 'Text5', 'Text6', 'BirthDate', 'Dropdown1', 'Dropdown3', 'Text22', 'Text23', 'Image1_af_image'];

    /** The choices the report can set, and the word of the form's list each one stands for. */
    public const CHOICES = [
        'health' => ['good' => 'جيدة', 'sick' => 'مريض', 'determined' => 'صاحب همة'],
        'stage' => ['kindergarten' => 'رياض الأطفال', 'primary' => 'الابتدائية', 'intermediate' => 'الإعدادية', 'secondary' => 'الثانوية', 'university' => 'الجامعة', 'none' => 'لا يدرس', 'child' => 'طفل', 'quran' => 'يحفظ القرآن الكريم'],
        'grade' => ['k1' => 'روضة أولى', 'k2' => 'روضة ثانية', '1' => 'الأول', '2' => 'الثاني', '3' => 'الثالث', '4' => 'الرابع', '5' => 'الخامس', '6' => 'السادس', '7' => 'السابع', '8' => 'الثامن', '9' => 'التاسع', '10' => 'العاشر', '11' => 'الحادي عشر', '12' => 'الثاني عشر', 'higher' => 'التعليم العالي'],
        'level' => ['excellent' => 'ممتاز', 'average' => 'متوسط', 'acceptable' => 'مقبول'],
        'commitment' => ['excellent' => 'ممتاز', 'good' => 'جيد', 'learning' => 'يتعلم', 'child' => 'طفل'],
    ];

    private const FIELDS = ['health' => 'Dropdown2', 'stage' => 'Dropdown3', 'grade' => 'Dropdown4', 'level' => 'Dropdown5', 'commitment' => 'Dropdown6'];

    private const RELATION = ['mother' => 'أم', 'father' => 'أب', 'grandfather' => 'جد', 'grandmother' => 'جدة', 'uncle' => 'عم', 'aunt' => 'عمة', 'brother' => 'أخ', 'sister' => 'أخت'];

    private const DEMONYM = ['filipino' => 'PH', 'filipina' => 'PH', 'pilipino' => 'PH', 'pinoy' => 'PH', 'فلبيني' => 'PH', 'فلبينية' => 'PH', 'indonesian' => 'ID', 'indian' => 'IN', 'pakistani' => 'PK',
        'bangladeshi' => 'BD', 'egyptian' => 'EG', 'sudanese' => 'SD', 'somali' => 'SO', 'yemeni' => 'YE', 'syrian' => 'SY', 'palestinian' => 'PS', 'jordanian' => 'JO', 'lebanese' => 'LB',
        'moroccan' => 'MA', 'thai' => 'TH', 'vietnamese' => 'VN', 'cambodian' => 'KH', 'burmese' => 'MM', 'nepali' => 'NP', 'nepalese' => 'NP', 'sri lankan' => 'LK', 'kenyan' => 'KE',
        'nigerian' => 'NG', 'ghanaian' => 'GH', 'emirati' => 'AE', 'afghan' => 'AF', 'ugandan' => 'UG', 'tanzanian' => 'TZ'];

    /**
     * Everything that goes on the report, by meaning (not by field name).
     *
     * @param  array{org_name:string, country:string}  $settings
     */
    public static function facts(Child $c, ?OrphanReport $r, int $year, array $settings, ?array $texts = null): array
    {
        $c->loadMissing(['guardian', 'sponsorship.sponsor']);
        $ch = (array) ($r?->choices ?? []);
        $age = $c->birth_date ? Carbon::parse($c->birth_date)->age : null;
        $auto = self::autoChoices($c, $age);
        $pick = fn (string $k) => isset($ch[$k]) && isset(self::CHOICES[$k][$ch[$k]]) ? $ch[$k] : $auto[$k];
        $stage = $pick('stage');
        $notStudying = in_array($stage, ['none', 'child'], true);

        $facts = [
            'year' => $year,
            'org_name' => $settings['org_name'],
            'country' => $settings['country'],
            'sponsor_name' => self::s($c->sponsor_name),   // the person who sponsors (not the partner charity the sponsorship comes through)
            'sponsor_file_no' => self::s($c->sponsor_file_no),
            'name' => self::arabicName($c),
            'first_name' => self::firstName($c),
            'file_no' => self::s($c->external_no),   // Dar Al Ber's own file number of the orphan
            'birth_date' => $c->birth_date ? Carbon::parse($c->birth_date)->format('d-m-Y') : null,
            'age' => $age,
            'gender' => in_array($c->gender, ['m', 'f'], true) ? $c->gender : null,
            'nationality' => self::s($c->nationality),
            'health' => $pick('health'),
            'health_note' => self::s($c->health_details),
            'stage' => $stage,
            'grade' => $notStudying ? null : $pick('grade'),
            'level' => $notStudying ? null : $pick('level'),
            'commitment' => $pick('commitment'),
            'education_note' => $notStudying ? self::s($c->reason_not_studying) : (self::s($c->madrasa_grade) ? 'المدرسة الدينية: '.$c->madrasa_grade : null),
            'quran' => self::s($c->quran_memorization),
            'responsible_name' => self::s($r?->responsible_name) ?? self::responsibleName($c),
            'responsible_relation' => self::s($r?->responsible_relation) ?? self::relation($c),
            'auto' => $auto,
        ];
        foreach (['changes', 'comment', 'message'] as $k) {
            $facts[$k] = self::s($r?->{$k}) ?? ($texts[$k] ?? null);
        }

        return $facts;
    }

    /** What the record says for each choice of the form (before anyone changes it for the report). */
    public static function autoChoices(Child $c, ?int $age): array
    {
        $young = $age !== null && $age < 6;
        $grade = self::gradeKey((string) $c->grade, (string) $c->study_level);
        $stage = match ((string) $c->study_level) {
            'kindergarten' => 'kindergarten', 'primary' => 'primary', 'intermediate' => 'intermediate', 'secondary' => 'secondary',
            'university', 'vocational' => 'university',
            'none' => $young ? 'child' : 'none',
            default => null,
        };
        if ($stage === null && $grade !== null) {
            $stage = match (true) {
                in_array($grade, ['k1', 'k2'], true) => 'kindergarten',
                $grade === 'higher' => 'university',
                (int) $grade <= 6 => 'primary',
                (int) $grade <= 9 => 'intermediate',
                default => 'secondary',
            };
        }
        if ($stage === null && $young) {
            $stage = 'child';
        }
        if ($stage === 'university') {
            $grade = 'higher';
        }

        return [
            'health' => match ((string) $c->health_status) { 'good' => 'good', 'needs_followup' => 'sick', default => null },
            'stage' => $stage,
            'grade' => $grade,
            'level' => match ((string) $c->performance) { 'excellent', 'very_good' => 'excellent', 'good', 'average' => 'average', 'weak' => 'acceptable', default => null },
            'commitment' => $young ? 'child' : match ((string) $c->islamic_commitment) { 'excellent', 'very_good' => 'excellent', 'good' => 'good', 'average', 'weak' => 'learning', default => null },
        ];
    }

    /** "7", "Grade 7", "السابع", "Kinder 2" ... => the form's grade. */
    public static function gradeKey(string $g, string $stage = ''): ?string
    {
        $g = trim(strtr(mb_strtolower($g), array_combine(['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'], range(0, 9))));
        if (in_array($stage, ['university', 'vocational'], true) || preg_match('/college|university|جامع|عالي|higher/u', $g)) {
            return 'higher';
        }
        if (preg_match('/kinder|^k\s*\d|kg|روضة|nursery|prep/u', $g)) {
            return preg_match('/2|ثاني|two|ii\b/u', $g) ? 'k2' : 'k1';
        }
        if (preg_match('/(\d{1,2})/', $g, $m) && (int) $m[1] >= 1 && (int) $m[1] <= 12) {
            return (string) (int) $m[1];
        }
        foreach (array_reverse(self::CHOICES['grade'], true) as $k => $word) {   // longest Arabic words first (الحادي عشر before الأول)
            if (is_numeric($k) && $g !== '' && str_contains($g, $word)) {
                return (string) $k;
            }
        }

        return null;
    }

    /** How the grade reads in a sentence: "الصف السابع", "الروضة الثانية", "المرحلة الجامعية". */
    public static function gradeText(?string $k): ?string
    {
        return match (true) {
            $k === null => null,
            $k === 'k1' => 'الروضة الأولى',
            $k === 'k2' => 'الروضة الثانية',
            $k === 'higher' => 'المرحلة الجامعية',
            default => 'الصف '.self::CHOICES['grade'][$k],
        };
    }

    /** What the phrase writer needs to know. */
    public static function phraseInput(array $f): array
    {
        $young = $f['stage'] === 'child' || ($f['age'] !== null && $f['age'] < 6);

        return [
            'female' => $f['gender'] === 'f',
            'name' => $f['first_name'],
            'grade' => in_array($f['stage'], ['none', 'child', null], true) ? null : self::gradeText($f['grade']),
            'stage' => null,
            'quran' => self::quranText($f['quran']),
            'level' => match (true) { in_array($f['stage'], ['none', 'child', null], true) => 'none', $f['level'] === 'excellent' => 'high', $f['level'] === 'acceptable' => 'low', default => 'mid' },
            'health' => $f['health'] ?: 'good',
            'young' => $young,
            'child_voice' => ! $young && $f['age'] !== null && $f['age'] >= 10,
        ];
    }

    /** "9" => "9 أجزاء"; Arabic words stay as they are; anything else is not used in a sentence. */
    public static function quranText(?string $q): ?string
    {
        $q = trim((string) $q);
        if ($q === '' || preg_match('/^(0|لا|none|no)$/iu', $q)) {
            return null;
        }
        if (preg_match('/^\d{1,2}$/', $q)) {
            $n = (int) $q;

            return match (true) { $n === 1 => 'جزءًا واحدًا', $n === 2 => 'جزأين', $n <= 10 => $n.' أجزاء', $n <= 30 => $n.' جزءًا', default => null };
        }

        return preg_match('/\p{Arabic}/u', $q) && mb_strlen($q) <= 40 ? $q : null;
    }

    /** The filled PDF. */
    public static function pdf(Document $template, array $f, ?string $photo): string
    {
        $form = new Form($template->fork());
        $text = ['Number1' => (string) $f['year'], 'Text1' => $f['org_name'], 'Text3' => $f['sponsor_name'], 'Text4' => $f['sponsor_file_no'], 'Text5' => $f['name'], 'Text6' => $f['file_no'],
            'BirthDate' => $f['birth_date'], 'Age' => $f['age'] === null ? null : (string) $f['age'], 'Text11' => $f['health_note'], 'Text15' => $f['education_note'], 'Text17' => $f['quran'],
            'Text18' => $f['responsible_name'], 'Text19' => $f['responsible_relation'], 'Text21' => $f['changes'], 'Text22' => $f['comment'], 'Text23' => $f['message']];
        foreach ($text as $field => $v) {
            $form->setText($field, $v);
        }
        $form->setChoice('Dropdown1', self::option($form, 'Dropdown1', $f['gender'] === 'm' ? 'ذكر' : ($f['gender'] === 'f' ? 'أنثى' : null)));
        $form->setHidden('Male_Logo', $f['gender'] !== 'm');
        $form->setHidden('Female_Logo', $f['gender'] !== 'f');
        foreach (self::FIELDS as $k => $field) {
            $form->setChoice($field, self::option($form, $field, $f[$k] ? self::CHOICES[$k][$f[$k]] : null));
        }
        $form->setHidden('Icon_Good', $f['health'] !== 'good');
        $form->setHidden('Icon_Sick', $f['health'] !== 'sick');
        $form->setHidden('Icon_Determined', $f['health'] !== 'determined');
        $country = self::countryCode($form, $f['country']);
        $nat = self::countryCode($form, $f['nationality']) ?? $country;
        $form->setChoice('Country', $country);
        $form->setChoice('Nationality', $nat);
        foreach ($form->options('Country') as [$code]) {   // the flag of the chosen country shows, the others hide (what the form's script does)
            if ($code !== '') {
                $form->setHidden($code, $code !== $country);
                $form->setHidden('Nat_'.$code, $code !== $nat);
            }
        }
        $form->setImage('Image1_af_image', $photo);
        $form->clearRest();

        return $form->save();
    }

    /** The exact text of the form's choice that matches a word (the lists have stars and spaces after some words). */
    public static function option(Form $form, string $field, ?string $word): ?string
    {
        if ($word === null) {
            return null;
        }
        $norm = fn (string $s) => trim(preg_replace('/[\x{2605}\x{2606}\s]+/u', ' ', $s));
        foreach ($form->options($field) as [$exp, $shown]) {
            if ($norm($shown) === $word) {
                return $exp;
            }
        }
        foreach ($form->options($field) as [$exp, $shown]) {
            if ($word !== '' && str_contains($norm($shown), $word)) {
                return $exp;
            }
        }

        return null;
    }

    /** A country (code, English or Arabic name, or nationality word) => the form's two-letter code. */
    public static function countryCode(Form $form, ?string $raw): ?string
    {
        $raw = mb_strtolower(trim((string) $raw));
        if ($raw === '') {
            return null;
        }
        $opts = array_filter($form->options('Country'), fn ($o) => $o[0] !== '');
        foreach ($opts as [$code]) {
            if ($raw === mb_strtolower($code)) {
                return $code;
            }
        }
        foreach (self::DEMONYM as $w => $code) {
            if (str_contains($raw, $w)) {
                return $code;
            }
        }
        foreach ($opts as [$code, $shown]) {
            foreach (array_map('trim', explode(' - ', mb_strtolower($shown))) as $part) {
                if ($part !== '' && (str_contains($raw, $part) || str_contains($part, $raw))) {
                    return $code;
                }
            }
        }

        return null;
    }

    private static function arabicName(Child $c): ?string
    {
        if (self::s($c->name_ar)) {
            return trim($c->name_ar);
        }
        $parts = array_filter([$c->child_first_ar ?? null, $c->child_father_ar ?? null, $c->child_grand_ar ?? null, $c->child_family_ar ?? null], fn ($x) => self::s($x));

        return $parts ? implode(' ', array_map('trim', $parts)) : self::s($c->name_en);
    }

    private static function firstName(Child $c): string
    {
        $first = self::s($c->child_first_ar ?? null) ?? (self::s($c->name_ar) ? preg_split('/\s+/u', trim($c->name_ar))[0] : null);

        return $first ?? (self::s($c->name_en) ? preg_split('/\s+/u', trim($c->name_en))[0] : 'اليتيم');
    }

    private static function responsibleName(Child $c): ?string
    {
        if (self::s($c->responsible_name_ar ?? null)) {
            return trim($c->responsible_name_ar);
        }
        $parts = array_filter([$c->resp_first_ar ?? null, $c->resp_father_ar ?? null, $c->resp_grand_ar ?? null, $c->resp_family_ar ?? null], fn ($x) => self::s($x));
        if ($parts) {
            return implode(' ', array_map('trim', $parts));
        }

        return self::s($c->responsible_name_en ?? null) ?? self::s($c->guardian?->name);
    }

    private static function relation(Child $c): ?string
    {
        $r = self::s($c->responsible_relation ?? null) ?? self::s($c->guardian?->relation);
        if ($r === null) {
            return null;
        }

        return self::RELATION[mb_strtolower($r)] ?? (preg_match('/\p{Arabic}/u', $r) ? preg_replace('/^ال/u', '', $r) : $r);
    }

    private static function s(mixed $v): ?string
    {
        $v = trim((string) $v);

        return $v === '' || $v === '-' ? null : $v;
    }
}
