<?php

namespace Tests\Feature;

use App\Models\Device;
use App\Models\Location;
use App\Models\SyncChange;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class SyncPullTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
    }

    private function pull(int $since, array $query = [], $user = null, ?string $token = null)
    {
        return $this->asDevice($user ?? $this->admin, $token)->getJson('/api/sync/pull?'.http_build_query(['since' => $since] + $query));
    }

    private function push(array $changes, $user = null, ?string $token = null)
    {
        return $this->asDevice($user ?? $this->admin, $token)->postJson('/api/sync/push', ['changes' => $changes])->assertOk();
    }

    public function test_first_pull_returns_the_installed_state_in_order(): void
    {
        $r = $this->pull(0, ['limit' => 1000])->assertOk();
        $changes = $r->json('data.changes');

        $entities = array_unique(array_column($changes, 'entity'));
        foreach (['roles', 'settings', 'foundations', 'users', 'devices', 'audit_logs'] as $e) {
            $this->assertContains($e, $entities, $e);
        }
        $seqs = array_column($changes, 'seq');
        $sorted = $seqs;
        sort($sorted);
        $this->assertSame($sorted, $seqs);
        $this->assertFalse($r->json('data.has_more'));
        $this->assertSame(end($seqs), $r->json('data.next_seq'));
    }

    public function test_pagination_walks_the_whole_feed_without_gaps_or_repeats(): void
    {
        for ($i = 0; $i < 12; $i++) {
            $this->push([$this->change('locations', 'create', ['name' => "L{$i}", 'level' => 'country'])]);
        }
        $total = SyncChange::count();

        $seen = [];
        $cursor = 0;
        $pages = 0;
        do {
            $r = $this->pull($cursor, ['limit' => 7])->json('data');
            foreach ($r['changes'] as $c) {
                $this->assertNotContains($c['seq'], $seen, 'a change must not be delivered twice');
                $seen[] = $c['seq'];
            }
            $cursor = $r['next_seq'];
            $pages++;
        } while ($r['has_more'] && $pages < 100);

        $this->assertCount($total, $seen);
        $this->assertGreaterThan(2, $pages);

        // Caught up: nothing new, cursor does not regress.
        $idle = $this->pull($cursor)->json('data');
        $this->assertSame([[], false, $cursor], [$idle['changes'], $idle['has_more'], $idle['next_seq']]);

        // New work shows up after the cursor.
        $this->push([$this->change('locations', 'create', ['name' => 'Late', 'level' => 'country'])]);
        $delta = $this->pull($cursor)->json('data.changes');
        $this->assertSame('locations', $delta[0]['entity']);
        $this->assertSame('Late', $delta[0]['payload']['name']);
    }

    public function test_pull_only_returns_entities_the_user_may_read(): void
    {
        $viewer = $this->makeUser('viewer');
        $entities = array_unique(array_column($this->pull(0, ['limit' => 1000], $viewer)->json('data.changes'), 'entity'));
        sort($entities);

        $this->assertSame(['foundations', 'roles', 'settings'], $entities);   // no users, devices, audit_logs
        $this->push([$this->change('locations', 'create', ['name' => 'Visible', 'level' => 'country'])]);
        $this->assertContains('locations', array_column($this->pull(0, ['limit' => 1000], $viewer)->json('data.changes'), 'entity'));

        $auditor = $this->makeUser('foundation_admin');
        $this->assertContains('audit_logs', array_column($this->pull(0, ['limit' => 1000], $auditor)->json('data.changes'), 'entity'));
    }

    public function test_entities_filter_and_validation(): void
    {
        $only = $this->pull(0, ['entities' => ['roles'], 'limit' => 1000])->json('data.changes');
        $this->assertSame(['roles'], array_values(array_unique(array_column($only, 'entity'))));

        // Asking for something you may not read yields nothing, not an error or a leak.
        $this->assertSame([], $this->pull(0, ['entities' => ['users']], $this->makeUser('viewer'))->json('data.changes'));
        $this->asDevice($this->admin)->getJson('/api/sync/pull')->assertStatus(422);
        $this->asDevice($this->admin)->getJson('/api/sync/pull?since=-1')->assertStatus(422);
        $this->asDevice($this->admin)->getJson('/api/sync/pull?since=0&limit=999999')->assertStatus(422);
    }

    public function test_credentials_and_server_only_columns_never_leave_the_server(): void
    {
        $json = json_encode($this->pull(0, ['limit' => 1000])->json('data.changes'));
        foreach (['$2y$', 'password', 'remember_token', 'token_hash', 'logo_path', 'last_login_at', 'last_seen_at'] as $needle) {
            $this->assertStringNotContainsString($needle, $json, $needle);
        }
    }

    public function test_deletes_are_delivered_as_tombstones(): void
    {
        $c = $this->change('locations', 'create', ['name' => 'Gone', 'level' => 'country']);
        $this->push([$c]);
        $cursor = $this->pull(0, ['limit' => 1000])->json('data.next_seq');

        $this->push([$this->change('locations', 'delete', [], $c['entity_id'], 1)]);
        $change = $this->pull($cursor)->json('data.changes.0');

        $this->assertSame(['delete', 2, $c['entity_id']], [$change['op'], $change['version'], $change['entity_id']]);
        $this->assertNotNull($change['payload']['deleted_at']);
    }

    public function test_changes_made_by_another_device_are_visible_with_its_identity(): void
    {
        [$other, $otherToken] = $this->newDevice('Field Phone');
        $c = $this->change('locations', 'create', ['name' => 'From the field', 'level' => 'country']);
        $this->push([$c], null, $otherToken);

        $got = collect($this->pull(0, ['limit' => 1000])->json('data.changes'))->firstWhere('entity_id', $c['entity_id']);
        $this->assertSame($other->id, $got['device_id']);
        $this->assertSame($c['change_id'], $got['change_id']);
    }

    public function test_recent_rows_are_withheld_until_they_settle(): void
    {
        config(['foundation.sync.settle_seconds' => 3600]);
        $this->assertSame([], $this->pull(0, ['limit' => 1000])->json('data.changes'));
        $this->assertSame(0, $this->pull(0)->json('data.next_seq'));

        config(['foundation.sync.settle_seconds' => 0]);
        $this->assertNotEmpty($this->pull(0)->json('data.changes'));
    }

    public function test_pull_records_the_devices_acknowledged_cursor(): void
    {
        $max = (int) SyncChange::max('seq');
        $this->pull($max);
        $this->assertSame($max, Device::find($this->device->id)->last_pull_seq);
        $this->assertNotNull(Device::find($this->device->id)->last_seen_at);

        $this->pull(1);   // an older cursor never moves it backwards
        $this->assertSame($max, Device::find($this->device->id)->last_pull_seq);
    }

    public function test_status_and_schema_describe_what_this_device_may_do(): void
    {
        $this->asDevice($this->admin)->getJson('/api/sync/status')->assertOk()
            ->assertJsonPath('data.online', true)->assertJsonPath('data.device.code', $this->device->device_code)
            ->assertJsonPath('data.open_conflicts', 0)->assertJsonPath('data.schema_version', (int) config('foundation.schema_version'));

        $viewer = $this->asDevice($this->makeUser('viewer'))->getJson('/api/sync/schema')->assertOk()->json('data.entities');
        $this->assertFalse($viewer['users']['can_pull']);
        $this->assertTrue($viewer['locations']['can_pull']);
        $this->assertFalse($viewer['locations']['ops']['create']);

        $staff = $this->asDevice($this->makeUser('staff'))->getJson('/api/sync/schema')->json('data.entities');
        $this->assertSame(['create' => true, 'update' => true, 'delete' => true], $staff['locations']['ops']);
        $this->assertContains('name', $staff['locations']['writable']);
        $this->assertNotContains('path', $staff['locations']['writable']);
    }

    public function test_the_feed_alone_is_enough_to_rebuild_the_server_state(): void
    {
        [$other, $otherToken] = $this->newDevice('Field Phone');

        $ph = $this->change('locations', 'create', ['name' => 'PH', 'level' => 'country']);
        $a = $this->change('locations', 'create', ['name' => 'Town A', 'level' => 'municipality', 'parent_id' => $ph['entity_id']]);
        $b = $this->change('locations', 'create', ['name' => 'Region B', 'level' => 'region', 'parent_id' => $ph['entity_id']]);
        $s1 = $this->change('locations', 'create', ['name' => 'Site 1', 'level' => 'site', 'parent_id' => $a['entity_id']]);
        $s2 = $this->change('locations', 'create', ['name' => 'Site 2', 'level' => 'site', 'parent_id' => $a['entity_id']]);
        $this->push([$ph, $a, $b, $s1, $s2]);

        $this->push([$this->change('locations', 'update', ['name' => 'Site One', 'code' => 'S1'], $s1['entity_id'], 1)]);
        $moved = $this->push([$this->change('locations', 'update', ['parent_id' => $b['entity_id']], $a['entity_id'], 1)]);       // move subtree
        $this->assertSame('applied', $moved->json('data.results.0.status'));
        $this->push([$this->change('locations', 'update', ['latitude' => 6.9, 'longitude' => 122.1], $s2['entity_id'], 1)], null, $otherToken);
        $this->push([$this->change('locations', 'delete', [], $s2['entity_id'], 2)], null, $otherToken);
        $this->push([$this->change('locations', 'update', ['name' => 'Clash'], $s1['entity_id'], 1)], null, $otherToken);   // conflict: no feed row

        $store = [];
        $cursor = 0;
        do {
            $r = $this->pull($cursor, ['limit' => 4, 'entities' => ['locations']])->json('data');
            foreach ($r['changes'] as $c) {
                $store[$c['entity_id']] = $c['op'] === 'create' ? $c['payload'] : array_merge($store[$c['entity_id']], $c['payload']);
            }
            $cursor = $r['next_seq'];
        } while ($r['has_more']);

        $this->assertCount(5, $store);
        foreach (Location::withTrashed()->get() as $loc) {
            $this->assertEquals($loc->toSyncPayload(), $store[$loc->id], "replayed state of {$loc->name} must equal the server's");
        }
        $this->assertSame('Site One', $store[$s1['entity_id']]['name']);
        $this->assertSame("/{$ph['entity_id']}/{$b['entity_id']}/{$a['entity_id']}/{$s1['entity_id']}/", $store[$s1['entity_id']]['path']);
        $this->assertNotNull($store[$s2['entity_id']]['deleted_at']);
    }

    public function test_timestamps_are_utc_and_unaffected_by_the_display_timezone_setting(): void
    {
        $c = $this->change('locations', 'create', ['name' => 'T', 'level' => 'country']);
        $this->push([$c]);
        $before = Location::find($c['entity_id'])->toSyncPayload()['created_at'];
        $this->assertStringEndsWith('Z', $before);

        $tz = \App\Models\Setting::where('key', 'app.timezone')->first();
        $this->push([$this->change('settings', 'update', ['value' => 'Pacific/Auckland'], $tz->id, 1)]);
        app(\App\Core\Settings\SettingsService::class)->forget();
        app(\App\Core\Settings\SettingsService::class)->applyRuntime();

        $this->assertSame('UTC', config('app.timezone'));
        $this->assertSame($before, Location::find($c['entity_id'])->fresh()->toSyncPayload()['created_at']);
    }
}
