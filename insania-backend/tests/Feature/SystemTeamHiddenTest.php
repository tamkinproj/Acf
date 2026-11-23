<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** The System team (System Admin, Admin Maintenance) is not seen in the office: not in Accounts, not in the Activity log, not in the dashboard. */
class SystemTeamHiddenTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_the_president_never_sees_the_system_team_or_its_activity(): void
    {
        $this->world();
        $sys = User::where('email', $this->accounts['system_admin'])->firstOrFail();
        $mnt = User::where('email', $this->accounts['admin_maintenance'])->firstOrFail();
        $sec = User::where('email', $this->accounts['security_admin'])->firstOrFail();
        $pres = User::where('email', $this->accounts['president_admin'])->firstOrFail();
        $fid = $pres->foundation_id;
        AuditLog::query()->delete();
        $log = fn (?int $by, string $action, ?int $subject = null) => AuditLog::create(['foundation_id' => $fid, 'user_id' => $by, 'action' => $action, 'subject_type' => $subject ? 'user' : null, 'subject_id' => $subject]);
        $log($sys->id, 'login');                       // what the System Admin did
        $log($sys->id, 'settings.updated');
        $log(null, 'update.applied');                  // system-only work, even with no one named
        $log($sec->id, 'user.updated', $sys->id);      // something done to a System account
        $log($pres->id, 'login');                      // office activity stays
        $log($sec->id, 'user.created', $pres->id);

        $this->actingAsRole('president_admin');
        $rows = $this->getJson('/api/audit')->assertOk()->json('data');
        $this->assertSame([['user.created', $sec->id], ['login', $pres->id]], array_map(fn ($r) => [$r['action'], $r['actor']['id'] ?? null], $rows));
        $this->assertSame(0, count($this->getJson('/api/audit?area=system')->json('data')));

        $users = $this->getJson('/api/users?per_page=100')->assertOk();
        $roles = array_column($users->json('data'), 'role');
        $this->assertNotContains('system_admin', $roles);
        $this->assertContains('admin_maintenance', $roles, 'Admin Maintenance stays visible: the President can make those accounts');
        $this->assertContains('security_admin', $roles);
        $this->assertSame(count(array_filter($users->json('data'), fn ($u) => $u['account_type'] === 'staff')), $users->json('meta.counts.staff'));
        $this->patchJson('/api/users/'.$sys->id, ['active' => false])->assertNotFound();
        $this->postJson('/api/users/'.$sys->id.'/revoke-tokens')->assertNotFound();

        $dash = $this->getJson('/api/dashboard')->assertOk()->json('data.security');
        $this->assertArrayNotHasKey('system_admin', (array) $dash['by_role']);
        $this->assertNotContains('system_admin', array_column($dash['latest_users'], 'role'));

        // the Security Admin (technical) still sees everything
        $this->app['auth']->forgetGuards();
        $this->actingAsRole('security_admin');
        $this->assertCount(6, $this->getJson('/api/audit')->assertOk()->json('data'));
        $this->assertContains('system_admin', array_column($this->getJson('/api/users?per_page=100')->json('data'), 'role'));
    }

    public function test_the_system_team_does_not_see_the_office(): void
    {
        $this->world();
        $this->actingAsRole('system_admin');
        $this->getJson('/api/audit')->assertForbidden();
        $this->getJson('/api/users')->assertForbidden();
        $this->getJson('/api/children')->assertForbidden();
        $this->getJson('/api/chat/conversations')->assertForbidden();
        $this->getJson('/api/files')->assertForbidden();
        $this->getJson('/api/payments')->assertForbidden();
    }
}
