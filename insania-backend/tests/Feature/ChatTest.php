<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChatMessage;
use App\Models\Child;
use App\Models\ChildApplication;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class ChatTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function as(string $role): User
    {
        $this->app['auth']->forgetGuards();

        return $this->actingAsRole($role);
    }

    private function general(): int
    {
        return $this->getJson('/api/chat/conversations')->assertOk()->json('data.0.id');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->world();
    }

    public function test_only_the_president_and_staff_can_chat_never_the_system_roles_or_families(): void
    {
        foreach (['president_admin', 'staff:aytam', 'staff:finance', 'staff:projects'] as $role) {
            $this->as($role);
            $this->getJson('/api/chat/conversations')->assertOk();
            $this->getJson('/api/chat/people')->assertOk();
        }
        foreach (['system_admin', 'security_admin', 'admin_maintenance'] as $role) {
            $this->as($role);
            $this->getJson('/api/chat/conversations')->assertForbidden();
            $this->getJson('/api/chat/unread')->assertForbidden();
            $this->postJson('/api/chat/direct', ['user_id' => 1])->assertForbidden();
        }
        // families and orphans: only the groups the office adds them to (none here), never the General chat or private chats
        $this->as('user');
        $this->assertSame([], $this->getJson('/api/chat/conversations')->assertOk()->json('data'));
        $this->postJson('/api/chat/direct', ['user_id' => 1])->assertForbidden();
    }

    public function test_the_general_group_is_shared_by_the_whole_office_and_nobody_else(): void
    {
        $this->as('staff:aytam');
        $g = $this->general();
        $this->postJson("/api/chat/conversations/$g/messages", ['body' => 'Hello office'])->assertCreated()->assertJsonPath('data.mine', true);

        $this->as('staff:finance');
        $this->assertSame(1, $this->getJson('/api/chat/conversations')->json('data.0.unread'));      // unread until opened
        $rows = $this->getJson("/api/chat/conversations/$g/messages")->assertOk()->json('data');
        $this->assertSame('Hello office', $rows[0]['body']);
        $this->assertFalse($rows[0]['mine']);
        $this->assertSame(0, $this->getJson('/api/chat/conversations')->json('data.0.unread'));      // reading cleared it

        foreach (['system_admin', 'security_admin', 'admin_maintenance', 'user'] as $role) {
            $this->as($role);
            $this->getJson("/api/chat/conversations/$g/messages")->assertForbidden();
            $this->postJson("/api/chat/conversations/$g/messages", ['body' => 'x'])->assertForbidden();
        }
        // the system accounts are not offered as chat partners either
        $this->as('president_admin');
        $names = collect($this->getJson('/api/chat/people')->json('data'))->pluck('name')->all();
        $this->assertNotContains(User::where('role', 'system_admin')->value('name'), $names);
        $this->assertNotContains(User::where('role', 'user')->value('name'), $names);
        $this->assertContains(User::where('email', 'aytam@insania.test')->value('name'), $names);
    }

    public function test_any_staff_can_start_one_to_one_chats_with_anyone_in_the_office_and_it_is_private(): void
    {
        $aytam = User::where('email', 'aytam@insania.test')->first();
        $finance = User::where('email', 'finance@insania.test')->first();
        $president = User::where('email', 'president@insania.test')->first();

        $this->as('staff:aytam');
        $c = $this->postJson('/api/chat/direct', ['user_id' => $finance->id])->assertOk()->json('data.id');
        $this->assertSame($c, $this->postJson('/api/chat/direct', ['user_id' => $finance->id])->json('data.id'), 'the same chat is reused');
        $this->postJson("/api/chat/conversations/$c/messages", ['body' => 'Private note'])->assertCreated();
        $this->postJson('/api/chat/direct', ['user_id' => $president->id])->assertOk();           // staff can chat with the President
        $this->postJson('/api/chat/direct', ['user_id' => $aytam->id])->assertStatus(422);        // not with yourself
        $this->postJson('/api/chat/direct', ['user_id' => User::where('role', 'system_admin')->value('id')])->assertStatus(422);
        $this->postJson('/api/chat/direct', ['user_id' => User::where('role', 'user')->value('id')])->assertStatus(422);

        $this->as('staff:finance');
        $this->assertSame('Private note', $this->getJson("/api/chat/conversations/$c/messages")->assertOk()->json('data.0.body'));
        $this->assertSame($aytam->name, $this->getJson('/api/chat/conversations')->json('data.1.title'));   // the other person's name is the title

        $this->as('staff:projects');                                                              // a third person cannot read or write it
        $this->getJson("/api/chat/conversations/$c/messages")->assertForbidden();
        $this->postJson("/api/chat/conversations/$c/messages", ['body' => 'spy'])->assertForbidden();
        $this->getJson("/api/chat/conversations/$c/audience?kind=child")->assertForbidden();
        $this->assertNotContains($c, collect($this->getJson('/api/chat/conversations')->json('data'))->pluck('id')->all());
    }

    public function test_a_card_can_only_be_shared_by_someone_who_can_open_it_and_each_person_sees_it_through_their_own_access(): void
    {
        $child = Child::orderBy('id')->first();
        $this->as('staff:finance');
        $g = $this->general();
        $this->postJson("/api/chat/conversations/$g/messages", ['card' => ['kind' => 'child', 'id' => $child->id]])->assertForbidden();   // Finance cannot open orphans
        $this->postJson("/api/chat/conversations/$g/messages", ['card' => ['kind' => 'nonsense', 'id' => 1]])->assertStatus(422);
        $this->postJson("/api/chat/conversations/$g/messages", ['card' => ['kind' => 'payment', 'id' => $child->id, 'month' => '2026-11']])->assertCreated();

        $this->as('staff:aytam');
        $sent = $this->postJson("/api/chat/conversations/$g/messages", ['body' => 'Please look', 'card' => ['kind' => 'child', 'id' => $child->id]])->assertCreated();
        $sent->assertJsonPath('data.card.access', true)->assertJsonPath('data.card.href', 'person.html?id='.$child->id);
        $this->assertStringContainsString($child->ref, (string) $sent->json('data.card.sub'));
        $this->assertContains($child->name_en ?: $child->name_ar, [$sent->json('data.card.label')]);
        $this->postJson("/api/chat/conversations/$g/messages", ['card' => ['kind' => 'child', 'id' => 999999]])->assertStatus(422);
        $this->postJson("/api/chat/conversations/$g/messages", [])->assertStatus(422);

        // Finance reads the same message: reference and status only, no name, no link
        $this->as('staff:finance');
        $card = collect($this->getJson("/api/chat/conversations/$g/messages")->json('data'))->firstWhere('body', 'Please look')['card'];
        $this->assertFalse($card['access']);
        $this->assertNull($card['href']);
        $this->assertSame('Orphan '.$child->ref, $card['label']);
        $this->assertStringNotContainsString((string) ($child->name_en ?: 'zzz-no-name'), json_encode($card));

        // the President holds every office feature, so the same card opens for them
        $this->as('president_admin');
        $card = collect($this->getJson("/api/chat/conversations/$g/messages")->json('data'))->firstWhere('body', 'Please look')['card'];
        $this->assertTrue($card['access']);

        $this->assertSame(1, AuditLog::where('action', 'chat.share')->where('subject_type', 'child')->where('user_id', User::where('email', 'aytam@insania.test')->value('id'))->count());
        $this->assertSame(0, AuditLog::where('action', 'chat.share')->where('user_id', User::where('email', 'finance@insania.test')->value('id'))->where('subject_type', 'child')->count());   // the refused share left no entry
    }

    public function test_the_sender_is_told_who_in_the_chat_cannot_open_the_card(): void
    {
        $this->as('staff:aytam');
        $g = $this->general();
        $a = $this->getJson("/api/chat/conversations/$g/audience?kind=child")->assertOk()->json('data');
        $this->assertGreaterThan(0, $a['cannot_open']);                // Finance and Projects staff cannot open orphans
        $this->assertLessThan($a['total'], $a['cannot_open']);         // the President can
        $fin = User::where('email', 'finance@insania.test')->first();
        $c = $this->postJson('/api/chat/direct', ['user_id' => $fin->id])->json('data.id');
        $d = $this->getJson("/api/chat/conversations/$c/audience?kind=child")->assertOk()->json('data');
        $this->assertSame([$fin->name], $d['names']);
        $this->assertSame(0, $this->getJson("/api/chat/conversations/$c/audience?kind=payment")->json('data.cannot_open'));   // Finance can open payments
    }

    public function test_every_kind_of_card_resolves_with_the_right_link_and_the_right_permission(): void
    {
        $this->as('president_admin');
        $g = $this->general();
        $child = Child::orderBy('id')->first();
        $project = \App\Models\Project::first();
        $app = ChildApplication::withoutGlobalScopes()->create(['foundation_id' => $child->foundation_id, 'status' => 'new', 'data' => ['name_en' => 'Applicant One']]);
        $cards = [
            ['kind' => 'child', 'id' => $child->id, 'href' => 'person.html?id='.$child->id],
            ['kind' => 'application', 'id' => $app->id, 'href' => 'application.html?id='.$app->id],
            ['kind' => 'project', 'id' => $project->id, 'href' => 'projects.html?focus='.$project->id],
            ['kind' => 'payment', 'id' => $child->id, 'month' => '2026-11', 'href' => 'payments.html?month=2026-11&focus='.$child->id],
            ['kind' => 'user', 'id' => User::where('email', 'aytam@insania.test')->value('id'), 'href' => 'users.html?focus='.User::where('email', 'aytam@insania.test')->value('id')],
            ['kind' => 'report', 'month' => '2026-11', 'href' => 'reports.html?month=2026-11'],
            ['kind' => 'site', 'href' => 'website.html'],
        ];
        foreach ($cards as $c) {
            $href = $c['href'];
            unset($c['href']);
            $this->postJson("/api/chat/conversations/$g/messages", ['card' => $c])->assertCreated()->assertJsonPath('data.card.href', $href)->assertJsonPath('data.card.access', true);
        }
        $this->postJson("/api/chat/conversations/$g/messages", ['card' => ['kind' => 'report', 'month' => 'nope']])->assertStatus(422);
        $this->assertSame(7, ChatMessage::whereNotNull('card_kind')->count());
        $this->assertSame(['child', 'application', 'project', 'payment', 'user', 'report', 'site'], $this->getJson('/api/chat/unread')->json('data.shareable'));

        // Aytam staff can open orphans and applications only: they see the others without a link
        $this->as('staff:aytam');
        $rows = collect($this->getJson("/api/chat/conversations/$g/messages")->json('data'))->keyBy(fn ($m) => $m['card']['kind']);
        foreach (['child', 'application'] as $k) {
            $this->assertTrue($rows[$k]['card']['access'], $k);
        }
        foreach (['project', 'payment', 'user', 'report', 'site'] as $k) {
            $this->assertFalse($rows[$k]['card']['access'], $k);
            $this->assertNull($rows[$k]['card']['href'], $k);
        }
        $this->assertSame(['child', 'application'], $this->getJson('/api/chat/unread')->json('data.shareable'));
    }

    public function test_polling_returns_only_new_messages_and_other_foundations_never_see_the_chat(): void
    {
        $this->as('president_admin');
        $g = $this->general();
        $first = $this->postJson("/api/chat/conversations/$g/messages", ['body' => 'one'])->json('data.id');
        $this->postJson("/api/chat/conversations/$g/messages", ['body' => 'two'])->assertCreated();
        $new = $this->getJson("/api/chat/conversations/$g/messages?after_id=$first")->assertOk()->json('data');
        $this->assertSame(['two'], array_column($new, 'body'));

        $other = $this->otherFoundation();
        $this->app['auth']->forgetGuards();
        \Laravel\Sanctum\Sanctum::actingAs($other['president'], ['*']);
        $this->getJson("/api/chat/conversations/$g/messages")->assertNotFound();                  // not their conversation
        $this->assertNotSame($g, $this->getJson('/api/chat/conversations')->json('data.0.id'));
        $this->assertSame([], $this->getJson('/api/chat/people')->json('data'));
    }
}
