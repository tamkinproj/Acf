<?php

namespace Tests\Support;

use App\Support\Pdf\Dict;
use App\Support\Pdf\Document;
use App\Support\Pdf\Name;
use App\Support\Pdf\Ref;
use App\Support\Pdf\Str;

/**
 * A small stand-in for Dar Al Ber's report form, made for the tests: the same field names, lists and scripts' fields,
 * a JavaScript action, an old value left in one field (that must disappear), and optionally written the compact way
 * (objects packed in an object stream, a compressed cross-reference stream) like the real form.
 * Uses the DejaVu Sans font of the test machine for Arabic.
 */
final class FakeReportForm
{
    public const OLD = 'OLDNAME';

    public static function fontPath(): ?string
    {
        foreach (['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf', '/usr/share/fonts/dejavu/DejaVuSans.ttf'] as $p) {
            if (is_file($p)) {
                return $p;
            }
        }

        return null;
    }

    public static function build(bool $compact = false): string
    {
        $o = [];   // number => value (Dict, array, or ['stream' => Dict, 'data' => string])
        $n = 20;
        $widgets = [];
        $fields = [];
        $s = fn (string $t) => Str::text($t);
        $field = function (array $d, bool $widget = true) use (&$o, &$n, &$widgets, &$fields) {
            $num = $n++;
            $base = $widget ? ['Type' => new Name('Annot'), 'Subtype' => new Name('Widget'), 'P' => new Ref(3), 'F' => 4] : [];
            $o[$num] = new Dict($base + $d);
            if ($widget) {
                $widgets[] = new Ref($num);
            }
            $fields[] = new Ref($num);

            return $num;
        };
        $y = 800;
        $rect = function () use (&$y) {
            $y -= 22;

            return [30, $y, 300, $y + 20];
        };
        $tx = fn (string $name, string $font = 'DejaVu', int $q = 2, int $ff = 0, array $extra = []) => $field($extra + ['T' => new Str($name), 'FT' => new Name('Tx'), 'Rect' => $rect(), 'DA' => new Str('/'.$font.' 0 Tf 0 g'), 'Q' => $q, 'Ff' => $ff]);

        $tx('Number1', 'Helv', 1);
        foreach (['Text1', 'Text3', 'Text4', 'Text6', 'Text11', 'Text15', 'Text17', 'Text18', 'Text19', 'Text21', 'Text23'] as $t) {
            $tx($t);
        }
        // an old value with its old picture: both must be gone after filling
        $oldAp = $n++;
        $o[$oldAp] = ['stream' => new Dict(['Type' => new Name('XObject'), 'Subtype' => new Name('Form'), 'BBox' => [0, 0, 270, 20], 'Resources' => new Dict(['Font' => new Dict(['Helv' => new Ref(8)])])]), 'data' => 'BT /Helv 10 Tf 2 5 Td ('.self::OLD.') Tj ET'];
        $tx('Text5', 'DejaVu', 2, 0, ['V' => new Str(self::OLD), 'AP' => new Dict(['N' => new Ref($oldAp)])]);
        $tx('Text22', 'DejaVu', 2, 1 << 25, ['V' => new Str(self::OLD), 'RV' => new Str('<body><p>'.self::OLD.'</p></body>'), 'DS' => new Str('font-family: Dubai Medium;font-size: 10.00pt;'), 'Rect' => [30, 60, 560, 110]]);
        $tx('BirthDate', 'Helv', 1);
        $tx('Age', 'Helv', 1);
        $combo = 1 << 17;
        $lists = [
            'Dropdown1' => ['-- اختر الجنس --', 'ذكر', 'أنثى'],
            'Dropdown2' => ['--- اختر من القائمة ---', 'جيدة', 'مريض', 'صاحب همة'],
            'Dropdown3' => ['--- آختر من القائمة ---', 'رياض الأطفال', 'الابتدائية', 'الإعدادية', 'الثانوية', 'الجامعة', 'لا يدرس', 'طفل', 'يحفظ القرآن الكريم'],
            'Dropdown4' => ['---', 'روضة أولى', 'روضة ثانية', 'الأول', 'الثاني', 'الثالث', 'الرابع', 'الخامس', 'السادس', 'السابع', 'الثامن', 'التاسع', 'العاشر', 'الحادي عشر', 'الثاني عشر', 'التعليم العالي'],
            'Dropdown5' => ['---', 'ممتاز   ★ ★ ★', 'متوسط   ★  ★', 'مقبول   ★  '],
            'Dropdown6' => ['---', 'ممتاز', 'جيد', 'يتعلم', 'طفل'],
        ];
        foreach ($lists as $name => $opts) {
            $opt = array_map($s, $opts);
            if ($name === 'Dropdown1') {   // like the real form: a field with one widget under it
                $parent = $n++;
                $kid = $n++;
                $o[$parent] = new Dict(['T' => new Str($name), 'FT' => new Name('Ch'), 'Ff' => $combo, 'Opt' => $opt, 'DA' => new Str('/DejaVu 0 Tf 0 g'), 'Kids' => [new Ref($kid)]]);
                $o[$kid] = new Dict(['Type' => new Name('Annot'), 'Subtype' => new Name('Widget'), 'Parent' => new Ref($parent), 'P' => new Ref(3), 'F' => 4, 'Rect' => $rect(), 'Q' => 2, 'MK' => new Dict(['BG' => [1]])]);
                $widgets[] = new Ref($kid);
                $fields[] = new Ref($parent);

                continue;
            }
            $field(['T' => new Str($name), 'FT' => new Name('Ch'), 'Ff' => $combo, 'Opt' => $opt, 'Rect' => $rect(), 'DA' => new Str('/DejaVu 0 Tf 0 g'), 'Q' => 2, 'MK' => new Dict(['BG' => [1]])]);
        }
        $countries = [['', '--- اختر الدولة / Select Country ---'], ['PH', 'الفلبين - Philippines'], ['ID', 'إندونيسيا - Indonesia'], ['AE', 'الإمارات - UAE']];
        foreach (['Country', 'Nationality'] as $name) {
            $field(['T' => new Str($name), 'FT' => new Name('Ch'), 'Ff' => $combo, 'Opt' => array_map(fn ($c) => [new Str($c[0]), $s($c[1])], $countries), 'V' => new Str('ID'), 'I' => [2], 'Rect' => $rect(), 'DA' => new Str('/DejaVu 10 Tf 0 g'), 'Q' => 2]);
        }
        foreach (['PH', 'ID', 'AE', 'Nat_PH', 'Nat_ID', 'Nat_AE', 'Female_Logo', 'Icon_Good', 'Icon_Sick', 'Icon_Determined'] as $b) {
            $field(['T' => new Str($b), 'FT' => new Name('Btn'), 'Ff' => 65536, 'Rect' => [400, 700, 420, 720], 'F' => in_array($b, ['ID', 'Nat_ID'], true) ? 4 : 6]);
        }
        foreach (['Male_Logo', 'Image1_af_image'] as $b) {
            $parent = $n++;
            $kid = $n++;
            $o[$parent] = new Dict(['T' => new Str($b), 'FT' => new Name('Btn'), 'Ff' => 65536, 'Kids' => [new Ref($kid)]]);
            $o[$kid] = new Dict(['Type' => new Name('Annot'), 'Subtype' => new Name('Widget'), 'Parent' => new Ref($parent), 'P' => new Ref(3), 'F' => 4,
                'Rect' => $b === 'Image1_af_image' ? [330, 560, 530, 830] : [430, 700, 450, 720], 'MK' => new Dict(['IF' => new Dict(), 'TP' => 1])]
                + ($b === 'Image1_af_image' ? ['A' => new Dict(['S' => new Name('JavaScript'), 'JS' => new Str('event.target.buttonImportIcon();')])] : []));
            $widgets[] = new Ref($kid);
            $fields[] = new Ref($parent);
        }

        $o[1] = new Dict(['Type' => new Name('Catalog'), 'Pages' => new Ref(2), 'AcroForm' => new Ref(4), 'Names' => new Dict(['JavaScript' => new Ref(6)])]);
        $o[2] = new Dict(['Type' => new Name('Pages'), 'Kids' => [new Ref(3)], 'Count' => 1]);
        $o[3] = new Dict(['Type' => new Name('Page'), 'Parent' => new Ref(2), 'MediaBox' => [0, 0, 595, 842], 'Contents' => new Ref(5), 'Resources' => new Dict(), 'Annots' => $widgets]);
        $o[4] = new Dict(['Fields' => $fields, 'DR' => new Dict(['Font' => new Dict(['DejaVu' => new Ref(7), 'Helv' => new Ref(8)])]), 'DA' => new Str('/Helv 0 Tf 0 g')]);
        $o[5] = ['stream' => new Dict([]), 'data' => "0.85 0.55 0.25 rg 20 812 555 20 re f\n0.2 0.4 0.8 rg 20 20 555 20 re f\n"];
        $o[6] = new Dict(['Names' => [new Str('Hello'), new Ref(9)]]);
        $o[9] = new Dict(['S' => new Name('JavaScript'), 'JS' => new Str('var formScriptStays = 1;')]);
        $ttf = (string) @file_get_contents((string) self::fontPath());
        $o[7] = new Dict(['Type' => new Name('Font'), 'Subtype' => new Name('TrueType'), 'BaseFont' => new Name('DejaVuSans'), 'FirstChar' => 32, 'LastChar' => 126,
            'Widths' => array_fill(0, 95, 600), 'FontDescriptor' => new Ref(10), 'Encoding' => new Name('WinAnsiEncoding')]);
        $o[10] = new Dict(['Type' => new Name('FontDescriptor'), 'FontName' => new Name('DejaVuSans'), 'Flags' => 32, 'FontBBox' => [-1021, -463, 1793, 1232], 'Ascent' => 928, 'Descent' => -236,
            'CapHeight' => 729, 'ItalicAngle' => 0, 'StemV' => 80, 'FontFile2' => new Ref(11)]);
        $o[11] = ['stream' => new Dict(['Length1' => strlen($ttf), 'Filter' => new Name('FlateDecode')]), 'data' => (string) gzcompress($ttf)];
        $o[8] = new Dict(['Type' => new Name('Font'), 'Subtype' => new Name('Type1'), 'BaseFont' => new Name('Helvetica'), 'FirstChar' => 32, 'LastChar' => 126, 'Widths' => array_fill(0, 95, 556)]);
        ksort($o);

        return $compact ? self::compact($o) : self::plain($o);
    }

