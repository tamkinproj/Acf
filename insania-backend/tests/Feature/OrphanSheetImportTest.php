<?php

namespace Tests\Feature;

use App\Models\Child;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;
use ZipArchive;

class OrphanSheetImportTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    /** A minimal .xlsx laid out like the office report sheets (title row, then the headings, then one row per orphan). */
    private function xlsx(string $title, array $rows, string $sheetName = 'Sheet1'): UploadedFile
    {
        $strings = [];
        $s = function (string $v) use (&$strings) {
            $i = array_search($v, $strings, true);
            if ($i === false) {
                $strings[] = $v;
                $i = count($strings) - 1;
            }

            return $i;
        };
        $cols = ['H' => 'م', 'G' => 'رقم اليتيم', 'F' => 'اسم اليتيم', 'E' => 'اسم الكافل', 'D' => 'رقم الكافل', 'C' => 'حالة التقرير', 'B' => 'سبب التأخر', 'A' => 'التاريخ المتوقع لإرسال التقرير'];
        $xml = '<row r="1"><c r="H1" t="s"><v>'.$s($title).'</v></c></row><row r="2">';
        foreach ($cols as $c => $h) {
            $xml .= '<c r="'.$c.'2" t="s"><v>'.$s($h).'</v></c>';
        }
        $xml .= '</row>';
        foreach ($rows as $n => $r) {
            $row = $n + 3;
            $xml .= '<row r="'.$row.'"><c r="H'.$row.'"><v>'.($n + 1).'</v></c>';
            $xml .= '<c r="G'.$row.'"><v>'.$r[0].'</v></c><c r="F'.$row.'" t="s"><v>'.$s($r[1]).'</v></c><c r="E'.$row.'" t="s"><v>'.$s($r[2]).'</v></c><c r="D'.$row.'"><v>'.$r[3].'</v></c>';
            if (isset($r[4])) {
                $xml .= '<c r="A'.$row.'"><v>'.$r[4].'</v></c>';
            }
            $xml .= '</row>';
        }
        $sst = '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'.implode('', array_map(fn ($v) => '<si><t>'.htmlspecialchars($v, ENT_XML1).'</t></si>', $strings)).'</sst>';
        $path = tempnam(sys_get_temp_dir(), 'x').'.xlsx';
        $z = new ZipArchive();
        $z->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $z->addFromString('[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"/>');
        $z->addFromString('xl/workbook.xml', '<?xml version="1.0"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="'.$sheetName.'" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $z->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="x" Target="worksheets/sheet1.xml"/></Relationships>');
        $z->addFromString('xl/sharedStrings.xml', $sst);
        $z->addFromString('xl/worksheets/sheet1.xml', '<?xml version="1.0"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.$xml.'</sheetData></worksheet>');
        $z->close();

        return new UploadedFile($path, 'report.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
    }

    private function post_(UploadedFile $f, array $extra = [])
    {
        return $this->post('/api/children/import', ['file' => $f] + $extra, ['Accept' => 'application/json']);
    }

    public function test_a_cash_sheet_is_read_checked_and_imported_without_duplicates(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        $before = Child::count();
        $title = 'كشف الأيتام المطلوب تقارير لهم - مؤسسة الإنسانية الخيرية - نقدي - عدد الأيتام (2 يتيم)';
        $rows = [[159494, 'نوردينه نورسين هارون أمسائن', 'ندى علي الشحي', 553162, 46387], [159495, 'سعيد أحمد', 'خالد محمد', '٥٥٣١٦٣']];

        $this->post_($this->xlsx($title, $rows), ['dry_run' => 1])->assertOk()
            ->assertJsonPath('data.dry_run', true)->assertJsonPath('data.new', 2)->assertJsonPath('data.sheets.0.pay_type', 'cash')->assertJsonPath('data.sheets.0.expected', 2);
        $this->assertSame($before, Child::count(), 'a check saves nothing');

        $this->post_($this->xlsx($title, $rows))->assertOk()->assertJsonPath('data.new', 2)->assertJsonPath('data.updated', 0);
        $this->assertSame($before + 2, Child::count());
        $c = Child::where('external_no', '159494')->firstOrFail();
        $this->assertSame('نوردينه نورسين هارون أمسائن', $c->name_ar);
        $this->assertSame('ندى علي الشحي', $c->sponsor_name);
        $this->assertSame('553162', $c->sponsor_file_no);
        $this->assertSame(\Carbon\Carbon::create(1899, 12, 30)->addDays(46387)->toDateString(), substr((string) $c->expected_send_date, 0, 10), 'an Excel date is read');
        $this->assertSame('cash', $c->sponsorship->pay_type);
        $this->assertSame('active', $c->sponsorship->status);
        $this->assertSame('553163', Child::where('external_no', '159495')->first()->sponsor_file_no, 'Arabic digits are read');

        // The same sheet again updates, never adds twice.
        $this->post_($this->xlsx($title, $rows))->assertOk()->assertJsonPath('data.new', 0)->assertJsonPath('data.updated', 2);
        $this->assertSame($before + 2, Child::count());
    }

    public function test_a_bank_sheet_is_marked_bank_and_a_sheet_without_a_clear_title_needs_a_choice(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        $bank = 'كشف الأيتام المطلوب تقارير لهم - مؤسسة الإنسانية الخيرية - الفلبين - بنكي - عدد الأيتام (1 يتيم)';
        $this->post_($this->xlsx($bank, [[700001, 'يتيم بنكي', 'كافل', 111]]))->assertOk()->assertJsonPath('data.sheets.0.pay_type', 'bank');
        $this->assertSame('bank', Child::where('external_no', '700001')->first()->sponsorship->pay_type);

        $plain = 'كشف الأيتام';
        $r = $this->post_($this->xlsx($plain, [[700002, 'يتيم', 'كافل', 112]]))->assertOk();
        $this->assertSame(0, $r->json('data.new'));
        $this->assertNotEmpty($r->json('data.sheets.0.errors'));
        $this->post_($this->xlsx($plain, [[700002, 'يتيم', 'كافل', 112]]), ['pay_type' => 'cash'])->assertOk()->assertJsonPath('data.new', 1);
    }

    public function test_only_staff_who_may_create_orphans_can_import_and_bad_files_are_refused(): void
    {
        $this->world();
        $title = 'كشف نقدي';
        $this->actingAsRole('staff:finance');
        $this->post_($this->xlsx($title, [[1, 'يتيم', 'كافل', 2]]))->assertForbidden();

        $this->actingAsRole('staff:aytam');
        $bad = UploadedFile::fake()->createWithContent('x.xlsx', 'PK-not-really-a-zip');
        $this->post_($bad)->assertStatus(422);
        $this->post_(UploadedFile::fake()->createWithContent('page.html', '<script>alert(1)</script>'))->assertStatus(422);
    }
}
