<?php

namespace Tests\Unit;

use App\Models\AuditLog;
use App\Models\Location;
use App\Models\SyncChange;
use Tests\TestCase;

class SyncModelTest extends TestCase
{
    public function test_create_update_delete_version_feed_and_audit(): void
    {
        $loc = Location::create(['level' => 'country', 'name' => 'Philippines', 'path' => '/x/', 'depth' => 0]);

        $this->assertTrue(\Illuminate\Support\Str::isUuid($loc->id));
        $this->assertSame(1, $loc->version);

        $loc->update(['name' => 'PH']);
        $this->assertSame(2, $loc->fresh()->version);

        $loc->delete();
        $fresh = Location::withTrashed()->find($loc->id);
        $this->assertSame(3, $fresh->version);
        $this->assertNotNull($fresh->deleted_at);

        $feed = SyncChange::where('entity', 'locations')->orderBy('seq')->get();
        $this->assertSame(['create', 'update', 'delete'], $feed->pluck('op')->all());
        $this->assertSame(['name'], $feed[1]->fields);
        $this->assertSame([1, 2, 3], $feed->pluck('version')->all());

        $actions = AuditLog::query()->where('subject_type', 'locations')->orderBy('occurred_at')->pluck('action')->all();
        $this->assertSame(['location.created', 'location.updated', 'location.deleted'], $actions);
    }

    public function test_server_local_column_changes_do_not_bump_version(): void
    {
        $role = \App\Models\Role::create(['key' => 'r', 'name' => 'R', 'permissions' => []]);
        $u = \App\Models\User::create(['name' => 'A', 'email' => 'a@x.test', 'password' => 'secret-pass-1', 'role_id' => $role->id]);
        $u->forceFill(['last_login_at' => now()])->save();

        $this->assertSame(1, $u->fresh()->version);
        $this->assertSame(1, SyncChange::where('entity', 'users')->count());
        $this->assertArrayNotHasKey('password', SyncChange::where('entity', 'users')->first()->payload);
    }

    public function test_hard_delete_and_audit_edits_are_blocked(): void
    {
        $loc = Location::create(['level' => 'country', 'name' => 'A', 'path' => '/a/', 'depth' => 0]);
        $this->expectException(\LogicException::class);
        $loc->forceDelete();
    }

    public function test_audit_log_is_immutable(): void
    {
        $log = app(\App\Core\Audit\Auditor::class)->record('test.event', 'Something');
        $this->expectException(\LogicException::class);
        $log->update(['summary' => 'tampered']);
    }
}
