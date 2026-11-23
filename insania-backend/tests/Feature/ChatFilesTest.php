<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Chat: photos and voice messages (private: only the chat's members open them), and messaging a group member on their own. */
class ChatFilesTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function webm(): string
    {
        return hex2bin('1a45dfa39f4286810142f7810142f2810442f381084282847765626d4287810442858102').str_repeat("\0", 64);
    }

    private function jpeg(): string
    {
        $im = imagecreatetruecolor(40, 30);
        imagefill($im, 0, 0, imagecolorallocate($im, 30, 120, 200));
        ob_start();
        imagejpeg($im);

        return (string) ob_get_clean();
    }

    public function test_photos_and_voice_messages_are_sent_and_only_members_open_them(): void
    {
        Storage::fake('local');
        $this->world();
        $aytam = User::where('email', $this->accounts['staff:aytam'])->firstOrFail();
        $fin = User::where('email', $this->accounts['staff:finance'])->firstOrFail();
        $this->actingAsRole('president_admin');
        $dm = $this->postJson('/api/chat/direct', ['user_id' => $aytam->id])->assertOk()->json('data.id');

        $photo = $this->post("/api/chat/conversations/{$dm}/files", ['kind' => 'image', 'file' => UploadedFile::fake()->createWithContent('p.jpg', $this->jpeg()), 'body' => 'Look'], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame('image', $photo['file']['kind']);
        $this->assertSame('Look', $photo['body']);
        $voice = $this->post("/api/chat/conversations/{$dm}/files", ['kind' => 'audio', 'file' => UploadedFile::fake()->createWithContent('voice.webm', $this->webm()), 'ms' => 4200, 'peaks' => '0369cfilv'], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertSame(['kind' => 'audio', 'ms' => 4200, 'peaks' => '0369cfilv'], array_intersect_key($voice['file'], ['kind' => 1, 'ms' => 1, 'peaks' => 1]));
        // the shape of the sound is only letters 0-9 and a-v (one per bar)
        $this->post("/api/chat/conversations/{$dm}/files", ['kind' => 'audio', 'file' => UploadedFile::fake()->createWithContent('voice.webm', $this->webm()), 'peaks' => '<b>'], ['Accept' => 'application/json'])->assertStatus(422);
        $plain = $this->post("/api/chat/conversations/{$dm}/files", ['kind' => 'audio', 'file' => UploadedFile::fake()->createWithContent('voice.webm', $this->webm())], ['Accept' => 'application/json'])->assertCreated()->json('data');
        $this->assertNull($plain['file']['peaks']);
        // a text file pretending to be a recording is refused
        $this->post("/api/chat/conversations/{$dm}/files", ['kind' => 'audio', 'file' => UploadedFile::fake()->createWithContent('voice.webm', 'not sound')], ['Accept' => 'application/json'])->assertStatus(422);

        $this->get("/api/chat/conversations/{$dm}/messages/{$photo['id']}/file")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->get("/api/chat/conversations/{$dm}/messages/{$voice['id']}/file")->assertOk()->assertHeader('Content-Type', 'audio/webm');
        $list = $this->getJson("/api/chat/conversations/{$dm}/messages")->assertOk()->json('data');
        $this->assertSame(['image', 'audio', 'audio'], array_column(array_column(array_slice($list, -3), 'file'), 'kind'));

        // someone outside the chat cannot open them
        $this->app['auth']->forgetGuards();
        $this->actingAs($fin, 'sanctum');
        $this->get("/api/chat/conversations/{$dm}/messages/{$photo['id']}/file")->assertForbidden();
        $this->post("/api/chat/conversations/{$dm}/files", ['kind' => 'image', 'file' => UploadedFile::fake()->createWithContent('p.jpg', $this->jpeg())], ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_a_group_member_can_open_a_private_chat_with_another_member(): void
    {
        $this->world();
        $aytam = User::where('email', $this->accounts['staff:aytam'])->firstOrFail();
        $fin = User::where('email', $this->accounts['staff:finance'])->firstOrFail();
        $this->actingAsRole('president_admin');
        $g = $this->postJson('/api/chat/groups', ['title' => 'Team', 'user_ids' => [$aytam->id, $fin->id]])->assertCreated()->json('data.id');
        $this->assertNotNull($g);
        $this->app['auth']->forgetGuards();
        $this->actingAs($aytam, 'sanctum');
        $members = $this->getJson("/api/chat/conversations/{$g}/info")->assertOk()->json('data.members');
        $this->assertContains($fin->id, array_column($members, 'id'));
        $dm = $this->postJson('/api/chat/direct', ['user_id' => $fin->id])->assertOk()->json('data');
        $this->assertSame($fin->name, $dm['title']);
        $this->postJson("/api/chat/conversations/{$dm['id']}/messages", ['body' => 'Hi, from the group'])->assertCreated();
    }
}
