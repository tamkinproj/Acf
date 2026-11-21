<?php

namespace App\Support;

/**
 * Writes an Arabic personal name in Latin letters, entirely inside this server (children's names are never sent anywhere).
 * Common names come from a list so they get their usual spelling (محمد -> Muhammad); any other word is spelled out letter by letter
 * with vowels added by simple rules. The result is a good starting point, not a certainty: staff can correct it on the orphan's profile.
 */
final class ArabicNames
{
    private const WORDS = [
        'محمد' => 'Muhammad', 'احمد' => 'Ahmad', 'علي' => 'Ali', 'حسن' => 'Hassan', 'حسين' => 'Hussein', 'ابراهيم' => 'Ibrahim', 'اسماعيل' => 'Ismail',
        'يوسف' => 'Yusuf', 'عمر' => 'Omar', 'عثمان' => 'Uthman', 'خالد' => 'Khalid', 'خديجه' => 'Khadija', 'فاطمه' => 'Fatima', 'عايشه' => 'Aisha',
        'عائشه' => 'Aisha', 'مريم' => 'Maryam', 'زينب' => 'Zainab', 'سعيد' => 'Saeed', 'سالم' => 'Salim', 'ناصر' => 'Nasser', 'فيصل' => 'Faisal',
        'الله' => 'Allah', 'رحمن' => 'Rahman', 'رحيم' => 'Rahim', 'عزيز' => 'Aziz', 'كريم' => 'Karim', 'نور' => 'Nur', 'نوره' => 'Noura', 'سارة' => 'Sarah',
        'ساره' => 'Sarah', 'ليلي' => 'Layla', 'لينا' => 'Lina', 'منيه' => 'Munia', 'مني' => 'Mona', 'اسماء' => 'Asma', 'امينه' => 'Amina', 'هدي' => 'Huda',
        'رقيه' => 'Ruqayya', 'صفيه' => 'Safiya', 'حفصه' => 'Hafsa', 'سميه' => 'Sumaya', 'ايمان' => 'Iman', 'ندي' => 'Nada', 'شهد' => 'Shahd', 'ريم' => 'Reem', 'روان' => 'Rawan', 'جنه' => 'Jannah', 'غاليه' => 'Ghalia', 'عبيده' => 'Ubaida', 'ايه' => 'Aya', 'جميله' => 'Jamila',
        'محمود' => 'Mahmoud', 'مصطفي' => 'Mustafa', 'طارق' => 'Tariq', 'زيد' => 'Zaid', 'بلال' => 'Bilal', 'حمزه' => 'Hamza', 'ادريس' => 'Idris',
        'موسي' => 'Musa', 'عيسي' => 'Isa', 'داود' => 'Dawood', 'سليمان' => 'Sulaiman', 'يعقوب' => 'Yaqub', 'اسحاق' => 'Ishaq', 'هارون' => 'Harun',
        'نوح' => 'Nuh', 'ادم' => 'Adam', 'امين' => 'Amin', 'جمال' => 'Jamal', 'كمال' => 'Kamal', 'جلال' => 'Jalal', 'رشيد' => 'Rashid', 'حميد' => 'Hamid',
        'ماجد' => 'Majid', 'فهد' => 'Fahad', 'سلطان' => 'Sultan', 'سعود' => 'Saud', 'راشد' => 'Rashid', 'منصور' => 'Mansour', 'عادل' => 'Adel',
        'عماد' => 'Imad', 'ياسر' => 'Yasser', 'ياسين' => 'Yasin', 'انس' => 'Anas', 'اسامه' => 'Usama', 'اسلام' => 'Islam', 'سلمان' => 'Salman',
        'سلمي' => 'Salma', 'هاشم' => 'Hashim', 'هشام' => 'Hisham', 'جعفر' => 'Jafar', 'صالح' => 'Salih', 'ابو' => 'Abu', 'ام' => 'Umm', 'بن' => 'Bin',
        'بنت' => 'Bint', 'ابن' => 'Ibn', 'عبد' => 'Abd', 'مراد' => 'Murad', 'حليمه' => 'Halima', 'امل' => 'Amal', 'رنا' => 'Rana', 'دعاء' => 'Dua',
        'عمران' => 'Imran', 'سعد' => 'Saad', 'سعاد' => 'Suad', 'نادر' => 'Nader', 'نبيل' => 'Nabil', 'وليد' => 'Walid', 'ايوب' => 'Ayyub', 'زكريا' => 'Zakaria',
        'يحيي' => 'Yahya', 'ذاكر' => 'Zakir', 'صبري' => 'Sabri', 'فرج' => 'Faraj', 'عبيد' => 'Ubaid', 'عبدالله' => 'Abdullah',
    ];

