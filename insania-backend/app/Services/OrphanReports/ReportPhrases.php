<?php

namespace App\Services\OrphanReports;

/**
 * Writes the three free-text answers of the yearly report in Arabic, from what the system knows about the orphan:
 *  - changes: the most notable changes this year (one short line)
 *  - comment: the organization's comment on what the sponsorship did
 *  - message: a message to the sponsor, from the orphan (old enough) or from the person responsible
 * Nothing is sent anywhere: the sentences are put together here from many written variants, and every orphan in
 * the same year gets a different text (see ReportTexts).
 * In the variants, {a|b} is the word for a boy | a girl, and :name, :grade, :stage, :quran are filled in.
 */
final class ReportPhrases
{
    public const MAX = ['changes' => 80, 'comment' => 210, 'message' => 210];

    // ---------------------------------------------------------------- the organization's comment
    private const COMMENT_OPEN = [
        'أسهمت الكفالة في استقرار :name الدراسي وتوفير احتياجات{ه|ها} الأساسية',
        'ساعدت الكفالة :name على الانتظام في الدراسة وتلبية متطلبات{ه|ها} المدرسية',
        'كان للكفالة أثر واضح في تحسين الظروف المعيشية لأسرة :name',
        'خففت الكفالة عن أسرة :name كثيرًا من أعباء المعيشة',
        'مكّنت الكفالة :name من مواصلة تعليم{ه|ها} دون انقطاع',
        'وفّرت الكفالة احتياجات :name من الغذاء والملبس والأدوات المدرسية',
        'أعانت الكفالة أسرة :name على تغطية مصاريف الدراسة والمعيشة',
        'منحت الكفالة :name شعورًا بالأمان والاستقرار',
        'أثمرت الكفالة تحسنًا ملحوظًا في حياة :name الدراسية والمعيشية',
        'كانت الكفالة سندًا حقيقيًا ل:name وأسرت{ه|ها} طوال هذا العام',
        'ظهر أثر الكفالة جليًا في استقرار حياة :name وانتظام{ه|ها} في الدراسة',
        'ساهمت الكفالة في تأمين المستلزمات الدراسية ل:name وتحسين معيشة أسرت{ه|ها}',
    ];

    private const COMMENT_YOUNG_OPEN = [
        'وفّرت الكفالة ل:name الغذاء والرعاية الصحية اللازمة في هذه المرحلة من عمر{ه|ها}',
        'ساعدت الكفالة أسرة :name على توفير احتياجات{ه|ها} اليومية من غذاء ورعاية',
        'أسهمت الكفالة في نمو :name في بيئة مستقرة وآمنة',
        'خففت الكفالة عن أسرة :name أعباء الرعاية والمعيشة',
        'منحت الكفالة :name وأسرت{ه|ها} قدرًا كبيرًا من الطمأنينة والاستقرار',
    ];

    private const COMMENT_STUDY = [
        'high' => ['و{هو|هي} اليوم من المتفوقين في :grade', 'و{هو|هي} {يحافظ|تحافظ} على مستوى دراسي ممتاز', 'و{هو|هي} الآن أكثر تفوقًا في دراست{ه|ها}', 'و{هو|هي} {يحقق|تحقق} نتائج متميزة في :grade'],
        'mid' => ['وتحسّن مستوا{ه|ها} الدراسي تدريجيًا', 'و{هو|هي} {يواصل|تواصل} دراست{ه|ها} في :grade بجدية', 'و{هو|هي} {يتقدم|تتقدم} في دراست{ه|ها} بخطى ثابتة', 'و{هو|هي} {يحرص|تحرص} على أداء واجبات{ه|ها} المدرسية'],
        'low' => ['و{هو|هي} {يحتاج|تحتاج} إلى مزيد من المتابعة الدراسية، ونحن نتابع{ه|ها} باستمرار', 'ونعمل مع أسرت{ه|ها} على تحسين مستوا{ه|ها} الدراسي'],
        'none' => ['و{هو|هي} {ينمو|تنمو} في بيئة أسرية مستقرة', 'و{هو|هي} {يحظى|تحظى} برعاية جيدة من أسرت{ه|ها}', 'و{هو|هي} {يتلقى|تتلقى} الرعاية والاهتمام من أسرت{ه|ها}'],
    ];

