<?php

namespace Tests\Feature;

use App\Models\ChatConversation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class ChatGroupsTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function uid(string $email): int
    {
        return User::where('email', $email)->value('id');
    }

    public function test_staff_make_a_group_choose_members_and_only_members_see_it(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        $fin = $this->uid('finance@insania.test');
        $family = $this->uid('family@insania.test');

        $this->postJson('/api/chat/groups', ['title' => 'x', 'user_ids' => [$fin]])->assertStatus(422);                 // name too short
        $this->postJson('/api/chat/groups', ['title' => 'Field team', 'user_ids' => [$family]])->assertStatus(422);     // a family/orphan account cannot be added
        $r = $this->postJson('/api/chat/groups', ['title' => 'Field team', 'user_ids' => [$fin, $family]])->assertCreated();
        $id = $r->json('data.id');

        $list = $this->getJson('/api/chat/conversations')->assertOk()->json('data');
        $g = collect($list)->firstWhere('id', $id);
        $this->assertSame('group', $g['type']);
        $this->assertSame('Field team', $g['title']);

        $this->actingAsRole('staff:finance');                                       // chosen: in the list, can read and write
        $this->assertNotNull(collect($this->getJson('/api/chat/conversations')->json('data'))->firstWhere('id', $id));
        $this->postJson("/api/chat/conversations/$id/messages", ['body' => 'Salam all'])->assertCreated();

        $this->actingAsRole('staff:projects');                                      // not chosen: nothing
        $this->assertNull(collect($this->getJson('/api/chat/conversations')->json('data'))->firstWhere('id', $id));
        $this->getJson("/api/chat/conversations/$id/messages")->assertForbidden();
        $this->postJson("/api/chat/conversations/$id/messages", ['body' => 'hi'])->assertForbidden();
        $this->getJson("/api/chat/conversations/$id/info")->assertForbidden();
    }

    public function test_the_maker_and_the_president_manage_a_group_others_only_look_and_leave(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        $fin = $this->uid('finance@insania.test');
        $proj = $this->uid('projects@insania.test');
        $id = $this->postJson('/api/chat/groups', ['title' => 'Team', 'user_ids' => [$fin]])->json('data.id');

        $info = $this->getJson("/api/chat/conversations/$id/info")->assertOk()->json('data');
        $this->assertTrue($info['can_manage']);
        $this->assertCount(2, $info['members']);
        $this->patchJson("/api/chat/conversations/$id", ['title' => 'Aytam and Finance', 'theme' => 'ocean'])->assertOk()->assertJsonPath('data.title', 'Aytam and Finance');
        $this->patchJson("/api/chat/conversations/$id", ['theme' => 'neon'])->assertStatus(422);
        $this->postJson("/api/chat/conversations/$id/members", ['user_ids' => [$proj]])->assertOk()->assertJsonPath('data.added', 1);
        $this->postJson("/api/chat/conversations/$id/members", ['user_ids' => [$proj]])->assertOk()->assertJsonPath('data.added', 0);

        $this->actingAsRole('staff:finance');                                       // a member: may look, not change
        $this->assertFalse($this->getJson("/api/chat/conversations/$id/info")->json('data.can_manage'));
        $this->patchJson("/api/chat/conversations/$id", ['title' => 'Mine now'])->assertForbidden();
        $this->postJson("/api/chat/conversations/$id/members", ['user_ids' => [$this->uid('aytam@insania.test')]])->assertForbidden();
        $this->deleteJson("/api/chat/conversations/$id/members/$proj")->assertForbidden();

        $this->actingAsRole('president_admin');                                     // the President may manage any group
        $this->patchJson("/api/chat/conversations/$id", ['title' => 'Office team'])->assertOk();
        $this->deleteJson("/api/chat/conversations/$id/members/$proj")->assertOk();
        $this->deleteJson("/api/chat/conversations/$id/members/".$this->uid('aytam@insania.test'))->assertForbidden();   // the maker stays

        $this->actingAsRole('staff:finance');                                       // leaving is always allowed
        $this->deleteJson("/api/chat/conversations/$id/members/$fin")->assertOk();
        $this->getJson("/api/chat/conversations/$id/messages")->assertForbidden();
        $this->assertSame('Office team', ChatConversation::find($id)->title);
    }

    public function test_the_general_group_shows_members_and_only_the_president_changes_its_look(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        $gid = collect($this->getJson('/api/chat/conversations')->json('data'))->firstWhere('type', 'general')['id'];
        $info = $this->getJson("/api/chat/conversations/$gid/info")->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(4, count($info['members']));                 // everyone who can chat
        $this->assertFalse($info['can_manage']);
        $this->patchJson("/api/chat/conversations/$gid", ['theme' => 'rose'])->assertForbidden();
        $this->postJson("/api/chat/conversations/$gid/cover", ['photo' => UploadedFile::fake()->image('c.png', 600, 300)], ['Accept' => 'application/json'])->assertForbidden();

        Storage::fake('local');
        $this->actingAsRole('president_admin');
        $this->patchJson("/api/chat/conversations/$gid", ['theme' => 'rose', 'title' => 'Renamed'])->assertOk()->assertJsonPath('data.title', 'General');   // General keeps its name
        $this->post("/api/chat/conversations/$gid/cover", ['photo' => UploadedFile::fake()->image('c.png', 600, 300)], ['Accept' => 'application/json'])->assertOk();
        $this->assertNotNull(ChatConversation::find($gid)->cover_path);
        $this->get("/api/chat/conversations/$gid/cover")->assertOk();
        $this->actingAsRole('staff:aytam');
        $this->get("/api/chat/conversations/$gid/cover")->assertOk();                                  // everyone in General sees it
        $row = collect($this->getJson('/api/chat/conversations')->json('data'))->firstWhere('id', $gid);
        $this->assertSame('rose', $row['theme']);
        $this->assertNotNull($row['cover']);
    }

    public function test_a_direct_chat_has_no_group_settings_and_the_unread_count_includes_groups(): void
    {
        $this->world();
        $this->actingAsRole('staff:aytam');
        $fin = $this->uid('finance@insania.test');
        $direct = $this->postJson('/api/chat/direct', ['user_id' => $fin])->json('data.id');
        $this->getJson("/api/chat/conversations/$direct/info")->assertForbidden();
        $this->patchJson("/api/chat/conversations/$direct", ['theme' => 'ocean'])->assertForbidden();

        $gid = $this->postJson('/api/chat/groups', ['title' => 'Unread test', 'user_ids' => [$fin]])->json('data.id');
        $this->postJson("/api/chat/conversations/$gid/messages", ['body' => 'one'])->assertCreated();
        $this->postJson("/api/chat/conversations/$gid/messages", ['body' => 'two'])->assertCreated();
        $this->actingAsRole('staff:finance');
        $this->assertSame(2, $this->getJson('/api/chat/unread')->json('data.unread'));
    }
}