    private const LETTERS = [
        'ب' => 'b', 'ت' => 't', 'ث' => 'th', 'ج' => 'j', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'dh', 'ر' => 'r', 'ز' => 'z', 'س' => 's', 'ش' => 'sh',
        'ص' => 's', 'ض' => 'd', 'ط' => 't', 'ظ' => 'z', 'غ' => 'gh', 'ف' => 'f', 'ق' => 'q', 'ك' => 'k', 'ل' => 'l', 'م' => 'm', 'ن' => 'n', 'ه' => 'h',
        'پ' => 'p', 'ڤ' => 'v', 'چ' => 'ch', 'گ' => 'g',
    ];

    public static function hasArabic(string $s): bool
    {
        return (bool) preg_match('/\p{Arabic}/u', $s);
    }

    public static function toEnglish(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? $name);
        if ($name === '' || ! self::hasArabic($name)) {
            return $name;
        }
        $words = explode(' ', $name);
        $out = [];
        for ($i = 0; $i < count($words); $i++) {
            $w = self::norm($words[$i]);
            if ($w === 'عبد' && isset($words[$i + 1])) {           // "عبد الله" written as two words
                $out[] = self::abdul(self::norm($words[++$i]));

                continue;
            }
            $out[] = self::word($w);
        }

        return trim(implode(' ', $out));
    }

    private static function word(string $w): string
    {
        if (isset(self::WORDS[$w])) {
            return self::WORDS[$w];
        }
        if (str_starts_with($w, 'عبد') && mb_strlen($w) > 4) {      // عبدالرحمن, عبدالعزيز ...
            return self::abdul(mb_substr($w, 3));
        }
        if (str_starts_with($w, 'ال') && mb_strlen($w) > 3) {        // family names such as الشحي
            return 'Al-'.self::word(mb_substr($w, 2));
        }
        if (str_starts_with($w, 'ابو') && mb_strlen($w) > 4) {
            return self::spell(mb_substr($w, 3)) ? 'Abu'.self::spell(mb_substr($w, 3)) : 'Abu';
        }

        return self::spell($w);
    }

    private static function abdul(string $rest): string
    {
        $rest = preg_replace('/^ال/u', '', $rest) ?? $rest;
        if ($rest === 'له' || $rest === 'الله' || $rest === 'ه') {
            return 'Abdullah';
        }
        $r = self::WORDS[$rest] ?? self::spell($rest);

        return 'Abdul'.strtolower($r);
    }

    /** Letter by letter, with an "a" between two consonants that have no vowel yet. */
    private static function spell(string $w): string
    {
        $chars = preg_split('//u', $w, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $n = count($chars);
        $o = '';
        $vowel = fn (string $c) => $c !== '' && str_contains('aeiou', $c);
        $isCons = fn (string $ch) => isset(self::LETTERS[$ch]);
        for ($i = 0; $i < $n; $i++) {
            $ch = $chars[$i];
            $last = $o === '' ? '' : substr($o, -1);
            $next = $chars[$i + 1] ?? '';
            if (isset(self::LETTERS[$ch])) {
                $o .= self::LETTERS[$ch];
                if ($ch === 'ه' && $i === $n - 1 && $n > 2) {                 // feminine ending: Fatimah -> Fatima
                    $o = substr($o, 0, -1).'a';
                } elseif ($next !== '' && $isCons($next) && ! $vowel($last)) {
                    $o .= 'a';
                }

                continue;
            }
            switch ($ch) {
                case 'ا': case 'أ': case 'آ': case 'ى':
                    if (! $vowel($last) || $i === 0) {
                        $o .= 'a';
                    }
                    break;
                case 'إ':
                    $o .= 'i';
                    break;
                case 'ع':
                    $o .= ($i === 0 || ! $vowel($last)) ? 'a' : '';
                    break;
                case 'و': case 'ؤ':
                    $o .= ($i === 0 || $vowel($next === '' ? '' : 'x') || ($next !== '' && ! $isCons($next) && $next !== 'ه')) ? 'w' : ($vowel($last) && $last === 'u' ? '' : 'u');
                    break;
                case 'ي': case 'ئ':
                    $o .= ($i === 0 || ($next !== '' && ! $isCons($next) && $next !== 'ه')) ? 'y' : ($last === 'i' ? '' : 'i');
                    break;
                case 'ة':
                    $o .= $vowel($last) ? '' : 'a';
                    break;
                default:      // hamza, tatweel, marks, punctuation and anything else
                    if (preg_match('/[A-Za-z0-9\-\'.]/u', $ch)) {
                        $o .= $ch;
                    }
            }
        }

        return ucfirst($o);
    }

    /** Arabic with vowel marks, tatweel and the different alef/ya/ta marbuta forms made equal, for matching. */
    private static function norm(string $s): string
    {
        $s = preg_replace('/[\x{064B}-\x{065F}\x{0640}]/u', '', $s) ?? $s;

        return strtr($s, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ى' => 'ي', 'ة' => 'ه']);
    }
}