    private const COMMENT_HEALTH = [
        'good' => ['كما أن{ه|ها} {يتمتع|تتمتع} بصحة جيدة', 'كما أن{ه|ها} بصحة جيدة والحمد لله'],
        'sick' => ['كما ساعدت الكفالة في متابعة علاج{ه|ها} وتوفير الأدوية اللازمة', 'وأعانت الكفالة أسرت{ه|ها} على تكاليف العلاج'],
        'determined' => ['كما أعانت{ه|ها} الكفالة على تجاوز صعوبات وضع{ه|ها} الصحي', 'وساعدت الكفالة في توفير ما {يحتاجه|تحتاجه} من رعاية خاصة'],
    ];

    private const COMMENT_CLOSE = [
        'ونسأل الله أن يبارك في هذا العطاء.',
        'ونشكر للكافل الكريم هذا الأثر الطيب.',
        'ويظهر ذلك في ثقت{ه|ها} بنفس{ه|ها} وحسن سلوك{ه|ها}.',
        'وهذا بفضل الله ثم بفضل دعم الكافل.',
        'ونأمل أن يستمر هذا الأثر الطيب.',
        'جزى الله الكافل خير الجزاء.',
        'وننقل للكافل شكر الأسرة وتقديرها.',
        'ونسأل الله أن يجعل ذلك في ميزان حسنات الكافل.',
    ];

    // ---------------------------------------------------------------- the message to the sponsor
    private const MSG_CHILD_OPEN = [
        'شكرًا لكم من القلب على دعمكم المستمر',
        'أشكركم جزيل الشكر على كفالتكم ووقوفكم معي',
        'أتقدم إليكم بخالص الشكر والامتنان على رعايتكم لي',
        'لا أجد كلمات تكفي لشكركم على ما قدمتموه لي',
        'أشكركم كثيرًا لأنكم كنتم سببًا في استمراري في الدراسة',
        'جزاكم الله خيرًا على كفالتكم لي ولأسرتي',
        'أبعث إليكم بتحية طيبة وشكر كبير على عطائكم',
        'أنا {ممتنّ|ممتنّة} لكم كثيرًا على كل ما تقدمونه لي',
    ];

    private const MSG_CHILD_MID = [
        'أنا الآن في :grade وأجتهد في دروسي',
        'وأعدكم أن أجتهد في دراستي لأكون عند حسن ظنكم',
        'وقد حفظت :quran من القرآن الكريم',
        'وأحلم أن أصبح {نافعًا|نافعة} لأسرتي ومجتمعي',
        'وأنا بخير والحمد لله',
        'وسأبذل جهدي لأحقق أحلامي بإذن الله',
        'وأنا أدعو لكم في كل صلاة',
        'ودعمكم يشجعني على الاجتهاد أكثر',
        'وقد تحسنت حياتي وحياة أسرتي بفضل الله ثم بفضلكم',
        'وأتمنى أن ألقاكم يومًا لأشكركم بنفسي',
    ];

    private const MSG_ADULT_OPEN = [
        'نشكركم على كفالة :name ورعايتكم ل{ه|ها}',
        'أتقدم إليكم بالشكر الجزيل على وقوفكم مع :name',
        'باسمي وباسم :name نشكركم على دعمكم الكريم',
        'كانت كفالتكم عونًا كبيرًا لنا في تربية :name',
        'لقد خففتم عنا الكثير بكفالتكم ل:name',
        'نبعث إليكم بأطيب التحيات وأصدق عبارات الشكر على كفالة :name',
        'نشكر لكم هذا العطاء الذي غيّر حياة :name للأفضل',
        'نتوجه إليكم بخالص الشكر على رعايتكم ل:name',
        'أشكركم من أعماق قلبي على كفالة :name',
    ];