    private static function body(int $num, mixed $v): string
    {
        if (is_array($v) && isset($v['stream'])) {
            $d = $v['stream'];
            $d->v['Length'] = strlen($v['data']);

            return "{$num} 0 obj\n".Document::ser($d)."\nstream\n".$v['data']."\nendstream\nendobj\n";
        }

        return "{$num} 0 obj\n".Document::ser($v)."\nendobj\n";
    }

    private static function plain(array $o): string
    {
        $out = "%PDF-1.6\n%\xE2\xE3\xCF\xD3\n";
        $off = [];
        foreach ($o as $num => $v) {
            $off[$num] = strlen($out);
            $out .= self::body($num, $v);
        }
        $size = max(array_keys($o)) + 1;
        $x = strlen($out);
        $out .= "xref\n0 {$size}\n0000000000 65535 f \n";
        for ($i = 1; $i < $size; $i++) {
            $out .= isset($off[$i]) ? sprintf("%010d 00000 n \n", $off[$i]) : "0000000000 00001 f \n";
        }

        return $out."trailer\n<< /Size {$size} /Root 1 0 R >>\nstartxref\n{$x}\n%%EOF\n";
    }

    /** Objects packed into an object stream, and a compressed cross-reference stream with PNG "up" prediction. */
    private static function compact(array $o): string
    {
        $out = "%PDF-1.6\n%\xE2\xE3\xCF\xD3\n";
        $packed = array_filter($o, fn ($v) => ! (is_array($v) && isset($v['stream'])));
        $loose = array_diff_key($o, $packed);
        $stmNum = max(array_keys($o)) + 1;
        $xrefNum = $stmNum + 1;
        $head = '';
        $bodyText = '';
        $idx = [];
        $i = 0;
        foreach ($packed as $num => $v) {
            $head .= $num.' '.strlen($bodyText).' ';
            $bodyText .= Document::ser($v)."\n";
            $idx[$num] = $i++;
        }
        $first = strlen($head);
        $stm = $head.$bodyText;
        $entries = [];
        foreach ($loose as $num => $v) {
            $entries[$num] = [1, strlen($out), 0];
            $out .= self::body($num, $v);
        }
        $entries[$stmNum] = [1, strlen($out), 0];
        $z = (string) gzcompress($stm);
        $out .= "{$stmNum} 0 obj\n<< /Type /ObjStm /N ".count($packed)." /First {$first} /Filter /FlateDecode /Length ".strlen($z)." >>\nstream\n{$z}\nendstream\nendobj\n";
        foreach ($idx as $num => $k) {
            $entries[$num] = [2, $stmNum, $k];
        }
        $entries[$xrefNum] = [1, strlen($out), 0];
        $size = $xrefNum + 1;
        $rows = '';
        $prev = str_repeat("\0", 7);
        for ($k = 0; $k < $size; $k++) {
            $e = $entries[$k] ?? [0, 0, 0];
            $row = chr($e[0]).pack('N', $e[1]).pack('n', $e[2]);
            $up = '';
            for ($b = 0; $b < 7; $b++) {
                $up .= chr((ord($row[$b]) - ord($prev[$b])) & 0xFF);
            }
            $rows .= "\x02".$up;
            $prev = $row;
        }
        $z = (string) gzcompress($rows);
        $x = strlen($out);
        $out .= "{$xrefNum} 0 obj\n<< /Type /XRef /Size {$size} /W [1 4 2] /Root 1 0 R /Filter /FlateDecode /DecodeParms << /Predictor 12 /Columns 7 >> /Length ".strlen($z)." >>\nstream\n{$z}\nendstream\nendobj\n";

        return $out."startxref\n{$x}\n%%EOF\n";
    }
}
