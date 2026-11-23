<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** Orphans and families in chat groups: the office adds them; they see and write only in those groups. */
class ChatGuestsTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_a_group_can_have_orphans_and_families_who_see_only_their_groups(): void
    {
        $this->world();
        $aytam = User::where('email', $this->accounts['staff:aytam'])->firstOrFail();
        $fin = User::where('email', $this->accounts['staff:finance'])->firstOrFail();
        $family = User::where('email', $this->accounts['user'])->firstOrFail();
        $kid = Child::withoutGlobalScopes()->where('foundation_id', $aytam->foundation_id)->orderBy('id')->firstOrFail();
        $orphan = User::create(['name' => 'Amina Hassan', 'username' => 'amina.h', 'email' => 'amina.h@orphans.invalid', 'password' => 'OrphanPass#2026', 'role' => 'user', 'foundation_id' => $aytam->foundation_id, 'child_id' => $kid->id, 'active' => true]);

        // staff see orphans and families when making a group
        $this->actingAs($aytam, 'sanctum');
        $guests = $this->getJson('/api/chat/people?guests=1')->assertOk()->json('data');
        $byId = array_column($guests, null, 'id');
        $this->assertSame('orphan', $byId[$orphan->id]['kind']);
        $this->assertSame('family', $byId[$family->id]['kind']);
        $this->assertNotContains($aytam->id, array_keys($byId));
        $this->assertNotContains($family->id, array_column($this->getJson('/api/chat/people')->json('data'), 'id'), 'the usual list stays staff only');

        $g = $this->postJson('/api/chat/groups', ['title' => 'Quran class', 'user_ids' => [$fin->id, $family->id, $orphan->id]])->assertCreated()->json('data.id');
        $this->postJson("/api/chat/conversations/{$g}/messages", ['body' => 'Welcome everyone'])->assertCreated();
        $dm = $this->postJson('/api/chat/direct', ['user_id' => $fin->id])->assertOk()->json('data.id');
        $general = collect($this->getJson('/api/chat/conversations')->json('data'))->firstWhere('type', 'general')['id'];
        $this->postJson('/api/chat/direct', ['user_id' => $orphan->id])->assertStatus(422);   // no private chats with orphans or families

        // the orphan: only the group
        $this->app['auth']->forgetGuards();
        $this->actingAs($orphan, 'sanctum');
        $this->assertContains('chat.guest', $this->getJson('/api/me')->json('data.permissions'));
        $list = $this->getJson('/api/chat/conversations')->assertOk()->json('data');
        $this->assertSame([[$g, 'group']], array_map(fn ($c) => [$c['id'], $c['type']], $list));
        $this->assertSame(1, $this->getJson('/api/chat/unread')->assertOk()->json('data.unread'));
        $this->assertSame([], $this->getJson('/api/chat/unread')->json('data.shareable'));
        $this->getJson("/api/chat/conversations/{$g}/messages")->assertOk();
        $this->postJson("/api/chat/conversations/{$g}/messages", ['body' => 'Thank you'])->assertCreated();
        $members = $this->getJson("/api/chat/conversations/{$g}/info")->assertOk()->json('data.members');
        $this->assertSame(4, count($members));
        // and nothing else
        $this->getJson("/api/chat/conversations/{$general}/messages")->assertForbidden();
        $this->getJson("/api/chat/conversations/{$dm}/messages")->assertForbidden();
        $this->postJson("/api/chat/conversations/{$general}/messages", ['body' => 'hi'])->assertForbidden();
        $this->getJson('/api/chat/people')->assertForbidden();
        $this->getJson('/api/chat/people?guests=1')->assertForbidden();
        $this->postJson('/api/chat/direct', ['user_id' => $aytam->id])->assertForbidden();
        $this->postJson('/api/chat/groups', ['title' => 'Mine', 'user_ids' => [$aytam->id]])->assertForbidden();
        $this->postJson("/api/chat/conversations/{$g}/messages", ['card' => ['kind' => 'child', 'id' => $kid->id]])->assertForbidden();
        $this->getJson("/api/chat/conversations/{$g}/audience?kind=child")->assertForbidden();
        $this->postJson("/api/chat/conversations/{$g}/members", ['user_ids' => [$aytam->id]])->assertForbidden();
        $this->deleteJson("/api/chat/conversations/{$g}/members/{$fin->id}")->assertForbidden();

        // staff see who is an orphan or family member (not offered for a private chat)
        $this->app['auth']->forgetGuards();
        $this->actingAs($fin, 'sanctum');
        $msgs = $this->getJson("/api/chat/conversations/{$g}/messages")->assertOk()->json('data');
        $last = end($msgs);
        $this->assertTrue($last['guest']);
        $this->assertFalse($msgs[0]['guest']);
        $info = array_column($this->getJson("/api/chat/conversations/{$g}/info")->json('data.members'), null, 'id');
        $this->assertTrue($info[$orphan->id]['guest']);
        $this->assertSame('Orphan', $info[$orphan->id]['role_label']);

        // a family account can leave the group itself
        $this->app['auth']->forgetGuards();
        $this->actingAs($family, 'sanctum');
        $this->deleteJson("/api/chat/conversations/{$g}/members/{$family->id}")->assertOk();
        $this->assertSame([], $this->getJson('/api/chat/conversations')->json('data'));
    }
}
