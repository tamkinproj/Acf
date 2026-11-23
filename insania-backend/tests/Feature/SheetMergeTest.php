<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\Child;
use App\Models\Partner;
use App\Services\PaymentLedger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/**
 * The temporary sheet merge (UPDATE-61): the office's Google Form answer sheets are added to the orphans already
 * saved, matched only by orphan number. Made-up sheets with the same columns as the real ones.
 */
class SheetMergeTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private const A_HEAD = ['Timestamp', 'Pangalan ng Bata (kumpleto) mula sa birth certicate', 'Numero ng bata (batay sa binigay ng opisina)', 'Kasarian ng Bata', 'Edad ng Bata',
        'Grade ng Bata sa Arabic / madrasa', 'Hanggang saang surah ang naisaulo ng bata mula sa Qur-an', 'Guardian Ng Bata (ang ilagay kung sino ang napapadalhan ng budget ng bata )',
        'Kaano-ano ng bata ang Kanyang Guardian ?', 'Ano ang mga Pagbabago sa Bata mula nung Natanggap sa ating Orphanage', 'Kumusta ang Kalusugan ng bata ?',
        'Ano ang Masasabi ng Bata sa kanyang Sponsor', 'Mag-upload ng Picture na whole Body ng Bata', 'AUB / HELLO MONEY ACCOUNT NAME', 'AUB/ HELLO MONEY ACCOUNT NUMBER (934-************)', 'DATE F BIRTH OF ORPHAN'];

    private const B_HEAD = ['Timestamp', 'Pangalan ng Bata (kumpleto) mula sa birth certicate', 'Numero ng bata (batay sa binigay ng opisina)', 'Edad ng Bata', 'DATE F BIRTH OF ORPHAN'];

    private function csv(array $rows): string
    {
        $h = fopen('php://temp', 'r+');
        foreach ($rows as $r) {
            fputcsv($h, $r);
        }
        rewind($h);

        return (string) stream_get_contents($h);
    }

    /** One row of the update form: only the columns given are filled. */
    private function a(array $v): array
    {
        $keys = ['ts', 'name', 'no', 'gender', 'age', 'madrasa', 'quran', 'guardian', 'relation', 'changes', 'health', 'message', 'photos', 'holder', 'account', 'dob'];

        return array_map(fn ($k) => (string) ($v[$k] ?? ''), $keys);
    }

    private function send(array $aRows, array $bRows, bool $apply, array $headers = ['X-Partner' => 'dar_al_ber'])
    {
        $files = [UploadedFile::fake()->createWithContent('updates.csv', $this->csv(array_merge([self::A_HEAD], $aRows)))];
        if ($bRows) {
            $files[] = UploadedFile::fake()->createWithContent('births.csv', $this->csv(array_merge([self::B_HEAD], $bRows)));
        }

        return $this->post('/api/children/sheet-merge', ['files' => $files, 'apply' => $apply ? '1' : '0'], $headers + ['Accept' => 'application/json']);
    }

    /** Two orphans of the demo world given orphan numbers: one paid by bank, one by cash. */
    private function twoOrphans(): array
    {
        $kids = Child::withoutGlobalScopes()->where('status', 'active')->orderBy('id')->take(2)->get();
        $kids[0]->forceFill(['external_no' => '111111', 'guardian_id' => null, 'gender' => null, 'birth_date' => null, 'notable_changes' => 'Written by staff earlier', 'health_details' => null, 'health_status' => 'good'])->save();
        $kids[1]->forceFill(['external_no' => '222222', 'gender' => null, 'birth_date' => null, 'health_details' => null, 'health_status' => 'good'])->save();
        $kids[0]->sponsorship()->first()->update(['pay_type' => 'bank']);
        $kids[1]->sponsorship()->first()->update(['pay_type' => 'cash']);
        BankAccount::withoutGlobalScopes()->whereIn('child_id', [$kids[0]->id, $kids[1]->id])->delete();

        return [$kids[0]->fresh(), $kids[1]->fresh()];
    }

    public function test_a_preview_writes_nothing_and_apply_fills_only_empty_fields(): void
    {
        $this->world();
        [$bank, $cash] = $this->twoOrphans();
        $this->actingAsRole('staff:aytam');
        $rows = [
            $this->a(['ts' => '46224.5', 'no' => '111111.0', 'name' => 'Old answer', 'gender' => 'Batang Babae', 'age' => '10', 'madrasa' => '2.0', 'quran' => 'Surah Nas', 'guardian' => 'Maria Santos', 'relation' => 'Lola',
                'changes' => 'From the sheet', 'health' => 'Hindi mabuti, may lagnat', 'message' => 'Salamat po', 'photos' => 'https://drive.google.com/open?id=x', 'holder' => 'Maria Santos', 'account' => '9.34109693715E11', 'dob' => '42214']),
            $this->a(['ts' => '46255.5', 'no' => '111111', 'madrasa' => '3']),   // sent again later: the latest answer wins
            $this->a(['ts' => '46224.6', 'no' => '#222222', 'gender' => 'Batang lalaki', 'guardian' => 'Ana Cruz', 'relation' => 'Nanay', 'holder' => 'Ana Cruz', 'account' => '09171234567']),
        ];
        $pre = $this->send($rows, [], false)->assertOk()->json('data');
        $this->assertFalse($pre['applied']);
        $this->assertSame(2, $pre['matched']);
        $this->assertNull($bank->fresh()->gender, 'a preview saves nothing');

        $r = $this->send($rows, [], true)->assertOk()->json('data');
        $this->assertTrue($r['applied']);
        $b = $bank->fresh();
        $this->assertSame('f', $b->gender);
        $this->assertSame('2015-07-29', $b->birth_date->toDateString());
        $this->assertSame('3', $b->madrasa_grade, 'the latest answer wins');
        $this->assertSame('Written by staff earlier', $b->notable_changes, 'a value already saved is kept');
        $this->assertSame('needs_followup', $b->health_status, '"good" was only the starting value: the sheet says unwell');
        $this->assertSame('https://drive.google.com/open?id=x', $b->photo_links);
        $this->assertSame('Grandmother', $b->guardian->relation);
        $acct = BankAccount::withoutGlobalScopes()->where('child_id', $b->id)->first();
        $this->assertSame(['Maria Santos', '', '3715', false], [$acct->holder, $acct->bank_name, $acct->last4, (bool) $acct->verified], 'last 4 digits only, not verified');
        $this->assertNull(BankAccount::withoutGlobalScopes()->where('child_id', $cash->id)->first(), 'no bank account for an orphan paid in cash');
        $this->assertSame('m', $cash->fresh()->gender, '#222222 is read as 222222');
        $this->assertContains('notable_changes', array_column($r['lists']['conflicts'], 'field'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'children.sheet_merged']);
        $log = DB::table('audit_logs')->where('action', 'children.sheet_merged')->value('meta');
        $this->assertStringNotContainsString('Maria', (string) $log, 'the log keeps counts only');

        // a second run changes nothing
        $again = $this->send($rows, [], true)->json('data');
        $this->assertSame(0, $again['updated']);
        $this->assertSame(0, $again['created']);
    }

    public function test_unclear_numbers_and_birth_dates_are_listed_not_guessed(): void
    {
        $this->world();
        [$bank] = $this->twoOrphans();
        $this->actingAsRole('staff:aytam');
        $rows = [
            $this->a(['no' => '585341, 586074, 586095', 'name' => 'Three in one cell']),
            $this->a(['no' => '153394(8000)', 'name' => 'Number and amount']),
            $this->a(['no' => '1569617.0', 'name' => 'Seven digits']),
            $this->a(['no' => 'Wala', 'name' => 'No number']),
            $this->a(['ts' => '46230', 'no' => '111111', 'age' => '9', 'dob' => '46230']),   // the date of the form, not of birth
        ];
        $births = [['46230', 'x', '222222', '12', '41000'], ['46231', 'x', '222222', '', '41005']];
        $rows[] = $this->a(['ts' => '46229', 'no' => '222222', 'dob' => '41010']);   // the update form gives yet another date
        $r = $this->send($rows, $births, true)->assertOk()->json('data');
        $this->assertSame(['585341, 586074, 586095', '153394(8000)', '1569617.0', 'Wala'], array_column($r['lists']['unclear_numbers'], 'number'));
        $this->assertSame(0, $r['created'], 'nothing is created from an unclear number');
        $reasons = implode(' | ', array_column($r['lists']['birth_dates'], 'reason'));
        $this->assertStringContainsString('Does not fit the age written (9)', $reasons);
        $this->assertStringContainsString('different birth dates', $reasons);
        $this->assertNull($bank->fresh()->birth_date);
        // a date written in the age column is not used as an age
        $ok = $this->send([$this->a(['no' => '111111', 'age' => 'July,30,2012', 'dob' => '41120'])], [], true)->json('data');
        $this->assertSame([], $ok['lists']['birth_dates']);
        $this->assertSame('2012-07-30', $bank->fresh()->birth_date->toDateString());
    }

    public function test_a_number_not_in_the_system_becomes_a_pending_orphan_without_bank_or_cash(): void
    {
        $this->world();
        $this->twoOrphans();
        $this->actingAsRole('staff:aytam');
        $rows = [$this->a(['ts' => '46224', 'no' => '654321', 'name' => 'Rania Malang Kasim', 'gender' => 'Batang Babae', 'guardian' => 'Kungan Malang', 'relation' => 'Mother', 'holder' => 'Kungan', 'account' => '1234567890', 'dob' => '40851'])];
        $pre = $this->send($rows, [], false)->json('data');
        $this->assertSame(1, $pre['created']);
        $this->assertNull(Child::withoutGlobalScopes()->where('external_no', '654321')->first());
        $this->send($rows, [], true)->assertOk();
        $c = Child::withoutGlobalScopes()->where('external_no', '654321')->firstOrFail();
        $s = $c->sponsorship()->first();
        $this->assertSame(['pending', 'pending', ''], [$c->status, $s->status, $s->pay_type]);
        $this->assertSame((int) Partner::where('code', 'dar_al_ber')->value('id'), (int) $c->partner_id);
        $this->assertSame('Rania Malang Kasim', $c->name_en);
        $this->assertNull(BankAccount::withoutGlobalScopes()->where('child_id', $c->id)->first(), 'no bank details while bank or cash is not chosen');
        $ledger = (new PaymentLedger())->forMonth(now()->format('Y-m'));
        $this->assertNull($ledger->firstWhere('child_id', $c->id), 'not in payments');

        // it cannot be made active by hand ...
        $this->patchJson('/api/children/'.$c->id, ['status' => 'active'], ['X-Partner' => 'dar_al_ber'])->assertStatus(422);
        // ... it becomes active when a sponsor sheet lists it
        $sheet = $this->csv([['كشف الأيتام المطلوب تقارير لهم - مؤسسة الإنسانية الخيرية - بنكي'], ['م', 'رقم اليتيم', 'اسم اليتيم', 'اسم الكافل', 'رقم الكافل'], ['1', '654321', 'رانيا مالانج', 'كافل', '500100']]);
        $this->post('/api/children/import', ['file' => UploadedFile::fake()->createWithContent('kashf.csv', $sheet), 'pay_type' => 'auto'], ['X-Partner' => 'dar_al_ber', 'Accept' => 'application/json'])->assertOk();
        $s = $c->sponsorship()->first();
        $this->assertSame(['active', 'bank', 'active'], [$s->status, $s->pay_type, $c->fresh()->status]);
    }

    public function test_numbers_of_another_partner_are_left_alone_and_who_may_use_it(): void
    {
        $this->world();
        [$bank] = $this->twoOrphans();
        $other = Partner::where('code', 'dubai_charity')->value('id');
        $bank->forceFill(['partner_id' => $other])->save();
        $this->actingAsRole('staff:aytam');
        $r = $this->send([$this->a(['no' => '111111', 'gender' => 'Batang Babae'])], [], true)->json('data');
        $this->assertSame(['111111'], array_column($r['lists']['other_partner'], 'number'));
        $this->assertSame(0, $r['created'], 'never a second orphan with the same number');
        $this->assertNull($bank->fresh()->gender);
        foreach (['staff:finance', 'staff:hr', 'user'] as $role) {
            $this->app['auth']->forgetGuards();
            $this->actingAsRole($role);
            $this->send([$this->a(['no' => '222222'])], [], false)->assertForbidden();
        }
    }
}
