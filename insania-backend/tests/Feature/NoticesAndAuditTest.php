<?php

namespace Tests\Feature;

use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class NoticesAndAuditTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private function uid(string $email): int
    {
        return User::where('email', $email)->value('id');
    }

    public function test_every_signed_in_person_has_a_bell_with_their_own_messages_only(): void
    {
        $this->world();
        foreach (['staff:aytam', 'president_admin', 'user', 'system_admin', 'staff:finance'] as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/notices/count')->assertOk()->assertJsonStructure(['data' => ['notices', 'chat', 'total']]);
            $this->getJson('/api/notices')->assertOk();
        }
        $me = $this->uid('aytam@insania.test');
        $other = $this->uid('finance@insania.test');
        $f = User::find($me)->foundation_id;
        \App\Support\Notify::to($me, $f, 'system', 'For Aytam', 'body', 'settings.html');
        \App\Support\Notify::to($other, $f, 'system', 'For Finance', null, null);

        $this->actingAsRole('staff:aytam');
        $list = $this->getJson('/api/notices')->assertOk()->json('data');
        $this->assertSame(['For Aytam'], collect($list['items'])->where('kind', 'notice')->pluck('title')->all());
        $this->assertSame(1, $this->getJson('/api/notices/count')->json('data.notices'));
        $this->postJson('/api/notices/read', [])->assertOk();
        $this->assertSame(0, $this->getJson('/api/notices/count')->json('data.notices'));
        $this->assertNull(Notice::where('user_id', $other)->value('read_at'), 'reading yours never marks someone else\'s');
        $this->assertFalse(collect($this->getJson('/api/notices')->json('data.items'))->firstWhere('title', 'For Aytam')['unread']);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/notices')->assertUnauthorized();
    }

    public function test_the_things_that_happen_ring_the_right_bells(): void
    {
        $this->world();
        // a new application tells the people who review applications (not the others)
        config(['orphan_fields.apply.per_hour' => 1000]);
        $aytam = $this->uid('aytam@insania.test');
        $fin = $this->uid('finance@insania.test');
        $this->actingAsRole('staff:aytam');
        // a group tells the people added
        $gid = $this->postJson('/api/chat/groups', ['title' => 'Bell group', 'user_ids' => [$fin]])->json('data.id');
        $this->assertSame(1, Notice::where('user_id', $fin)->where('type', 'chat')->count());
        $this->assertSame(0, Notice::where('user_id', $aytam)->where('type', 'chat')->count());
        $this->actingAsRole('staff:finance');
        $items = collect($this->getJson('/api/notices')->json('data.items'));
        $this->assertSame('You were added to a group', $items->firstWhere('kind', 'notice')['title']);
        $this->assertSame('room.html?c='.$gid, $items->firstWhere('kind', 'notice')['url']);

        // unread chat messages are listed with their count
        $this->actingAsRole('staff:aytam');
        $this->postJson("/api/chat/conversations/$gid/messages", ['body' => 'one'])->assertCreated();
        $this->postJson("/api/chat/conversations/$gid/messages", ['body' => 'two'])->assertCreated();
        $this->actingAsRole('staff:finance');
        $chat = collect($this->getJson('/api/notices')->json('data.items'))->firstWhere('kind', 'chat');
        $this->assertSame(2, $chat['count']);
        $this->assertSame('Bell group', $chat['title']);
        $this->assertSame(2, $this->getJson('/api/notices/count')->json('data.chat'));

        // a password reset by an administrator tells that person
        $this->actingAsRole('president_admin');
        $this->postJson('/api/users', ['name' => 'New Person', 'email' => 'np@insania.test', 'password' => 'Mango-Lantern-Quiet-81', 'role' => 'staff', 'department_key' => 'finance', 'confirm_password' => 'DemoPass#2026'])->assertCreated();
        $np = $this->uid('np@insania.test');
        $this->assertSame('Welcome to Al Insania', Notice::where('user_id', $np)->value('title'));
    }

    public function test_education_contract_warnings_come_once(): void
    {
        $this->world();
        $this->actingAsRole('staff:education');
        $id = $this->postJson('/api/edu/teachers', ['name_en' => 'Warned', 'start_date' => now()->subMonths(5)->subDays(5)->toDateString(), 'monthly_salary' => 100])->json('data.id');
        \Illuminate\Support\Facades\Cache::flush();
        $n = collect($this->getJson('/api/notices')->json('data.items'))->where('type', 'education');
        $this->assertCount(1, $n);
        $this->assertStringContainsString('ending soon', $n->first()['title']);
        \Illuminate\Support\Facades\Cache::flush();
        $this->assertCount(1, collect($this->getJson('/api/notices')->json('data.items'))->where('type', 'education'), 'told once, not on every look');
        $this->actingAsRole('staff:aytam');
        $this->assertCount(0, collect($this->getJson('/api/notices')->json('data.items'))->where('type', 'education'));
        $this->assertNotNull($id);
    }

    public function test_the_activity_log_says_who_did_what_with_which_record(): void
    {
        $this->world();
        $this->actingAsRole('staff:education');
        $tid = $this->postJson('/api/edu/teachers', ['name_en' => 'Ustadh Log', 'start_date' => '2026-01-01', 'monthly_salary' => 5000])->json('data.id');
        $this->postJson("/api/edu/teachers/$tid/contracts", [])->assertCreated();
        $this->actingAsRole('staff:aytam');
        $child = \App\Models\Child::first();
        $this->patchJson("/api/children/{$child->id}", ['grade' => 'Grade 9', 'city' => 'Molave'])->assertOk();

        $this->actingAsRole('president_admin');
        $rows = collect($this->getJson('/api/audit?per_page=100')->assertOk()->json('data'));
        $renew = $rows->firstWhere('action', 'edu.contract_renewed');
        $this->assertSame('Renewed a teacher\'s contract', $renew['text']['en']);
        $this->assertSame('جدّد عقد معلم', $renew['text']['ar']);
        $this->assertSame('Education Staff', $renew['actor']['name']);
        $this->assertSame('education', $renew['area']);
        $this->assertMatchesRegularExpression('/^ALM-\d{4}$/', $renew['subject']['label']);
        $this->assertSame('Until', $renew['details'][0]['en']);
        $edit = $rows->firstWhere('action', 'child.updated');
        $this->assertSame($child->ref, $edit['subject']['label']);
        $this->assertSame('Changed', $edit['details'][0]['en']);
        $this->assertStringContainsString('grade', $edit['details'][0]['value']);
        $this->assertStringNotContainsString($child->name_en, json_encode($edit), 'no orphan names in the log');
        $login = $rows->firstWhere('action', 'login');
        $this->assertNull($login['subject'] ?? null);

        $only = $this->getJson('/api/audit?area=education&per_page=100')->json('data');
        $this->assertNotEmpty($only);
        $this->assertSame(['education'], collect($only)->pluck('area')->unique()->values()->all());
        $this->getJson('/api/audit?area=nonsense')->assertStatus(422);
        $this->actingAsRole('staff:aytam');
        $this->getJson('/api/audit')->assertForbidden();
    }
}
