<?php

namespace Tests\Feature;

use App\Core\DashboardService;
use App\Models\AuditLog;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class DashboardAuditTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
    }

    public function test_dashboard_summary_has_system_sync_counts_and_module_placeholders(): void
    {
        $r = $this->asDevice($this->admin)->getJson('/api/dashboard/summary')->assertOk();

        $r->assertJsonPath('data.system.version', config('foundation.version'))
            ->assertJsonPath('data.system.deployment_model', 'central')
            ->assertJsonPath('data.system.storage_timezone', 'UTC')
            ->assertJsonPath('data.sync.devices.total', 1)
            ->assertJsonPath('data.sync.open_conflicts', 0)
            ->assertJsonPath('data.counts.users', 1)
            ->assertJsonPath('data.modules.aytam.available', false)
            ->assertJsonPath('data.modules.donations.available', false);
        $this->assertGreaterThan(0, $r->json('data.sync.latest_seq'));
    }

    public function test_counts_respect_permissions(): void
    {
        $counts = $this->asDevice($this->makeUser('viewer'))->getJson('/api/dashboard/summary')->assertOk()->json('data.counts');
        $this->assertArrayNotHasKey('users', $counts);
        $this->assertArrayNotHasKey('audit_events_today', $counts);
        $this->assertArrayHasKey('locations', $counts);
    }

    public function test_future_modules_plug_in_their_own_cards(): void
    {
        DashboardService::extend('aytam', fn ($user) => ['title' => 'Aytam', 'value' => 42]);
        $r = $this->asDevice($this->admin)->getJson('/api/dashboard/summary')->assertOk();
        $r->assertJsonPath('data.cards.aytam.value', 42)->assertJsonPath('data.modules.aytam.available', true);
    }

    public function test_audit_log_lists_filters_and_shows_actor_device_and_status(): void
    {
        $staff = $this->makeUser('staff');
        $this->asDevice($staff)->postJson('/api/sync/push', ['changes' => [$this->change('locations', 'create', ['name' => 'Cotabato', 'level' => 'municipality'])]])->assertOk();

        $all = $this->asDevice($this->admin)->getJson('/api/audit-logs')->assertOk();
        $row = collect($all->json('data'))->firstWhere('action', 'location.created');
        $this->assertSame([$staff->id, 'Main Office', 'synced'], [$row['user_id'], $row['device_name'], $row['sync_status']]);
        $this->assertSame('Created location "Cotabato"', $row['summary']);

        $this->assertNotEmpty($this->getJson('/api/audit-logs?action=location')->json('data'));
        $this->assertSame(['location.created'], array_unique(array_column($this->getJson('/api/audit-logs?action=location.created')->json('data'), 'action')));
        $this->assertSame([], $this->getJson('/api/audit-logs?action=beneficiary')->json('data'));
        $this->assertNotEmpty($this->getJson('/api/audit-logs?q=Cotabato')->json('data'));
        $this->assertNotEmpty($this->getJson('/api/audit-logs?device_id='.$this->device->id)->json('data'));
        $this->getJson('/api/audit-logs?per_page=1')->assertJsonCount(1, 'data');
        $this->getJson('/api/audit-logs?user_id=not-a-uuid')->assertStatus(422);
    }

    public function test_update_audit_records_old_and_new_values_only_for_changed_fields(): void
    {
        $c = $this->change('locations', 'create', ['name' => 'Old', 'level' => 'country']);
        $this->asDevice($this->admin)->postJson('/api/sync/push', ['changes' => [$c, $this->change('locations', 'update', ['name' => 'New'], $c['entity_id'], 1)]]);

        $log = AuditLog::where('action', 'location.updated')->sole();
        $this->assertSame(['name' => 'Old'], $log->old_values);
        $this->assertSame(['name' => 'New'], $log->new_values);
    }

    public function test_audit_entries_cannot_be_edited_or_removed_through_the_api_or_models(): void
    {
        $log = AuditLog::first();
        $this->asDevice($this->admin);
        $this->patchJson('/api/audit-logs/'.$log->id, ['summary' => 'x'])->assertStatus(404);
        $this->deleteJson('/api/audit-logs/'.$log->id)->assertStatus(404);

        $this->expectException(\LogicException::class);
        $log->delete();
    }

    public function test_system_health_requires_permission_and_reports_checks(): void
    {
        $this->asDevice($this->makeUser('viewer'))->getJson('/api/system/health')->assertStatus(403);
        $r = $this->asDevice($this->admin)->getJson('/api/system/health');
        $this->assertContains($r->status(), [200, 503]);
        $this->assertTrue($r->json('data.checks.database.ok'));
        $this->assertArrayHasKey('migrations', $r->json('data.checks'));
    }

    public function test_public_status_exposes_only_branding(): void
    {
        $r = $this->getJson('/api/system/status')->assertOk();
        $this->assertEqualsCanonicalizing(['installed', 'version', 'name', 'short_name', 'logo_hash', 'locale'], array_keys($r->json('data')));
        $r->assertJsonPath('data.name', 'Test Foundation');
    }

    public function test_settings_listing_comes_from_the_catalog(): void
    {
        $r = $this->asDevice($this->admin)->getJson('/api/settings')->assertOk();
        $keys = array_column($r->json('data'), 'key');
        $this->assertContains('app.timezone', $keys);
        $this->assertContains('deployment.model', $keys);
    }
}