    private const MSG_ADULT_MID = [
        ':name الآن في :grade و{يجتهد|تجتهد} في دروس{ه|ها}',
        'و{هو|هي} بصحة جيدة والحمد لله',
        'و{هو|هي} {يحفظ|تحفظ} :quran من القرآن الكريم',
        'وقد تحسنت أحوالنا كثيرًا بفضل الله ثم بفضلكم',
        'و{هو|هي} {ينمو|تنمو} بصحة وعافية والحمد لله',
        'ونحن نرى أثر دعمكم في حيات{ه|ها} كل يوم',
        'و{هو|هي} {يذكركم|تذكركم} دائمًا بالخير و{يدعو|تدعو} لكم',
        'وقد أدخلتم السرور على قلوبنا',
        'فقد كان لعطائكم أثر كبير في حياتنا',
    ];

    private const MSG_CLOSE = [
        'أدعو الله أن يجزيكم خيرًا ويبارك في أهلكم ومالكم.',
        'أسأل الله أن يحفظكم ويجعل ذلك في ميزان حسناتكم.',
        'دمتم بخير وعافية.',
        'أسأل الله أن يرزقكم السعادة في الدنيا والآخرة.',
        'وجزاكم الله خير الجزاء.',
        'بارك الله فيكم وفي أعمالكم.',
        'نسأل الله أن يديم عليكم الصحة والعافية.',
        'وفقكم الله لكل خير.',
        'أسأل الله أن يبارك لكم في أعماركم وأرزاقكم.',
        'حفظكم الله ورعاكم.',
    ];

    // ---------------------------------------------------------------- notable changes this year
    private const CHANGES = [
        'grade' => ['انتقل{|ت} إلى :grade بنجاح', 'نجح{|ت} وانتقل{|ت} إلى :grade', 'التحق{|ت} ب:grade', 'انتقل{|ت} :name إلى :grade', '{أنهى|أنهت} عام{ه|ها} الدراسي وانتقل{|ت} إلى :grade', 'بدأ{|ت} :name الدراسة في :grade'],
        'high' => ['تحسّن مستوا{ه|ها} الدراسي', 'حقق{|ت} نتائج دراسية متميزة', 'ازداد{|ت} تفوقًا في دراست{ه|ها}', 'حصل{|ت} :name على درجات عالية', 'أصبح{|ت} من المتفوقين في صف{ه|ها}'],
        'mid' => ['أصبح{|ت} أكثر انتظامًا في الحضور المدرسي', 'زادت مشاركت{ه|ها} في الأنشطة المدرسية', 'تحسّن اهتمام{ه|ها} بالواجبات المدرسية', 'تحسّن مستوى :name في القراءة والكتابة', 'أصبح{|ت} أكثر اهتمامًا بدروس{ه|ها}'],
        'quran' => ['أتمّ{|ت} حفظ :quran من القرآن الكريم', 'تقدّم{|ت} في حفظ القرآن الكريم', 'واظب{|ت} :name على حلقات تحفيظ القرآن'],
        'good' => ['تحسّنت حالت{ه|ها} الصحية والنفسية', 'تحسّن سلوك{ه|ها} وثقت{ه|ها} بنفس{ه|ها}', 'أصبح{|ت} أكثر ثقة وتفاعلًا مع من حول{ه|ها}', 'ظهر{|ت} :name أكثر سعادة واستقرارًا', 'تحسّنت علاقات{ه|ها} مع زملائ{ه|ها}'],
        'sick' => ['تابع{|ت} علاج{ه|ها} بانتظام وتحسّنت حالت{ه|ها}', 'استقرت حالت{ه|ها} الصحية مع المتابعة', 'انتظم{|ت} :name في مواعيد العلاج', 'تحسّنت حالة :name الصحية مع العلاج'],
        'young' => ['{ينمو|تنمو} بشكل طبيعي و{يتمتع|تتمتع} بصحة جيدة', 'زاد وزن{ه|ها} وتحسّنت صحت{ه|ها} العامة', 'بدأ{|ت} {يتعلم|تتعلم} الكلام والحروف الأولى', 'أصبح{|ت} :name أكثر نشاطًا وحيوية', '{يتطور|تتطور} نمو :name بشكل سليم'],
    ];

