<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\OrphanReport;
use App\Services\OrphanReports\DarAlBerReport;
use App\Services\OrphanReports\ReportTexts;
use App\Support\Pdf\Arabic;
use App\Support\Pdf\Document;
use App\Support\Pdf\Form;
use App\Support\Pdf\Stream;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\Support\FakeReportForm;
use Tests\TestCase;

/**
 * Dar Al Ber's periodic orphan report: the PDF form is only filled (its fields), never changed otherwise; the texts are
 * written by the system and differ from orphan to orphan; who may see, write, approve and add the form.
 */
class DarAlBerReportTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        if (! FakeReportForm::fontPath()) {
            $this->markTestSkipped('The DejaVu Sans font is needed to make the test form.');
        }
        Storage::fake('local');
    }

    /** Every object's text and unpacked streams, to look for leftovers. */
    private function everything(string $pdf): string
    {
        $d = Document::load($pdf);
        preg_match_all('/(\d+) 0 obj/', $pdf, $m);
        $all = '';
        foreach ($m[1] as $n) {
            $o = $d->get((int) $n);
            $all .= Document::ser($o instanceof Stream ? $o->dict : $o);
            if ($o instanceof Stream) {
                try {
                    $all .= $o->decoded();
                } catch (\Throwable) {
                }
            }
        }

        return $all;
    }

    private function widgetFlags(Form $f, string $name, Document $d): int
    {
        $ref = (new \ReflectionProperty(Form::class, 'fields'))->getValue($f)[$name]['widgets'][0];

        return (int) $d->get($ref)->get('F');
    }

    public function test_the_pdf_reader_opens_both_ways_of_writing_a_pdf_and_writes_it_back_the_same(): void
    {
        foreach ([false, true] as $compact) {
            $pdf = FakeReportForm::build($compact);
            $doc = Document::load($pdf);
            $form = new Form($doc);
            $this->assertContains('Text22', $form->names());
            $this->assertContains('Male_Logo', $form->names(), 'a field with its widget under it');
            $this->assertSame(FakeReportForm::OLD, $form->value('Text5'));
            $this->assertSame(['PH', 'الفلبين - Philippines'], $form->options('Country')[1]);
            $again = Document::load($doc->save());
            $this->assertSame(count($form->names()), count((new Form($again))->names()));
            $this->assertSame('ID', (new Form($again))->value('Country'));
        }
        // a damaged index at the end of the file: the objects are found by reading the whole file
        $broken = preg_replace('/startxref\s+\d+/', 'startxref 999999999', FakeReportForm::build(false));
        $this->assertSame(FakeReportForm::OLD, (new Form(Document::load($broken)))->value('Text5'));
    }

    public function test_arabic_letters_are_joined_and_put_in_reading_order(): void
    {
        $all = fn () => true;
        $flat = fn (string $s, bool $rtl = false) => array_map(fn ($cl) => $cl[0][0], Arabic::visual($s, $all, $rtl));
        // سلام: seen (start), lam-alef (end form, one sign), meem alone: drawn right to left
        $this->assertSame([0x0645, 0xFEFC, 0xFEB3], $flat('سلام'));
        // numbers keep their order inside Arabic text
        $v = $flat('عمر 12 سنة');
        $this->assertSame([0x31, 0x32], array_values(array_filter($v, fn ($c) => $c === 0x31 || $c === 0x32)));
        $this->assertGreaterThan(array_search(0x31, $v, true), array_search(0xFECB, $v, true), 'the first word (عمر) is drawn to the right of the number');
        // English stays left to right; a date with no letters too
        $this->assertSame([0x41, 0x42], $flat('AB'));
        $this->assertSame(array_map('ord', str_split('03-05-2014')), $flat('03-05-2014'));
        // a font without the joined shapes: the plain letter is used
        $plain = array_map(fn ($cl) => $cl[0][0], Arabic::visual('بب', fn ($c) => $c < 0xFB00));
        $this->assertSame([0x0628, 0x0628], $plain);
    }

    public function test_filling_writes_only_the_fields_and_leaves_nothing_of_the_old_copy(): void
    {
        $tpl = Document::load(FakeReportForm::build(true));
        $f = [
            'year' => 2026, 'org_name' => 'مؤسسة الانسانية الخيرية', 'country' => 'PH', 'sponsor_name' => 'أحمد الكعبي', 'sponsor_file_no' => '204871', 'name' => 'فاطمة عبدالله إبراهيم',
            'file_no' => '160022', 'birth_date' => '03-05-2014', 'age' => 12, 'gender' => 'f', 'nationality' => 'Filipino', 'health' => 'good', 'health_note' => null, 'stage' => 'intermediate',
            'grade' => '7', 'level' => 'excellent', 'commitment' => 'good', 'education_note' => null, 'quran' => '3', 'responsible_name' => 'عائشة محمد', 'responsible_relation' => 'جدة',
            'changes' => 'انتقلت إلى الصف السابع بنجاح.', 'comment' => str_repeat('أسهمت الكفالة في استقرار فاطمة الدراسي وتوفير احتياجاتها الأساسية، ', 3), 'message' => 'شكرًا لكم من القلب.',
        ];
        $img = imagecreatetruecolor(30, 40);
        ob_start();
        imagejpeg($img);
        $jpeg = ob_get_clean();
        $pdf = DarAlBerReport::pdf($tpl, $f, $jpeg);

        $this->assertStringStartsWith('%PDF-1.6', $pdf);
        $doc = Document::load($pdf);
        $form = new Form($doc);
        $this->assertSame('فاطمة عبدالله إبراهيم', $form->value('Text5'));
        $this->assertSame('2026', $form->value('Number1'));
        $this->assertSame('أنثى', $form->value('Dropdown1'));
        $this->assertSame('الإعدادية', $form->value('Dropdown3'));
        $this->assertSame('السابع', $form->value('Dropdown4'));
        $this->assertSame('ممتاز   ★ ★ ★', $form->value('Dropdown5'), 'the exact text of the choice, stars and spaces included');
        $this->assertSame('PH', $form->value('Country'));
        $this->assertSame('PH', $form->value('Nationality'), 'Filipino => the Philippines');
        // what the form's scripts would show: the girl icon, the good-health icon, the Philippine flags only
        $this->assertSame(0, $this->widgetFlags($form, 'Female_Logo', $doc) & 2);
        $this->assertSame(2, $this->widgetFlags($form, 'Male_Logo', $doc) & 2);
        $this->assertSame(0, $this->widgetFlags($form, 'Icon_Good', $doc) & 2);
        $this->assertSame(2, $this->widgetFlags($form, 'Icon_Sick', $doc) & 2);
        $this->assertSame(0, $this->widgetFlags($form, 'PH', $doc) & 2);
        $this->assertSame(2, $this->widgetFlags($form, 'ID', $doc) & 2, 'the flag shown in the old copy is hidden');
        $this->assertSame(0, $this->widgetFlags($form, 'Nat_PH', $doc) & 2);

        $all = $this->everything($pdf);
        $this->assertStringNotContainsString(FakeReportForm::OLD, $all, 'the old value, its picture and its rich text are gone');
        $this->assertStringContainsString('formScriptStays', $all, 'the form\'s own script is kept');
        $this->assertStringContainsString('buttonImportIcon', $all, 'the photo button still lets a person choose a photo');
        $this->assertStringContainsString('/Identity-H', $all, 'Arabic is drawn with the font inside the form');
        $this->assertStringContainsString('/DCTDecode', $all, 'the photo');
        $this->assertStringContainsString('&#1601;', $all, 'the rich text box has the new text');
        // still a form: the fields were not flattened
        $this->assertGreaterThan(30, count($form->names()));
        // the page itself is untouched
        $page = $doc->resolve($doc->resolve($doc->root()->get('Pages'))->get('Kids')[0]);
        $this->assertSame("0.85 0.55 0.25 rg 20 812 555 20 re f\n0.2 0.4 0.8 rg 20 20 555 20 re f\n", $doc->resolve($page->get('Contents'))->decoded());
    }

    public function test_the_written_texts_differ_from_orphan_to_orphan_and_read_right(): void
    {
        $texts = new ReportTexts();
        $seen = ['comment' => [], 'message' => [], 'changes' => []];
        for ($i = 1; $i <= 250; $i++) {
            $p = ['female' => $i % 2 === 0, 'name' => $i % 3 ? 'فاطمة' : 'محمد', 'grade' => $i % 4 ? 'الصف السابع' : null, 'stage' => null, 'quran' => $i % 5 ? null : '3 أجزاء',
                'level' => ['high', 'mid', 'low', 'none'][$i % 4], 'health' => ['good', 'sick', 'determined'][$i % 3], 'young' => $i % 7 === 0, 'child_voice' => $i % 2 === 1];
            foreach (['comment', 'message', 'changes'] as $k) {
                [$t] = $texts->make($k, $p, $i, 2026);
                $this->assertArrayNotHasKey($t, $seen[$k], "two orphans got the same {$k}");
                $seen[$k][$t] = true;
                $this->assertDoesNotMatchRegularExpression('/[{}|]|:name|:grade|:quran/u', $t, 'nothing left unfilled: '.$t);
                $this->assertLessThanOrEqual(\App\Services\OrphanReports\ReportPhrases::MAX[$k] + 5, mb_strlen($t));
            }
        }
        // the same orphan and year always gets the same text
        $p = ['female' => true, 'name' => 'مريم', 'grade' => 'الصف الثالث', 'stage' => null, 'quran' => null, 'level' => 'mid', 'health' => 'good', 'young' => false, 'child_voice' => true];
        $this->assertSame((new ReportTexts())->make('comment', $p, 77, 2026)[0], (new ReportTexts())->make('comment', $p, 77, 2026)[0]);
        // a girl is written about as a girl
        $this->assertStringNotContainsString('{', (new ReportTexts())->make('comment', $p, 5, 2026)[0]);
    }

    public function test_grades_and_choices_are_read_from_the_record(): void
    {
        $this->assertSame('7', DarAlBerReport::gradeKey('Grade 7'));
        $this->assertSame('11', DarAlBerReport::gradeKey('الحادي عشر'));
        $this->assertSame('2', DarAlBerReport::gradeKey('الصف الثاني'));
        $this->assertSame('k2', DarAlBerReport::gradeKey('Kinder 2'));
        $this->assertSame('higher', DarAlBerReport::gradeKey('1st year', 'university'));
        $this->assertSame('3', DarAlBerReport::gradeKey('٣'));
        $this->assertSame('9 أجزاء', DarAlBerReport::quranText('9'));
        $this->assertSame('جزأين', DarAlBerReport::quranText('2'));
        $this->assertNull(DarAlBerReport::quranText('some'));
    }

    private function upload(): \Illuminate\Testing\TestResponse
    {
        return $this->post('/api/partners/dar_al_ber/report-form', ['file' => UploadedFile::fake()->createWithContent('report.pdf', FakeReportForm::build(true))], ['Accept' => 'application/json']);
    }

    public function test_only_the_president_adds_the_form_and_only_a_real_form_is_accepted(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        $this->getJson('/api/partners/dar_al_ber/report-form')->assertOk()->assertJsonPath('data.available', false);
        $this->upload()->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $bad = "%PDF-1.4\n1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj 2 0 obj<</Type/Pages/Kids[]/Count 0>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";
        $this->post('/api/partners/dar_al_ber/report-form', ['file' => UploadedFile::fake()->createWithContent('x.pdf', $bad)], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/api/partners/dar_al_ber/report-form', ['file' => UploadedFile::fake()->createWithContent('x.pdf', 'hello')], ['Accept' => 'application/json'])->assertStatus(422);
        $this->post('/api/partners/ico/report-form', ['file' => UploadedFile::fake()->createWithContent('report.pdf', FakeReportForm::build())], ['Accept' => 'application/json'])->assertStatus(422)->assertJsonPath('code', 'NO_REPORT');
        $this->upload()->assertOk()->assertJsonPath('data.available', true)->assertJsonPath('data.org_name', fn ($v) => $v !== '');
        $this->putJson('/api/partners/dar_al_ber/report-form', ['org_name' => 'مؤسسة الإنسانية الخيرية', 'country' => 'ph'])->assertOk()->assertJsonPath('data.country', 'PH');
        $this->assertDatabaseHas('audit_logs', ['action' => 'report_form.uploaded']);
    }

    public function test_staff_write_approve_and_download_reports_one_by_one_and_together(): void
    {
        $this->world();
        $child = Child::withoutGlobalScopes()->where('status', 'active')->orderBy('id')->firstOrFail();
        $this->actingAsRole('staff:aytam');
        $this->getJson("/api/children/{$child->id}/report/pdf?year=2026")->assertStatus(409)->assertJsonPath('code', 'NO_FORM');
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $this->upload()->assertOk();
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('staff:aytam');

        $r = $this->getJson("/api/children/{$child->id}/report?year=2026")->assertOk()->json('data');
        $this->assertSame('new', $r['status']);
        $this->assertNotEmpty($r['values']['comment']);
        $this->assertNotEmpty($r['values']['message']);
        $this->assertTrue($r['form']['available']);
        // write another comment: a different one
        $w = $this->postJson("/api/children/{$child->id}/report/write", ['year' => 2026, 'kind' => 'comment', 'avoid' => $r['values']['comment']])->assertOk()->json('data');
        $this->assertNotSame($r['values']['comment'], $w['text']);
        // the person responsible changed this year (the mother went abroad)
        $saved = $this->putJson("/api/children/{$child->id}/report", ['year' => 2026, 'responsible_name' => 'خديجة علي', 'responsible_relation' => 'خالة', 'choices' => ['health' => 'determined'],
            'changes' => $r['values']['changes'], 'comment' => $w['text'], 'message' => $r['values']['message']])->assertOk()->json('data');
        $this->assertSame('draft', $saved['status']);
        $this->assertSame('determined', $saved['values']['choices']['health']);
        $this->postJson("/api/children/{$child->id}/report/approve", ['year' => 2026, 'approved' => true])->assertOk()->assertJsonPath('data.status', 'approved');

        $pdf = $this->get("/api/children/{$child->id}/report/pdf?year=2026")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $form = new Form(Document::load($pdf->getContent()));
        $this->assertSame('خديجة علي', $form->value('Text18'));
        $this->assertSame('خالة', $form->value('Text19'));
        $this->assertSame('صاحب همة', $form->value('Dropdown2'));
        $this->assertSame($w['text'], $form->value('Text22'));
        $this->assertSame('2026', $form->value('Number1'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'report.downloaded']);

        // changing an approved report needs approving again
        $this->putJson("/api/children/{$child->id}/report", ['year' => 2026, 'responsible_name' => 'خديجة علي حسن', 'comment' => $w['text']])->assertOk()->assertJsonPath('data.status', 'draft');
        $this->postJson("/api/children/{$child->id}/report/approve", ['year' => 2026, 'approved' => true])->assertOk();

        // the year at a glance, then a ZIP of the approved ones and of everyone
        $list = $this->getJson('/api/partner-reports?year=2026')->assertOk()->json('data');
        $this->assertSame(1, $list['counts']['approved']);
        $zip = $this->get('/api/partner-reports/zip?year=2026&scope=approved')->assertOk();
        $this->assertSame('1', $zip->headers->get('X-Count'));
        $all = $this->get('/api/partner-reports/zip?year=2026&scope=all&per=3&part=1')->assertOk();
        $this->assertSame('3', $all->headers->get('X-Count'));
        $file = $all->baseResponse->getFile()->getPathname();
        $z = new \ZipArchive();
        $this->assertTrue($z->open($file) === true);
        $this->assertSame(3, $z->numFiles);
        $comments = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $comments[] = (new Form(Document::load($z->getFromIndex($i))))->value('Text22');
        }
        $this->assertCount(3, array_unique($comments), 'every orphan in the ZIP has its own comment');
        $z->close();

        // a second year is separate
        $this->assertSame('new', $this->getJson("/api/children/{$child->id}/report?year=2027")->json('data.status'));
        $this->assertSame(1, OrphanReport::withoutGlobalScopes()->count());
    }

    public function test_who_may_do_what(): void
    {
        $this->world();
        $child = Child::withoutGlobalScopes()->orderBy('id')->firstOrFail();
        foreach (['staff:finance', 'staff:hr', 'user'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->getJson("/api/children/{$child->id}/report?year=2026")->assertForbidden();
            $this->putJson("/api/children/{$child->id}/report", ['year' => 2026])->assertForbidden();
        }
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('president_admin');
        $this->getJson("/api/children/{$child->id}/report?year=2026")->assertOk();
        $this->putJson("/api/children/{$child->id}/report", ['year' => 2026, 'choices' => ['health' => 'nonsense']])->assertStatus(422);
        // another partner has no report form
        $this->getJson('/api/partner-reports?year=2026', ['X-Partner' => 'ico'])->assertStatus(423);
    }
}
