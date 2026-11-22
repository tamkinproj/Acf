<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\ChildApplication;
use App\Support\NameParts;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** The orphan form follows the Dar Al Ber form: names in four parts, the papers under their own names. */
class DarAlBerFormTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function png(string $name = 'p.png'): UploadedFile
    {
        $im = imagecreatetruecolor(40, 30);
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent($name, (string) ob_get_clean());
    }

    private function form(array $over = []): array
    {
        return $over + [
            'child_first_ar' => 'علي', 'child_father_ar' => 'يوسف', 'child_grand_ar' => 'نورون', 'child_family_ar' => 'قاسم كالي',
            'child_first_en' => 'Alyusep', 'child_father_en' => 'Noron', 'child_grand_en' => 'Kasim', 'child_family_en' => 'Kali',
            'gender' => 'm', 'birth_date' => '2018-12-18', 'mother_first_en' => 'Asmin', 'mother_father_en' => 'Omar', 'mother_grand_en' => 'Malang', 'mother_family_en' => 'Kasim',
            'resp_first_en' => 'Asmin', 'resp_father_en' => 'Omar', 'resp_grand_en' => 'Malang', 'resp_family_en' => 'Kasim', 'responsible_relation' => 'Mother', 'guardian_phone' => '09171234567',
        ];
    }

    public function test_names_are_split_and_joined(): void
    {
        $this->assertSame(['Alyusep', 'Noron', 'Kasim', 'Kali'], NameParts::split('Alyusep  Noron Kasim Kali'));
        $this->assertSame(['Ali', 'Omar', null, null], NameParts::split('Ali Omar'));
        $this->assertSame(['A', 'B', 'C', 'D E'], NameParts::split('A B C D E'));
        $this->assertSame('A B D', NameParts::join(['A', ' B ', '', 'D']));
    }

    public function test_the_public_form_takes_the_parts_and_keeps_the_full_names_and_the_guardian(): void
    {
        Storage::fake('local');
        $this->world();
        config(['orphan_fields.apply.per_hour' => 1000]);
        $this->postJson('/api/public/apply', $this->form())->assertCreated();
        $a = ChildApplication::withoutGlobalScopes()->first();
        $this->assertSame('Alyusep Noron Kasim Kali', $a->data['name_en']);
        $this->assertSame('علي يوسف نورون قاسم كالي', $a->data['name_ar']);
        $this->assertSame('Asmin Omar Malang Kasim', $a->data['guardian_name']);   // the guardian is the responsible person
        $this->assertSame('Mother', $a->data['guardian_relation']);
        $nameless = array_fill_keys(['child_first_en', 'child_first_ar', 'child_father_en', 'child_father_ar', 'child_grand_en', 'child_grand_ar', 'child_family_en', 'child_family_ar'], null);
        $this->postJson('/api/public/apply', $nameless + $this->form())->assertStatus(422);   // an orphan needs a name
    }

    public function test_papers_sent_under_their_name_reach_the_profile_when_approved(): void
    {
        Storage::fake('local');
        $this->world();
        config(['orphan_fields.apply.per_hour' => 1000]);
        $this->post('/api/public/apply', $this->form() + [
            'photos' => [$this->png()], 'documents' => [$this->png('b.png'), $this->png('c.png'), $this->png('d.png')], 'document_types' => ['birth_certificate', 'case_study', 'other'],
        ], ['Accept' => 'application/json'])->assertCreated();
        $a = ChildApplication::withoutGlobalScopes()->first();
        $this->assertSame(['birth_certificate', 'case_study', 'other'], array_column($a->documents, 'type'));

        $this->post('/api/public/apply', $this->form() + ['documents' => [$this->png()], 'document_types' => ['passport']], ['Accept' => 'application/json'])->assertStatus(422);

        $this->app['auth']->forgetGuards();
        $this->actingAsRole('staff:aytam');
        $this->getJson("/api/applications/{$a->id}")->assertOk()->assertJsonPath('data.document_types', ['birth_certificate', 'case_study', 'other']);
        $id = $this->postJson("/api/applications/{$a->id}/approve")->assertOk()->json('data.child.id');
        $docs = collect($this->getJson("/api/children/$id/documents")->json('data'))->keyBy('type');
        $this->assertTrue($docs['birth_certificate']['on_file']);
        $this->assertTrue($docs['case_study']['on_file']);
        $this->assertFalse($docs['school_record']['on_file']);
        $this->get("/api/children/$id/documents/case_study/download")->assertOk();
        $p = $this->getJson("/api/children/$id")->assertOk()->json('data');
        $this->assertSame('Alyusep', $p['details']['child_first_en']);
        $this->assertSame('قاسم كالي', $p['details']['child_family_ar']);
        $this->assertSame('Mother', $p['details']['responsible_relation']);
        $this->assertCount(5, $p['documents']);
    }

    public function test_staff_add_and_edit_with_the_parts(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        $body = $this->form() + ['guardian' => ['phone' => '0917']];
        $id = $this->postJson('/api/children', $body)->assertCreated()->json('data.id');
        $c = Child::find($id);
        $this->assertSame('Alyusep Noron Kasim Kali', $c->name_en);
        $this->assertSame('Asmin Omar Malang Kasim', $c->guardian->name);
        $this->patchJson("/api/children/$id", ['child_father_en' => 'Nuron'])->assertOk();
        $c->refresh();
        $this->assertSame('Alyusep Nuron Kasim Kali', $c->name_en);
        $this->assertSame('Kasim', $c->child_grand_en);
        $c->update(['mother_name' => 'Fatima Ali Hassan Omar Said']);
        $this->assertSame(['Fatima', 'Ali', 'Hassan', 'Omar Said'], [$c->mother_first_en, $c->mother_father_en, $c->mother_grand_en, $c->mother_family_en]);
        $this->patchJson("/api/children/$id", ['guardian' => ['phone' => '0999']])->assertOk();
        $this->assertSame('Asmin Omar Malang Kasim', $c->fresh()->guardian->name);
    }
}