    /** Words that can end the line, so two orphans with the same news still read differently. */
    private const CHANGES_END = ['', ' هذا العام', ' خلال هذه السنة', ' والحمد لله', ' بفضل الله', ' بفضل الله ثم الكفالة'];

    /**
     * @param  array{female:bool, name:string, grade:?string, stage:?string, quran:?string, level:string, health:string, young:bool, child_voice:bool}  $p
     * @return array{0:string, 1:string} the text, and a short code of which variants were used
     */
    public static function make(string $kind, array $p, int $seed): array
    {
        $rnd = new Rand($seed);

        return match ($kind) {
            'comment' => self::comment($p, $rnd),
            'message' => self::message($p, $rnd),
            default => self::changes($p, $rnd),
        };
    }

    private static function comment(array $p, Rand $r): array
    {
        $open = $p['young'] ? self::COMMENT_YOUNG_OPEN : self::COMMENT_OPEN;
        $i = $r->pick(count($open));
        $study = self::COMMENT_STUDY[$p['young'] || $p['level'] === 'none' ? 'none' : $p['level']];
        $j = $r->pick(count($study));
        $health = self::COMMENT_HEALTH[$p['health']] ?? self::COMMENT_HEALTH['good'];
        $k = $r->pick(count($health));
        $c = $r->pick(count(self::COMMENT_CLOSE));
        $useHealth = $r->pick(2) === 0;
        $parts = [self::fill($open[$i], $p).'، '.self::fill($study[$j], $p)];
        if ($useHealth) {
            $parts[0] .= '، '.self::fill($health[$k], $p);
        }
        $text = $parts[0].'. '.self::fill(self::COMMENT_CLOSE[$c], $p);
        if (mb_strlen($text) > self::MAX['comment']) {
            $text = $parts[0].'.';
        }

        return [self::tidy($text), "c{$i}.{$j}.".($useHealth ? $k : '-').".{$c}"];
    }

    private static function message(array $p, Rand $r): array
    {
        $child = $p['child_voice'];
        $open = $child ? self::MSG_CHILD_OPEN : self::MSG_ADULT_OPEN;
        $mid = array_values(array_filter($child ? self::MSG_CHILD_MID : self::MSG_ADULT_MID, fn ($s) => self::usable($s, $p)));
        $i = $r->pick(count($open));
        $j = $mid ? $r->pick(count($mid)) : -1;
        $c = $r->pick(count(self::MSG_CLOSE));
        // the closing prayer never repeats the opening one (جزاكم الله ... وجزاكم الله)
        $stem = fn (string $x) => preg_match('/جزا|يجزي/u', $x) ? 'j' : (preg_match('/بارك/u', $x) ? 'b' : (preg_match('/حفظ/u', $x) ? 'h' : null));
        for ($k = 0; $k < count(self::MSG_CLOSE) && $stem($open[$i]) !== null && $stem(self::MSG_CLOSE[$c]) === $stem($open[$i]); $k++) {
            $c = ($c + 1) % count(self::MSG_CLOSE);
        }
        $text = self::fill($open[$i], $p);
        if ($j >= 0) {
            $m = self::fill($mid[$j], $p);
            $text .= str_starts_with($m, 'و') || str_starts_with($m, 'ف') ? '، '.$m : '. '.$m;
        }
        $text .= '. '.self::fill(self::MSG_CLOSE[$c], $p);
        if (mb_strlen($text) > self::MAX['message']) {
            $text = self::fill($open[$i], $p).'. '.self::fill(self::MSG_CLOSE[$c], $p);
        }

        return [self::tidy($text), "m{$i}.{$j}.{$c}"];
    }

