<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkActivity;
use App\Services\AttendanceCalc;
use App\Services\WorkTracker;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class WorkTrackerTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->world();
        config(['attendance.timezone' => 'Asia/Manila']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_only_hr_and_the_president_see_the_tracker(): void
    {
        foreach (['staff:aytam', 'staff:finance', 'staff:education', 'user', 'system_admin', 'security_admin'] as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/work/summary')->assertForbidden();
        }
        foreach (['staff:hr', 'president_admin'] as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/work/summary')->assertOk();
        }
    }

    public function test_staff_use_of_the_app_is_recorded_as_counts_and_times(): void
    {
        $this->actingAsRole('staff:aytam');
        $uid = User::where('email', 'aytam@insania.test')->value('id');
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:00:00', 'Asia/Manila')->utc());
        $this->getJson('/api/children')->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:02:00', 'Asia/Manila')->utc());
        $this->getJson('/api/children')->assertOk();
        Carbon::setTestNow(Carbon::parse('2026-03-10 09:30:00', 'Asia/Manila')->utc());   // a long gap is not working time
        $this->getJson('/api/children')->assertOk();
        $this->getJson('/api/notices/count')->assertOk();                                  // the app's own background check is not activity
        $this->getJson('/api/chat/unread')->assertOk();

        $row = WorkActivity::where('user_id', $uid)->first();
        $this->assertSame('2026-03-10', $row->day->toDateString());
        $this->assertSame(3, $row->requests);
        $this->assertSame(120, $row->active_seconds);
        $this->assertSame(3, $row->views);
        $this->assertSame(0, $row->actions);
        $this->assertSame(['orphans' => 3], $row->areas);
    }

    public function test_the_summary_and_detail_show_active_time_actions_and_last_seen(): void
    {
        $uid = User::where('email', 'aytam@insania.test')->value('id');
        $now = AttendanceCalc::now();
        WorkActivity::withoutGlobalScopes()->create(['foundation_id' => User::find($uid)->foundation_id, 'user_id' => $uid, 'day' => $now->toDateString(), 'first_at' => $now->copy()->subHours(3)->utc(), 'last_at' => $now->copy()->subMinutes(2)->utc(),
            'active_seconds' => 7200, 'requests' => 40, 'views' => 30, 'actions' => 10, 'areas' => ['orphans' => 30, 'chat' => 10]]);
        $this->actingAsRole('staff:hr');
        $s = $this->getJson('/api/work/summary?range=today')->assertOk()->json('data');
        $row = collect($s['staff'])->firstWhere('id', $uid);
        $this->assertSame('active', $row['status']);
        $this->assertSame(7200, $row['active_seconds']);
        $this->assertSame(10, $row['actions']);
        $this->assertSame('orphans', array_key_first($row['areas']));
        $this->assertGreaterThanOrEqual(1, $s['totals']['active_now']);
        $this->assertNotContains($uid + 1000, array_column($s['staff'], 'id'));

        $d = $this->getJson("/api/work/staff/$uid")->assertOk()->json('data');
        $this->assertSame(7200, $d['totals']['active_seconds']);
        $this->assertSame(1, $d['totals']['days_active']);
        $this->assertArrayHasKey('recent', $d);
        // the President and system accounts are not in the staff list
        $this->assertNotContains(User::where('email', 'president@insania.test')->value('id'), array_column($s['staff'], 'id'));
        $this->getJson('/api/work/staff/'.User::where('email', 'president@insania.test')->value('id'))->assertNotFound();
    }

    public function test_writes_count_as_actions_and_areas_follow_the_address(): void
    {
        $this->assertSame('orphans', WorkTracker::area('api/children/5'));
        $this->assertSame('finance', WorkTracker::area('api/payments'));
        $this->assertSame('education', WorkTracker::area('api/edu/teachers'));
        $this->assertSame('chat', WorkTracker::area('api/chat/conversations/3/messages'));
        $this->assertSame('other', WorkTracker::area('api/dashboard'));
    }

    public function test_samples_can_be_loaded_and_removed(): void
    {
        $this->actingAsRole('staff:hr');
        $this->postJson('/api/work/samples')->assertCreated();
        $this->assertGreaterThan(20, WorkActivity::where('is_sample', true)->count());
        $this->deleteJson('/api/work/samples')->assertOk();
        $this->assertSame(0, WorkActivity::where('is_sample', true)->count());
    }
}
