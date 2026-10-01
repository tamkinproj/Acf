<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Foundation;
use App\Models\Location;
use App\Models\Role;
use App\Models\Setting;
use App\Models\SyncChange;
use App\Models\User;
use App\Tenancy\TenantContext;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

/** Foundation A must never see, change, or reference anything of Foundation B - and the reverse. */
class TenantIsolationTest extends TestCase
{
    use BootsFoundation;

    private Foundation $other;
    private User $otherAdmin;
    private User $otherStaff;
    private Location $otherPlace;
    private Location $myPlace;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
        [$this->other, $this->otherAdmin] = $this->createFoundation('Other Foundation', 'other-admin@example.test');
        $this->otherStaff = $this->makeUser('staff', 'other-staff@example.test', foundation: $this->other);

        $this->otherPlace = app(TenantContext::class)->runAs($this->other->id, fn () => Location::create(['level' => 'country', 'name' => 'Secret Land', 'path' => '/x/', 'depth' => 0]));
        $this->inFoundation($this->foundation);
        $this->myPlace = Location::create(['level' => 'country', 'name' => 'My Land', 'path' => '/y/', 'depth' => 0]);
    }

    public function test_lists_never_include_another_foundations_records(): void
    {
        $this->actingAs($this->admin);

        $emails = array_column($this->getJson('/api/users')->assertOk()->json('data'), 'email');
        $this->assertContains('admin@example.test', $emails);
        $this->assertNotContains('other-admin@example.test', $emails);
        $this->assertNotContains('other-staff@example.test', $emails);

        $roles = Role::query()->pluck('foundation_id')->unique()->all();
        $this->assertSame([$this->foundation->id], $roles);
        $this->assertSame(['Server'], array_column($this->getJson('/api/devices')->json('data'), 'name'));
        $this->assertStringNotContainsString('Other Foundation', $this->getJson('/api/audit-logs?per_page=100')->getContent());
        $this->assertStringNotContainsString('other-admin', $this->getJson('/api/audit-logs?per_page=100')->getContent());
    }

    public function test_direct_access_by_id_is_not_found(): void
    {
        $this->actingAs($this->admin);
        $theirRole = app(TenantContext::class)->asSystem(fn () => Role::where('foundation_id', $this->other->id)->where('key', 'staff')->first());
        $theirDevice = app(TenantContext::class)->asSystem(fn () => Device::where('foundation_id', $this->other->id)->first());

        $this->getJson('/api/users/'.$this->otherStaff->id)->assertStatus(404);
        $this->patchJson('/api/users/'.$this->otherStaff->id, ['name' => 'Taken over'])->assertStatus(404);
        $this->deleteJson('/api/users/'.$this->otherStaff->id)->assertStatus(404);
        $this->postJson('/api/users/'.$this->otherStaff->id.'/reset-password')->assertStatus(404);
        $this->putJson("/api/roles/{$theirRole->id}/permissions", ['permissions' => ['dashboard.view']])->assertStatus(404);
        $this->patchJson("/api/devices/{$theirDevice->id}", ['name' => 'x'])->assertStatus(404);
        $this->postJson("/api/devices/{$theirDevice->id}/revoke")->assertStatus(404);

        $this->assertNotSame('Taken over', app(TenantContext::class)->asSystem(fn () => User::find($this->otherStaff->id))->name);
        $this->assertNull(app(TenantContext::class)->asSystem(fn () => User::withTrashed()->find($this->otherStaff->id))->deleted_at);
    }

    public function test_references_to_another_foundations_records_are_refused(): void
    {
        $this->actingAs($this->admin);
        $theirRole = app(TenantContext::class)->asSystem(fn () => Role::where('foundation_id', $this->other->id)->where('key', 'staff')->first());

        $this->postJson('/api/users', ['name' => 'X', 'email' => 'x@example.test', 'role_id' => $theirRole->id])->assertStatus(422)->assertJsonValidationErrors('role_id');

        $r = $this->asDevice($this->admin)->postJson('/api/sync/push', ['changes' => [
            $this->change('locations', 'create', ['name' => 'Child', 'level' => 'region', 'parent_id' => $this->otherPlace->id]),
            $this->change('locations', 'update', ['name' => 'Hijacked'], $this->otherPlace->id, 1),
            $this->change('users', 'update', ['name' => 'Hijacked'], $this->otherStaff->id, 1),
            $this->change('foundations', 'update', ['default_location_id' => $this->otherPlace->id], $this->foundation->id, 1),
        ]])->json('data.results');
        $this->assertSame(['validation', 'not_found', 'not_found', 'validation'], array_column($r, 'code'));

        $this->assertSame('Secret Land', app(TenantContext::class)->asSystem(fn () => Location::find($this->otherPlace->id))->name);
    }

    public function test_the_sync_feed_and_pull_are_per_foundation(): void
    {
        $r = $this->asDevice($this->admin)->getJson('/api/sync/pull?since=0&limit=1000')->assertOk();
        $names = collect($r->json('data.changes'))->where('entity', 'locations')->pluck('payload.name')->all();
        $this->assertSame(['My Land'], $names);
        $this->assertNotContains('other-admin@example.test', collect($r->json('data.changes'))->pluck('payload.email')->all());

        $entityIds = SyncChange::query()->pluck('entity_id')->all();
        $this->assertNotContains($this->otherPlace->id, $entityIds);
        $this->assertNotContains($this->otherStaff->id, $entityIds);
    }

    public function test_a_device_token_belongs_to_one_foundation(): void
    {
        $theirDevice = app(TenantContext::class)->asSystem(fn () => Device::where('foundation_id', $this->other->id)->first());
        $theirToken = app(\App\Core\Devices\DeviceService::class)->issueToken($theirDevice);

        $this->actingAs($this->admin)->withToken($theirToken)->getJson('/api/sync/status')->assertStatus(401)->assertJsonPath('code', 'DEVICE_INVALID');
        $this->actingAs($this->otherAdmin)->withToken($theirToken)->getJson('/api/sync/status')->assertOk();
    }

    public function test_settings_are_independent(): void
    {
        $this->inFoundation($this->foundation);
        Setting::where('key', 'app.currency')->first()->update(['value' => 'SAR']);

        $this->assertSame('SAR', Setting::where('key', 'app.currency')->value('value'));
        $this->inFoundation($this->other);
        $this->assertSame('PHP', Setting::where('key', 'app.currency')->value('value'));
        $this->assertSame(1, Setting::where('key', 'app.currency')->count());
    }

    public function test_the_same_works_in_the_other_direction(): void
    {
        $this->actingAs($this->otherAdmin);
        $emails = array_column($this->getJson('/api/users')->json('data'), 'email');
        $this->assertContains('other-staff@example.test', $emails);
        $this->assertNotContains('admin@example.test', $emails);
        $this->getJson('/api/users/'.$this->admin->id)->assertStatus(404);
    }

    public function test_an_unresolved_context_reads_nothing_and_writes_nothing(): void
    {
        app(TenantContext::class)->reset();
        $this->assertSame(0, User::count());
        $this->assertSame(0, Location::count());
        $this->assertSame(0, AuditLog::count());

        $this->expectException(\LogicException::class);
        Location::create(['level' => 'country', 'name' => 'Nowhere', 'path' => '/z/', 'depth' => 0]);
    }

    public function test_a_record_cannot_be_written_into_or_moved_to_another_foundation(): void
    {
        $this->inFoundation($this->foundation);
        try {
            Location::create(['foundation_id' => $this->other->id, 'level' => 'country', 'name' => 'Smuggled', 'path' => '/s/', 'depth' => 0]);
            $this->fail('creating for another foundation must be refused');
        } catch (\LogicException $e) {
            $this->assertStringContainsString('another foundation', $e->getMessage());
        }
        $this->expectException(\LogicException::class);
        $this->myPlace->update(['foundation_id' => $this->other->id]);
    }

    public function test_platform_scope_cannot_see_foundation_records_and_foundation_users_cannot_enter_the_platform(): void
    {
        $this->inPlatform();
        $this->assertSame(1, User::count(), 'only the platform administrator is visible at platform level');
        $this->assertSame(0, Location::count());
        $this->assertSame(0, Device::count());

        $this->actingAs($this->platformAdmin);
        foreach (['/api/users', '/api/audit-logs', '/api/devices', '/api/roles', '/api/dashboard/summary', '/api/foundation', '/api/sync/status'] as $path) {
            $this->getJson($path)->assertStatus(403);
        }

        $this->actingAs($this->admin);
        foreach (['/api/platform/dashboard', '/api/platform/foundations', '/api/platform/users', '/api/platform/activity'] as $path) {
            $this->getJson($path)->assertStatus(403);
        }
    }
}
