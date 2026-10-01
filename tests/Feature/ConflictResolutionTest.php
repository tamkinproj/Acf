<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\SyncConflict;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class ConflictResolutionTest extends TestCase
{
    use BootsFoundation;

    private string $locId;
    private string $otherToken;
    private SyncConflict $conflict;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
        [, $this->otherToken] = $this->newDevice('Field Phone');

        $create = $this->change('locations', 'create', ['name' => 'PH', 'level' => 'country', 'code' => 'P1']);
        $this->locId = $create['entity_id'];
        $this->push([$create]);
        $this->push([$this->change('locations', 'update', ['name' => 'Server Name'], $this->locId, 1)], $this->otherToken);
        $this->push([$this->change('locations', 'update', ['name' => 'Offline Name', 'code' => 'P2'], $this->locId, 1)]);
        $this->conflict = SyncConflict::sole();
    }

    private function push(array $changes, ?string $token = null): void
    {
        $this->asDevice($this->admin, $token)->postJson('/api/sync/push', ['changes' => $changes])->assertOk();
    }

    public function test_the_conflict_is_listed_for_its_device_and_for_managers(): void
    {
        $this->assertSame('open', $this->conflict->status);
        // The whole change conflicts because `name` overlaps: nothing partial (e.g. the code) was applied.
        $this->assertSame('P1', Location::find($this->locId)->code);

        $mine = $this->asDevice($this->admin)->getJson('/api/sync/conflicts?status=open')->assertOk()->json('data');
        $this->assertCount(1, $mine);

        // A different device's staff member sees only their own device's conflicts; a manager sees all.
        $fieldWorker = $this->makeUser('field_worker');   // no sync.manage
        $this->assertCount(0, $this->asDevice($fieldWorker, $this->otherToken)->getJson('/api/sync/conflicts')->json('data'));
        $this->assertCount(1, $this->asDevice($this->makeUser('foundation_admin'), $this->otherToken)->getJson('/api/sync/conflicts')->json('data'));
        $this->asDevice($this->admin)->getJson('/api/sync/status')->assertJsonPath('data.open_conflicts', 1);
    }

    public function test_accept_server_keeps_the_server_value_and_closes_the_conflict(): void
    {
        $this->asDevice($this->admin)->postJson("/api/sync/conflicts/{$this->conflict->id}/resolve", ['resolution' => 'accept_server'])
            ->assertOk()->assertJsonPath('data.status', 'resolved')->assertJsonPath('data.resolution', 'accept_server');

        $this->assertSame('Server Name', Location::find($this->locId)->name);
        $this->assertSame($this->admin->id, $this->conflict->fresh()->resolved_by);
        $this->assertTrue(AuditLog::where('action', 'sync.conflict_resolved')->exists());
    }

    public function test_accept_local_reapplies_the_preserved_change_on_top_of_the_current_version(): void
    {
        $this->asDevice($this->admin)->postJson("/api/sync/conflicts/{$this->conflict->id}/resolve", ['resolution' => 'accept_local'])->assertOk();

        $loc = Location::find($this->locId);
        $this->assertSame(['Offline Name', 'P2', 3], [$loc->name, $loc->code, $loc->version]);
        $this->assertSame('resolved', $this->conflict->fresh()->status);
    }

    public function test_merged_applies_the_chosen_fields(): void
    {
        $this->asDevice($this->admin)->postJson("/api/sync/conflicts/{$this->conflict->id}/resolve", ['resolution' => 'merged', 'fields' => ['name' => 'Server Name + Offline', 'code' => 'P2']])->assertOk();

        $this->assertSame('Server Name + Offline', Location::find($this->locId)->name);
        $this->assertSame('merged', $this->conflict->fresh()->resolution);
    }

    public function test_merged_edits_are_still_validated(): void
    {
        $this->asDevice($this->admin)->postJson("/api/sync/conflicts/{$this->conflict->id}/resolve", ['resolution' => 'merged', 'fields' => ['name' => '', 'path' => '/x/']])->assertStatus(422);
        $this->assertSame('open', $this->conflict->fresh()->status);
        $this->assertSame('Server Name', Location::find($this->locId)->name);
    }

    public function test_only_managers_can_resolve_and_only_once(): void
    {
        $this->asDevice($this->makeUser('viewer'))->postJson("/api/sync/conflicts/{$this->conflict->id}/resolve", ['resolution' => 'accept_server'])->assertStatus(403);
        $this->asDevice($this->admin)->postJson("/api/sync/conflicts/{$this->conflict->id}/resolve", ['resolution' => 'bogus'])->assertStatus(422);

        $this->asDevice($this->admin)->postJson("/api/sync/conflicts/{$this->conflict->id}/resolve", ['resolution' => 'accept_server'])->assertOk();
        $this->asDevice($this->admin)->postJson("/api/sync/conflicts/{$this->conflict->id}/resolve", ['resolution' => 'accept_local'])
            ->assertStatus(422)->assertJsonPath('code', 'ALREADY_RESOLVED');
        $this->assertSame('Server Name', Location::find($this->locId)->name);
    }

    public function test_a_manager_without_the_entity_permission_cannot_force_a_change_through(): void
    {
        // sync.manage but no locations.manage: the normal applier checks still apply to the re-applied change.
        $role = \App\Models\Role::where('key', 'viewer')->first();
        $role->update(['permissions' => ['dashboard.view', 'sync.use', 'sync.manage']]);
        $user = $this->makeUser('viewer');

        $this->asDevice($user)->postJson("/api/sync/conflicts/{$this->conflict->id}/resolve", ['resolution' => 'accept_local'])->assertStatus(422)->assertJsonPath('code', 'FORBIDDEN');
        $this->assertSame('Server Name', Location::find($this->locId)->name);
        $this->assertSame('open', $this->conflict->fresh()->status);
    }
}
