<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

/** The Accounts screen's three folders: staff, orphan and family accounts. */
class AccountFoldersTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    public function test_accounts_are_sorted_into_staff_orphan_and_family_with_counts_and_search(): void
    {
        $this->world();
        $admin = $this->actingAsRole('security_admin');
        $child = Child::withoutGlobalScopes()->where('foundation_id', $admin->foundation_id)->first() ?? Child::withoutGlobalScopes()->create(['foundation_id' => $admin->foundation_id, 'ref' => 'ACF-9999', 'name_en' => 'Sample Orphan']);
        $before = $this->getJson('/api/users')->json('meta.counts');
        $make = fn (array $o) => User::withoutGlobalScopes()->forceCreate($o + ['password' => bcrypt('Zebra!Pine7Sun42'), 'foundation_id' => $admin->foundation_id, 'active' => true]);
        $make(['name' => 'Orphan Account A', 'email' => 'oa@x.test', 'username' => 'oa1', 'role' => 'user', 'child_id' => $child->id]);
        $make(['name' => 'Family Account B', 'email' => 'fb@x.test', 'username' => 'fb1', 'role' => 'user']);

        $all = $this->getJson('/api/users')->assertOk();
        $this->assertSame($before['orphan'] + 1, $all->json('meta.counts.orphan'));
        $this->assertSame($before['family'] + 1, $all->json('meta.counts.family'));
        $this->assertGreaterThan(2, $all->json('meta.counts.staff'));

        $staff = $this->getJson('/api/users?type=staff&per_page=100')->assertOk()->json('data');
        $this->assertNotEmpty($staff);
        $this->assertSame([], array_values(array_filter($staff, fn ($u) => $u['account_type'] !== 'staff')));
        $this->assertSame(['Orphan Account A'], array_column($this->getJson('/api/users?type=orphan')->json('data'), 'name'));
        $this->assertContains('Family Account B', array_column($this->getJson('/api/users?type=family')->json('data'), 'name'));
        $this->assertSame(['Family Account B'], array_column($this->getJson('/api/users?q=fb1')->json('data'), 'name'));
        $this->getJson('/api/users?type=robots')->assertStatus(422);
        $this->getJson('/api/users?per_page=1000')->assertStatus(422);
    }
}