    private static function changes(array $p, Rand $r): array
    {
        $pool = [];
        if ($p['young']) {
            $pool[] = 'young';
            if ($p['health'] !== 'good') {
                $pool[] = 'sick';
            }
        } else {
            if ($p['grade']) {
                $pool[] = 'grade';
            }
            $pool[] = $p['level'] === 'high' ? 'high' : 'mid';
            if ($p['quran']) {
                $pool[] = 'quran';
            }
            $pool[] = $p['health'] === 'good' ? 'good' : 'sick';
        }
        $a = $pool[$r->pick(count($pool))];
        $i = $r->pick(count(self::CHANGES[$a]));
        $text = self::fill(self::CHANGES[$a][$i], $p);
        $rest = array_values(array_diff($pool, [$a]));
        $code = "x{$a}{$i}";
        if ($rest && $r->pick(3) > 0) {
            $b = $rest[$r->pick(count($rest))];
            $k = $r->pick(count(self::CHANGES[$b]));
            $second = self::fill(self::CHANGES[$b][$k], $p);
            $firstWord = fn (string $x) => preg_split('/\s+/u', $x)[0];
            $ok = ! str_contains(self::CHANGES[$b][$k], ':name') && $firstWord($second) !== $firstWord($text);
            $two = $text.' و'.$second;
            if ($ok && mb_strlen($two) < self::MAX['changes'] - 12) {
                [$text, $code] = [$two, $code."+{$b}{$k}"];
            }
        }
        $e = $r->pick(count(self::CHANGES_END));
        if (mb_strlen($text.self::CHANGES_END[$e]) < self::MAX['changes']) {
            $text .= self::CHANGES_END[$e];
            $code .= "e{$e}";
        }

        return [self::tidy($text.'.'), $code];
    }

    /** A variant that needs something the record does not have (a grade, the Quran memorized) is not used. */
    private static function usable(string $s, array $p): bool
    {
        return (! str_contains($s, ':grade') || $p['grade']) && (! str_contains($s, ':quran') || $p['quran']) && (! str_contains($s, 'دروس') || ! $p['young']) && (! str_contains($s, 'بصحة') || $p['health'] === 'good');
    }

    private static function fill(string $s, array $p): string
    {
        $s = preg_replace_callback('/\{([^|{}]*)\|([^|{}]*)\}/u', fn ($m) => $p['female'] ? $m[2] : $m[1], $s);

        // "ل" and "ب" join the word after them: لفاطمة, بالصف; before "ال" the lam doubles (للـ), before a Latin name it stays apart
        $s = preg_replace_callback('/(?<![\p{Arabic}])([لب]):(name|grade)/u', function ($m) use ($p) {
            $w = (string) ($p[$m[2]] ?? '');
            if (! preg_match('/^\p{Arabic}/u', $w)) {
                return $m[1].'ـ '.$w;
            }

            return $m[1] === 'ل' && str_starts_with($w, 'ال') ? 'لل'.mb_substr($w, 2) : $m[1].$w;
        }, $s);

        return strtr($s, [':name' => $p['name'], ':grade' => (string) ($p['grade'] ?? ''), ':stage' => (string) ($p['stage'] ?? ''), ':quran' => (string) ($p['quran'] ?? '')]);
    }

    private static function tidy(string $s): string
    {
        $s = preg_replace('/\s+/u', ' ', $s);
        $s = preg_replace('/\.{2,}/u', '.', $s);

        return trim($s);
    }
}
