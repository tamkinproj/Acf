<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\SyncChange;
use App\Models\SyncConflict;
use App\Models\User;
use App\Sync\EntityDefinition;
use App\Sync\SyncRegistry;
use Illuminate\Support\Str;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class SyncPushTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
    }

    private function push(array $changes, ?User $as = null, ?string $token = null)
    {
        return $this->asDevice($as ?? $this->admin, $token)->postJson('/api/sync/push', ['changes' => $changes]);
    }

    private function one(array $change, ?User $as = null, ?string $token = null): array
    {
        return $this->push([$change], $as, $token)->assertOk()->json('data.results.0');
    }

    private function loc(string $name = 'Philippines', string $level = 'country', ?string $parent = null): array
    {
        return $this->change('locations', 'create', array_filter(['name' => $name, 'level' => $level, 'parent_id' => $parent]));
    }

    // ---- create / idempotency ---------------------------------------------

    public function test_create_is_applied_stamped_and_gets_server_derived_tree_fields(): void
    {
        $change = $this->loc('Philippines');
        $r = $this->one($change);

        $this->assertSame('applied', $r['status']);
        $this->assertSame(1, $r['version']);
        $row = Location::findOrFail($change['entity_id']);
        $this->assertSame("/{$row->id}/", $row->path);
        $this->assertSame(0, $row->depth);
        $this->assertSame($this->device->id, $row->origin_device_id);
        $this->assertSame($this->admin->id, $row->created_by);

        $feed = SyncChange::where('change_id', $change['change_id'])->sole();
        $this->assertSame(['locations', 'create'], [$feed->entity, $feed->op]);
    }

    public function test_the_same_change_sent_twice_is_applied_exactly_once(): void
    {
        $change = $this->loc();
        $first = $this->one($change);
        $second = $this->one($change);
        $third = $this->push([$change, $change])->json('data.results');

        $this->assertSame('applied', $first['status']);
        $this->assertSame('duplicate', $second['status']);
        $this->assertSame($first['version'], $second['version']);
        $this->assertSame(['duplicate', 'duplicate'], array_column($third, 'status'));
        $this->assertSame(1, Location::count());
        $this->assertSame(1, SyncChange::where('change_id', $change['change_id'])->count());
    }

    public function test_a_retried_batch_after_a_lost_response_does_not_duplicate_anything(): void
    {
        $a = $this->loc('A');
        $b = $this->loc('B');
        $batch = [$a, $b, $this->change('locations', 'update', ['name' => 'A2'], $a['entity_id'], 1)];

        $this->push($batch)->assertOk();
        $retry = $this->push($batch)->json('data.results');

        $this->assertSame(['duplicate', 'duplicate', 'duplicate'], array_column($retry, 'status'));
        $this->assertSame(2, Location::count());
        $this->assertSame('A2', Location::find($a['entity_id'])->name);
        $this->assertSame(2, Location::find($a['entity_id'])->version);
    }

    public function test_parent_and_children_in_one_batch_apply_in_order(): void
    {
        $country = $this->loc('Philippines', 'country');
        $region = $this->loc('BARMM', 'region', $country['entity_id']);
        $site = $this->loc('Depot', 'site', $region['entity_id']);

        $results = $this->push([$country, $region, $site])->json('data.results');
        $this->assertSame(['applied', 'applied', 'applied'], array_column($results, 'status'));

        $site = Location::find($site['entity_id']);
        $this->assertSame(2, $site->depth);
        $this->assertSame("/{$country['entity_id']}/{$region['entity_id']}/{$site->id}/", $site->path);
    }

    public function test_child_before_parent_is_rejected_not_half_applied(): void
    {
        $country = $this->loc('Philippines');
        $region = $this->loc('BARMM', 'region', $country['entity_id']);

        $results = $this->push([$region, $country])->json('data.results');
        $this->assertSame('rejected', $results[0]['status']);
        $this->assertSame('validation', $results[0]['code']);
        $this->assertSame('applied', $results[1]['status']);
        $this->assertSame(1, Location::count());
    }

    public function test_a_client_cannot_set_server_controlled_fields(): void
    {
        $c = $this->change('locations', 'create', ['name' => 'X', 'level' => 'country', 'path' => '/evil/', 'depth' => 9]);
        $r = $this->one($c);
        $this->assertSame(['rejected', 'invalid_field'], [$r['status'], $r['code']]);

        // Meta fields a client may echo back are ignored, not trusted.
        $c = $this->change('locations', 'create', ['name' => 'Y', 'level' => 'country', 'version' => 99, 'created_by' => 'someone-else', 'origin_device_id' => 'x']);
        $this->assertSame('applied', $this->one($c)['status']);
        $row = Location::find($c['entity_id']);
        $this->assertSame([1, $this->admin->id], [$row->version, $row->created_by]);
    }

    public function test_hierarchy_rules_are_enforced(): void
    {
        $country = $this->loc('PH', 'country');
        $brgy = $this->loc('Poblacion', 'barangay', $country['entity_id']);   // skipping levels is allowed
        $this->push([$country, $brgy]);

        $this->assertSame('hierarchy', $this->one($this->loc('Wrong', 'country', $brgy['entity_id']))['code']);
        $this->assertSame('hierarchy', $this->one($this->loc('Same', 'barangay', $brgy['entity_id']))['code']);

        // Moving a location inside its own subtree is refused.
        $cycle = $this->change('locations', 'update', ['parent_id' => $brgy['entity_id']], $country['entity_id'], 1);
        $this->assertSame('rejected', $this->one($cycle)['status']);
        $this->assertNull(Location::find($country['entity_id'])->parent_id);
    }

    public function test_moving_a_location_rewrites_descendant_paths_and_replicates_them(): void
    {
        $a = $this->loc('A', 'country');
        $b = $this->loc('B', 'country');
        $r = $this->loc('Region', 'region', $a['entity_id']);
        $s = $this->loc('Site', 'site', $r['entity_id']);
        $this->push([$a, $b, $r, $s]);

        $move = $this->change('locations', 'update', ['parent_id' => $b['entity_id']], $r['entity_id'], 1);
        $this->assertSame('applied', $this->one($move)['status']);

        $site = Location::find($s['entity_id']);
        $this->assertSame("/{$b['entity_id']}/{$r['entity_id']}/{$s['entity_id']}/", $site->path);
        $this->assertSame(2, $site->depth);
        $this->assertSame(2, $site->version, 'the descendant is a real versioned change, so devices pull it');
        $this->assertTrue(SyncChange::where('entity_id', $s['entity_id'])->where('op', 'update')->exists());
    }

    // ---- authorization / envelope ------------------------------------------

    public function test_permissions_are_checked_per_change_on_the_server(): void
    {
        $viewer = $this->makeUser('viewer');
        $fieldWorker = $this->makeUser('field_worker');
        $staff = $this->makeUser('staff');

        $this->assertSame('forbidden', $this->one($this->loc(), $viewer)['code']);
        $this->assertSame('forbidden', $this->one($this->loc(), $fieldWorker)['code']);
        $this->assertSame('applied', $this->one($this->loc(), $staff)['status']);
        $this->assertSame(1, Location::count());
    }

    public function test_unknown_entities_and_server_owned_operations_are_refused(): void
    {
        $this->assertSame('unknown_entity', $this->one($this->change('beneficiaries', 'create', ['name' => 'x']))['code']);
        $this->assertSame('op_not_allowed', $this->one($this->change('users', 'create', ['name' => 'x', 'email' => 'x@x.test']))['code']);
        $this->assertSame('op_not_allowed', $this->one($this->change('roles', 'update', ['name' => 'x'], $this->admin->role_id, 1))['code']);
        $this->assertSame('op_not_allowed', $this->one($this->change('devices', 'delete', [], $this->device->id, 1))['code']);
        $this->assertSame('op_not_allowed', $this->one($this->change('audit_logs', 'delete', [], (string) Str::uuid7(), 1))['code']);
    }

    public function test_malformed_changes_are_rejected_not_fatal(): void
    {
        $results = $this->push([
            ['entity' => 'locations'],
            ['change_id' => 'nope', 'entity' => 'locations', 'entity_id' => 'x', 'op' => 'create'],
            $this->change('locations', 'update', ['name' => 'x'], (string) Str::uuid7()),   // no base_version
            $this->loc('OK'),
        ])->json('data.results');

        $this->assertSame(['rejected', 'rejected', 'rejected', 'applied'], array_column($results, 'status'));
        $this->assertSame('invalid_envelope', $results[0]['code']);
    }

    public function test_batch_size_is_capped(): void
    {
        config(['foundation.sync.max_push_batch' => 3]);
        $this->push([$this->loc('1'), $this->loc('2'), $this->loc('3'), $this->loc('4')])->assertStatus(422);
        $this->assertSame(0, Location::count());
    }

    // ---- updates, merges, conflicts ----------------------------------------

    public function test_update_with_current_base_version_applies_and_bumps_version(): void
    {
        $c = $this->loc('PH');
        $this->one($c);

        $r = $this->one($this->change('locations', 'update', ['name' => 'Philippines', 'code' => 'PH'], $c['entity_id'], 1));
        $this->assertSame(['applied', 2], [$r['status'], $r['version']]);
        $this->assertSame('Philippines', Location::find($c['entity_id'])->name);

        $noop = $this->one($this->change('locations', 'update', ['name' => 'Philippines'], $c['entity_id'], 2));
        $this->assertSame(['applied', 2, true], [$noop['status'], $noop['version'], $noop['noop']]);
    }

    public function test_updates_to_different_fields_from_two_devices_merge_automatically(): void
    {
        [$other, $otherToken] = $this->newDevice('Field Phone');
        $c = $this->loc('PH');
        $this->one($c);

        // Device B (field phone) renames it, having seen version 1.
        $this->one($this->change('locations', 'update', ['name' => 'Republic'], $c['entity_id'], 1), null, $otherToken);
        // Device A (office), also at version 1, sets the code: different field => merge.
        $r = $this->one($this->change('locations', 'update', ['code' => 'PH'], $c['entity_id'], 1));

        $this->assertSame('applied', $r['status']);
        $this->assertTrue($r['merged']);
        $row = Location::find($c['entity_id']);
        $this->assertSame(['Republic', 'PH', 3], [$row->name, $row->code, $row->version]);
        $this->assertSame(0, SyncConflict::count());
    }

    public function test_updates_to_the_same_field_become_a_conflict_that_preserves_both_sides(): void
    {
        [$other, $otherToken] = $this->newDevice('Field Phone');
        $c = $this->loc('PH');
        $this->one($c);
        $this->one($this->change('locations', 'update', ['name' => 'Server Name'], $c['entity_id'], 1), null, $otherToken);

        $mine = $this->change('locations', 'update', ['name' => 'My Offline Name'], $c['entity_id'], 1);
        $r = $this->one($mine);

        $this->assertSame(['conflict', 'field_overlap', false], [$r['status'], $r['code'], $r['resolved']]);
        $this->assertSame(['name'], $r['conflicting_fields']);
        $this->assertSame('Server Name', $r['server']['name'], 'response carries the server value');
        $this->assertSame('Server Name', Location::find($c['entity_id'])->name, 'server value is NOT overwritten');

        $conflict = SyncConflict::sole();
        $this->assertSame(['open', 1, 2], [$conflict->status, $conflict->base_version, $conflict->server_version]);
        $this->assertSame(['name' => 'My Offline Name'], $conflict->local_payload, 'the local change is preserved');
        $this->assertSame($this->device->id, $conflict->device_id);
        $this->assertFalse(SyncChange::where('change_id', $mine['change_id'])->exists(), 'a conflicted change never enters the feed');

        // Retrying the same change does not create a second conflict.
        $again = $this->one($mine);
        $this->assertSame(['conflict', true], [$again['status'], $again['duplicate']]);
        $this->assertSame(1, SyncConflict::count());
    }

    public function test_a_devices_own_earlier_queued_changes_never_conflict_with_each_other(): void
    {
        $c = $this->loc('PH');
        $this->one($c);

        // Two offline edits of the same field on one device, both based on version 1, sent in one batch.
        $results = $this->push([
            $this->change('locations', 'update', ['name' => 'First'], $c['entity_id'], 1),
            $this->change('locations', 'update', ['name' => 'Second'], $c['entity_id'], 1),
        ])->json('data.results');

        $this->assertSame(['applied', 'applied'], array_column($results, 'status'));
        $this->assertSame('Second', Location::find($c['entity_id'])->name);
        $this->assertSame(0, SyncConflict::count());
    }

    public function test_updating_a_record_deleted_elsewhere_is_a_conflict(): void
    {
        [$other, $otherToken] = $this->newDevice();
        $c = $this->loc('PH');
        $this->one($c);
        $this->one($this->change('locations', 'delete', [], $c['entity_id'], 1), null, $otherToken);

        $r = $this->one($this->change('locations', 'update', ['name' => 'Edited after delete'], $c['entity_id'], 1));
        $this->assertSame(['conflict', 'deleted_on_server'], [$r['status'], $r['code']]);
        $this->assertNotNull(Location::withTrashed()->find($c['entity_id'])->deleted_at);
    }

    public function test_deleting_a_record_edited_elsewhere_is_a_conflict(): void
    {
        [$other, $otherToken] = $this->newDevice();
        $c = $this->loc('PH');
        $this->one($c);
        $this->one($this->change('locations', 'update', ['name' => 'Edited'], $c['entity_id'], 1), null, $otherToken);

        $r = $this->one($this->change('locations', 'delete', [], $c['entity_id'], 1));
        $this->assertSame(['conflict', 'updated_on_server'], [$r['status'], $r['code']]);
        $this->assertNull(Location::find($c['entity_id'])->deleted_at);
    }

    public function test_delete_is_soft_versioned_idempotent_and_guarded(): void
    {
        $country = $this->loc('PH');
        $region = $this->loc('R', 'region', $country['entity_id']);
        $this->push([$country, $region]);

        $this->assertSame('has_children', $this->one($this->change('locations', 'delete', [], $country['entity_id'], 1))['code']);

        $del = $this->change('locations', 'delete', [], $region['entity_id'], 1);
        $r = $this->one($del);
        $this->assertSame(['applied', 2], [$r['status'], $r['version']]);
        $this->assertNotNull(Location::withTrashed()->find($region['entity_id'])->deleted_at);
        $this->assertSame('duplicate', $this->one($del)['status']);
        $this->assertTrue($this->one($this->change('locations', 'delete', [], $region['entity_id'], 1))['already_deleted']);

        $this->assertSame('applied', $this->one($this->change('locations', 'delete', [], $country['entity_id'], 1))['status']);
        $this->assertSame(['create', 'delete'], SyncChange::where('entity_id', $region['entity_id'])->orderBy('seq')->pluck('op')->all());
    }

    public function test_conflict_policy_server_wins_and_client_wins(): void
    {
        $registry = app(SyncRegistry::class);
        $base = $registry->get('locations');
        $with = fn (string $policy) => $registry->register(new EntityDefinition(
            name: $base->name, model: $base->model, ops: $base->ops, pullPermission: $base->pullPermission, writable: $base->writable,
            rules: $base->rules, conflictPolicy: $policy, guard: $base->guard, prepare: $base->prepare,
        ));

        [$other, $otherToken] = $this->newDevice();
        foreach ([EntityDefinition::POLICY_SERVER_WINS, EntityDefinition::POLICY_CLIENT_WINS] as $policy) {
            $with($policy);
            $c = $this->loc("PH-{$policy}");
            $this->one($c);
            $this->one($this->change('locations', 'update', ['name' => 'Theirs'], $c['entity_id'], 1), null, $otherToken);
            $r = $this->one($this->change('locations', 'update', ['name' => 'Mine'], $c['entity_id'], 1));

            if ($policy === EntityDefinition::POLICY_SERVER_WINS) {
                $this->assertSame(['conflict', true], [$r['status'], $r['resolved']]);
                $this->assertSame('Theirs', Location::find($c['entity_id'])->name);
                $this->assertSame('server_wins', SyncConflict::where('entity_id', $c['entity_id'])->value('resolution'));
            } else {
                $this->assertSame('applied', $r['status']);
                $this->assertSame(['name'], $r['overwrote']);
                $this->assertSame('Mine', Location::find($c['entity_id'])->name);
                // Even when the client wins, the overwrite is recorded - never silent.
                $rec = SyncConflict::where('entity_id', $c['entity_id'])->sole();
                $this->assertSame(['resolved', 'client_wins'], [$rec->status, $rec->resolution]);
            }
        }
    }

    // ---- other entities --------------------------------------------------------

    public function test_user_fields_can_sync_but_credentials_cannot(): void
    {
        $staff = $this->makeUser('staff');

        $ok = $this->one($this->change('users', 'update', ['name' => 'Renamed Staff', 'phone' => '0917'], $staff->id, 1));
        $this->assertSame('applied', $ok['status']);
        $this->assertSame('Renamed Staff', $staff->fresh()->name);

        foreach (['password', 'remember_token', 'must_change_password', 'last_login_at'] as $field) {
            $r = $this->one($this->change('users', 'update', [$field => 'x'], $staff->id, 2));
            $this->assertSame(['rejected', 'invalid_field'], [$r['status'], $r['code']], $field);
        }
        $this->assertSame('forbidden', $this->one($this->change('users', 'update', ['name' => 'x'], $staff->id, 2), $this->makeUser('staff'))['code']);
    }

    public function test_user_safety_rules_also_apply_through_sync(): void
    {
        $fa = $this->makeUser('foundation_admin');

        $r = $this->one($this->change('users', 'update', ['name' => 'Pwned'], $this->admin->id, 1), $fa);
        $this->assertSame(['rejected', 'forbidden'], [$r['status'], $r['code']]);

        $r = $this->one($this->change('users', 'update', ['status' => 'disabled'], $this->admin->id, 1));
        $this->assertSame('forbidden', $r['code'], 'cannot disable yourself');

        $r = $this->one($this->change('users', 'update', ['email' => strtoupper($fa->email)], $this->admin->id, 1));
        $this->assertSame('validation', $r['code'], 'duplicate email (case-insensitive) rejected');
    }

    public function test_settings_are_validated_against_the_catalog(): void
    {
        $tz = \App\Models\Setting::where('key', 'app.timezone')->sole();

        $this->assertSame('applied', $this->one($this->change('settings', 'update', ['value' => 'Asia/Dubai'], $tz->id, 1))['status']);
        $this->assertSame('Asia/Dubai', $tz->fresh()->value);

        $bad = $this->one($this->change('settings', 'update', ['value' => 'Mars/Olympus'], $tz->id, 2));
        $this->assertSame(['rejected', 'validation'], [$bad['status'], $bad['code']]);
        $this->assertSame('invalid_field', $this->one($this->change('settings', 'update', ['key' => 'evil'], $tz->id, 2))['code']);
        $this->assertSame('op_not_allowed', $this->one($this->change('settings', 'create', ['key' => 'x', 'group' => 'x', 'value' => 1]))['code']);
        $this->assertSame('forbidden', $this->one($this->change('settings', 'update', ['value' => 'UTC'], $tz->id, 2), $this->makeUser('staff'))['code']);
    }

    public function test_foundation_profile_can_be_edited_but_not_its_logo_path(): void
    {
        $f = \App\Models\Foundation::current();
        $this->assertSame('applied', $this->one($this->change('foundations', 'update', ['name' => 'New Name', 'website' => 'https://x.example'], $f->id, 1))['status']);
        $this->assertSame('invalid_field', $this->one($this->change('foundations', 'update', ['logo_path' => '../../etc/passwd'], $f->id, 2))['code']);
        $this->assertSame('validation', $this->one($this->change('foundations', 'update', ['website' => 'javascript:alert(1)'], $f->id, 2))['code']);
        $this->assertSame('New Name', $f->fresh()->name);
    }

    public function test_devices_can_append_audit_events_but_cannot_forge_who_did_them(): void
    {
        $staff = $this->makeUser('staff');
        $c = $this->change('audit_logs', 'create', [
            'occurred_at' => now()->subHour()->toIso8601String(), 'action' => 'auth.login', 'summary' => 'Signed in (offline)',
            'subject_type' => 'users', 'subject_id' => $staff->id,
        ]);
        $this->assertSame('applied', $this->one($c, $staff)['status']);

        $log = AuditLog::findOrFail($c['entity_id']);
        $this->assertSame([$staff->id, $staff->name, $this->device->id], [$log->user_id, $log->user_name, $log->device_id]);

        foreach (['user_id', 'user_name', 'device_id', 'ip_address'] as $field) {
            $forged = $this->change('audit_logs', 'create', ['occurred_at' => now()->toIso8601String(), 'action' => 'x.y', 'summary' => 's', $field => 'forged']);
            $this->assertSame('invalid_field', $this->one($forged, $staff)['code'], $field);
        }
        $this->assertSame('validation', $this->one($this->change('audit_logs', 'create', ['occurred_at' => now()->toIso8601String(), 'action' => 'Bad Action!', 'summary' => 's']))['code']);
        $this->assertSame('op_not_allowed', $this->one($this->change('audit_logs', 'update', ['summary' => 'tampered'], $c['entity_id'], 1))['code']);
    }

    public function test_each_push_is_itself_audited_with_counts(): void
    {
        $this->push([$this->loc('A'), $this->loc('B', 'bogus')]);

        $log = AuditLog::where('action', 'sync.push')->sole();
        $this->assertSame(1, $log->new_values['applied']);
        $this->assertSame(1, $log->new_values['rejected']);
        $this->assertSame($this->device->id, $log->device_id);
    }
}
