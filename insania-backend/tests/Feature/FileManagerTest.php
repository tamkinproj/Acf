<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Child;
use App\Models\ChildDocument;
use App\Models\ChildPhoto;
use App\Models\Partner;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** The President's Files page: every office upload in named folders (never another foundation's, never private chats), ZIPs and the backup. */
class FileManagerTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function jpeg(): string
    {
        $im = imagecreatetruecolor(40, 30);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 200));
        ob_start();
        imagejpeg($im);

        return (string) ob_get_clean();
    }

    /** an orphan with a photo, a birth certificate and a file no record points to */
    private function orphanFiles(int $fid, User $by): Child
    {
        $kid = Child::withoutGlobalScopes()->where('foundation_id', $fid)->whereNotNull('partner_id')->orderBy('id')->firstOrFail();
        $kid->forceFill(['external_no' => '150409', 'name_en' => 'Rania Malang', 'name_ar' => 'رانيا مالانغ'])->save();
        $d = "children/$fid/{$kid->id}";
        Storage::disk('local')->put("$d/a1.jpg", $this->jpeg());
        ChildPhoto::withoutGlobalScopes()->create(['foundation_id' => $fid, 'child_id' => $kid->id, 'path' => "$d/a1.jpg", 'mime' => 'image/jpeg']);
        Storage::disk('local')->put("$d/b2.jpg", $this->jpeg());
        ChildDocument::withoutGlobalScopes()->create(['foundation_id' => $fid, 'child_id' => $kid->id, 'type' => 'birth_certificate', 'path' => "$d/b2.jpg", 'mime' => 'image/jpeg', 'size' => 10, 'uploaded_by' => $by->id]);
        Storage::disk('local')->put("$d/loose.pdf", "%PDF-1.4\n%%EOF\n");

        return $kid;
    }

    private function zipNames(\Illuminate\Testing\TestResponse $res): array
    {
        $z = new \ZipArchive();
        $this->assertTrue($z->open($res->baseResponse->getFile()->getPathname()) === true);
        $names = [];
        for ($i = 0; $i < $z->numFiles; $i++) {
            $names[] = $z->getNameIndex($i);
        }
        $z->close();

        return $names;
    }

    public function test_the_president_sees_every_office_file_in_named_folders(): void
    {
        Storage::fake('local');
        $this->world();
        $aytam = User::where('email', $this->accounts['staff:aytam'])->firstOrFail();
        $fin = User::where('email', $this->accounts['staff:finance'])->firstOrFail();
        $pres = $this->actingAsRole('president_admin');
        $fid = (int) $pres->foundation_id;
        $kid = $this->orphanFiles($fid, $aytam);
        $partner = Partner::withoutGlobalScopes()->findOrFail($kid->partner_id);

        // a photo in a group chat shows; a photo in a private chat between two people does not
        $g = $this->postJson('/api/chat/groups', ['title' => 'Field team', 'user_ids' => [$aytam->id, $fin->id]])->assertCreated()->json('data.id');
        $this->post("/api/chat/conversations/{$g}/files", ['kind' => 'image', 'file' => UploadedFile::fake()->createWithContent('p.jpg', $this->jpeg())], ['Accept' => 'application/json'])->assertCreated();
        $dm = $this->postJson('/api/chat/direct', ['user_id' => $aytam->id])->assertOk()->json('data.id');
        $this->post("/api/chat/conversations/{$dm}/files", ['kind' => 'image', 'file' => UploadedFile::fake()->createWithContent('p.jpg', $this->jpeg())], ['Accept' => 'application/json'])->assertCreated();
        // another foundation's files never show
        $other = $this->otherFoundation();
        Storage::disk('local')->put("children/{$other['foundation']->id}/{$other['child']->id}/x.jpg", $this->jpeg());

        $root = $this->getJson('/api/files')->assertOk()->json('data');
        $this->assertSame(['orphans', 'applications', 'chat', 'attendance', 'staff', 'forms', 'website'], array_column($root['folders'], 'id'));
        $byId = array_column($root['folders'], null, 'id');
        $this->assertSame(3, $byId['orphans']['count']);
        $this->assertSame(1, $byId['chat']['count'], 'only the group chat photo');
        $this->assertSame(4, $root['total']['count']);

        $this->assertSame([$partner->name_en], array_column($this->getJson('/api/files?folder=orphans')->assertOk()->json('data.folders'), 'name'));
        $kids = $this->getJson('/api/files?folder=orphans/'.$partner->code)->assertOk()->json('data.folders');
        $this->assertSame(['150409 - Rania Malang - رانيا مالانغ'], array_column($kids, 'name'));
        $in = $this->getJson('/api/files?folder=orphans/'.$partner->code.'/c'.$kid->id)->assertOk()->json('data');
        $this->assertSame(['Birth certificate.jpg', 'loose.pdf', 'Photo 1.jpg'], array_column($in['files'], 'name'));
        $this->assertSame(['Orphans', $partner->name_en, '150409 - Rania Malang - رانيا مالانغ'], array_column($in['crumbs'], 'name'));
        $files = array_column($in['files'], null, 'name');
        $this->assertSame($aytam->name, $files['Birth certificate.jpg']['by']);
        $this->assertSame('pdf', $files['loose.pdf']['kind']);
        $this->assertSame(['Field team'], array_column($this->getJson('/api/files?folder=chat')->json('data.folders'), 'name'));
        $this->getJson('/api/files?folder=orphans/nope')->assertNotFound();

        // open one (shown in the app) and save one (written in the activity log)
        $ref = $files['Photo 1.jpg']['ref'];
        $this->get('/api/files/file?ref='.urlencode($ref))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $res = $this->get('/api/files/file?ref='.urlencode($files['Birth certificate.jpg']['ref']).'&download=1')->assertOk();
        $this->assertStringContainsString('attachment', $res->headers->get('Content-Disposition'));
        $this->assertSame(1, AuditLog::withoutGlobalScopes()->where('action', 'files.downloaded')->count());
        // only files in the library: not another foundation's, not a private chat's, not anything else on the server
        $this->get('/api/files/file?ref='.urlencode("local:children/{$other['foundation']->id}/{$other['child']->id}/x.jpg"))->assertNotFound();
        $this->get('/api/files/file?ref='.urlencode('local:../.env'))->assertNotFound();
        $dmFile = collect(Storage::disk('local')->allFiles("chat/$fid/$dm"))->first();
        $this->assertNotNull($dmFile);
        $this->get('/api/files/file?ref='.urlencode('local:'.$dmFile))->assertNotFound();

        // search everywhere
        $hits = $this->getJson('/api/files?q=birth')->assertOk()->json('data.files');
        $this->assertCount(1, $hits);
        $this->assertStringStartsWith('Orphans › '.$partner->name_en, $hits[0]['where']);

        // a whole folder as a ZIP, with the same names
        $zip = $this->get('/api/files/zip?folder=orphans')->assertOk()->assertHeader('X-Parts', '1');
        $names = $this->zipNames($zip);
        sort($names);
        $this->assertSame(['Orphans/'.$partner->name_en.'/150409 - Rania Malang - رانيا مالانغ/Birth certificate.jpg', 'Orphans/'.$partner->name_en.'/150409 - Rania Malang - رانيا مالانغ/Photo 1.jpg', 'Orphans/'.$partner->name_en.'/150409 - Rania Malang - رانيا مالانغ/loose.pdf'], $names);

        // staff do not have the Files page
        $this->app['auth']->forgetGuards();
        $this->actingAs($aytam, 'sanctum');
        $this->getJson('/api/files')->assertForbidden();
        $this->get('/api/files/file?ref='.urlencode($ref))->assertForbidden();
        $this->postJson('/api/files/backup', ['confirm_password' => DemoSeeder::PASSWORD])->assertForbidden();
    }

    public function test_the_backup_asks_for_the_password_and_holds_every_file_and_the_records(): void
    {
        Storage::fake('local');
        $this->world();
        $pres = $this->actingAsRole('president_admin');
        $fid = (int) $pres->foundation_id;
        $this->otherFoundation();
        $this->orphanFiles($fid, $pres);

        $this->postJson('/api/files/backup', ['confirm_password' => 'not my password'])->assertStatus(422)->assertJsonPath('code', 'WRONG_PASSWORD');
        $res = $this->post('/api/files/backup', ['confirm_password' => DemoSeeder::PASSWORD], ['Accept' => 'application/json'])->assertOk()->assertHeader('X-Parts', '1');
        $names = $this->zipNames($res);
        $this->assertContains('READ ME.txt', $names);
        $this->assertContains('data/children.csv', $names);
        $this->assertContains('data/users.csv', $names);
        $this->assertNotEmpty(array_filter($names, fn ($n) => str_starts_with($n, 'files/Orphans/') && str_ends_with($n, '/Birth certificate.jpg')));
        $this->assertNotContains('data/personal_access_tokens.csv', $names);
        $this->assertNotContains('data/chat_messages.csv', $names);

        $z = new \ZipArchive();
        $z->open($res->baseResponse->getFile()->getPathname());
        $users = (string) $z->getFromName('data/users.csv');
        $kids = (string) $z->getFromName('data/children.csv');
        $readme = (string) $z->getFromName('READ ME.txt');
        $z->close();
        $head = strtolower(strtok($users, "\r\n"));
        $this->assertStringNotContainsString('password', $head);
        $this->assertStringNotContainsString('remember_token', $head);
        $this->assertStringContainsString('president@insania.test', $users);
        $this->assertStringNotContainsString('other-president@x.test', $users, 'only this foundation');
        $this->assertStringContainsString('Rania Malang', $kids);
        $this->assertStringNotContainsString('Other Child', $kids);
        $this->assertStringContainsString('private chats', $readme);
        $this->assertSame(1, AuditLog::withoutGlobalScopes()->where('action', 'files.backup_downloaded')->count());
    }

    public function test_a_large_backup_comes_in_parts_and_the_records_are_in_the_first(): void
    {
        Storage::fake('local');
        $this->world();
        $pres = $this->actingAsRole('president_admin');
        $fid = (int) $pres->foundation_id;
        config(['files.part_mb' => 1]);
        Storage::disk('local')->put("avatars/$fid/big1.jpg", random_bytes(700 * 1024));
        Storage::disk('local')->put("avatars/$fid/big2.jpg", random_bytes(700 * 1024));
        Storage::disk('local')->put("avatars/$fid/big3.jpg", random_bytes(700 * 1024));

        $p1 = $this->post('/api/files/backup', ['confirm_password' => DemoSeeder::PASSWORD, 'part' => 1], ['Accept' => 'application/json'])->assertOk()->assertHeader('X-Parts', '3');
        $this->assertStringContainsString('part 1 of 3', (string) $p1->headers->get('Content-Disposition'));
        $n1 = $this->zipNames($p1);
        $this->assertContains('data/children.csv', $n1);
        $this->assertCount(1, array_filter($n1, fn ($n) => str_starts_with($n, 'files/Staff photos/')));
        $p2 = $this->post('/api/files/backup', ['confirm_password' => DemoSeeder::PASSWORD, 'part' => 2], ['Accept' => 'application/json'])->assertOk();
        $n2 = $this->zipNames($p2);
        $this->assertSame(1, count($n2));
        $this->assertStringStartsWith('files/Staff photos/', $n2[0]);
        $this->get('/api/files/zip?folder=staff&part=3')->assertOk()->assertHeader('X-Parts', '3');
    }
}
