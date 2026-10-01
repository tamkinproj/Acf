<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Foundation;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class PlatformTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
    }

    private function newFoundationPayload(array $over = []): array
    {
        return $over + [
            'name' => 'Al-Noor Foundation', 'short_name' => 'Al-Noor', 'country' => 'Philippines', 'email' => 'info@alnoor.example',
            'admin' => ['name' => 'Aisha Santos', 'email' => 'Aisha@AlNoor.example'],
        ];
    }

    public function test_platform_admin_creates_a_working_foundation_with_its_first_administrator(): void
    {
        $r = $this->actingAs($this->platformAdmin)->postJson('/api/platform/foundations', $this->newFoundationPayload())->assertCreated();
        $temp = $r->json('data.administrator.temporary_password');
        $this->assertSame(14, strlen($temp));
        $r->assertJsonPath('data.slug', 'al-noor')->assertJsonPath('data.status', 'active')->assertJsonPath('data.administrator.email', 'aisha@alnoor.example');

        $id = $r->json('data.id');
        $tenant = app(TenantContext::class);
        $tenant->runAs($id, function () {
            $this->assertEqualsCanonicalizing(['foundation_admin', 'staff', 'field_worker', 'volunteer', 'viewer', 'aytam_mushrif', 'aytam_field_worker'], Role::pluck('key')->all());
            $this->assertSame(1, Device::where('is_primary', true)->count());
            $this->assertGreaterThan(3, Setting::count());
            $this->assertSame('Al-Noor', Setting::where('key', 'app.name')->value('value'));
        });

        // The administrator signs in with the temporary password and must change it before anything else.
        auth()->logout();
        $this->postJson('/api/auth/login', ['email' => 'aisha@alnoor.example', 'password' => $temp])->assertOk()
            ->assertJsonPath('data.kind', 'foundation')->assertJsonPath('data.user.must_change_password', true)
            ->assertJsonPath('data.foundation.name', 'Al-Noor Foundation');
        $this->getJson('/api/users')->assertStatus(403)->assertJsonPath('code', 'PASSWORD_CHANGE_REQUIRED');
        $this->putJson('/api/auth/password', ['current_password' => $temp, 'password' => 'Brand-new-pass-1', 'password_confirmation' => 'Brand-new-pass-1'])->assertOk();
        $this->getJson('/api/users')->assertOk()->assertJsonCount(1, 'data');

        // Audited at platform level (creation) and inside the new foundation (provisioning); the password never appears.
        $platformLog = $tenant->asPlatform(fn () => AuditLog::where('action', 'foundation.created')->first());
        $this->assertNotNull($platformLog);
        $this->assertStringNotContainsString($temp, json_encode($tenant->asSystem(fn () => AuditLog::all())));
        $this->assertTrue($tenant->runAs($id, fn () => AuditLog::where('action', 'foundation.provisioned')->exists()));
    }

    public function test_foundation_creation_validates_and_rejects_duplicate_emails_across_the_platform(): void
    {
        $this->actingAs($this->platformAdmin);
        $this->postJson('/api/platform/foundations', ['name' => 'X'])->assertStatus(422)->assertJsonValidationErrors(['admin']);
        $this->postJson('/api/platform/foundations', $this->newFoundationPayload(['admin' => ['name' => 'Dup', 'email' => 'admin@example.test']]))
            ->assertStatus(422)->assertJsonValidationErrors(['admin.email']);

        $this->postJson('/api/platform/foundations', $this->newFoundationPayload())->assertCreated();
        $second = $this->postJson('/api/platform/foundations', $this->newFoundationPayload(['admin' => ['name' => 'B', 'email' => 'b@alnoor.example']]))->assertCreated();
        $this->assertSame('al-noor-2', $second->json('data.slug'), 'slugs stay unique');
    }

    public function test_suspending_a_foundation_locks_its_people_out_immediately_and_reactivating_restores_access(): void
    {
        $staff = $this->makeUser('staff', 'staff@example.test');
        $this->actingAs($staff)->getJson('/api/auth/me')->assertOk();

        $this->actingAs($this->platformAdmin)->postJson("/api/platform/foundations/{$this->foundation->id}/status", ['status' => 'suspended', 'reason' => 'Non-payment'])
            ->assertOk()->assertJsonPath('data.status', 'suspended')->assertJsonPath('data.status_reason', 'Non-payment');

        // An already signed-in user is stopped on their very next request, and cannot sign in again.
        $this->actingAs($staff)->getJson('/api/auth/me')->assertStatus(403)->assertJsonPath('code', 'FOUNDATION_SUSPENDED');
        $this->postJson('/api/auth/login', ['email' => 'staff@example.test', 'password' => 'Correct-horse-9'])->assertStatus(403)->assertJsonPath('code', 'FOUNDATION_SUSPENDED');
        $this->assertGuest();

        // Other foundations are unaffected.
        [$b, $bAdmin] = $this->createFoundation('Foundation B', 'b-admin@example.test');
        $this->actingAs($bAdmin)->getJson('/api/auth/me')->assertOk();

        foreach (['inactive', 'active'] as $status) {
            $this->actingAs($this->platformAdmin)->postJson("/api/platform/foundations/{$this->foundation->id}/status", ['status' => $status])->assertOk();
        }
        $this->postJson('/api/auth/login', ['email' => 'staff@example.test', 'password' => 'Correct-horse-9'])->assertOk();

        $actions = app(TenantContext::class)->asPlatform(fn () => AuditLog::where('subject_id', $this->foundation->id)->pluck('action')->all());
        $this->assertContains('foundation.suspended', $actions);
        $this->assertContains('foundation.inactive', $actions);
        $this->assertContains('foundation.active', $actions);
        $this->assertSame('active', app(TenantContext::class)->asSystem(fn () => Foundation::find($this->foundation->id))->status);
    }

    public function test_the_platform_dashboard_shows_counts_only(): void
    {
        $this->createFoundation('Foundation B', 'b-admin@example.test');
        $this->makeUser('staff', 'secret-person@example.test');
        app(TenantContext::class)->runAs($this->foundation->id, fn () => \App\Models\Location::create(['level' => 'country', 'name' => 'Private Place', 'path' => '/p/', 'depth' => 0]));

        $r = $this->actingAs($this->platformAdmin)->getJson('/api/platform/dashboard')->assertOk();
        $r->assertJsonPath('data.foundations.total', 2)->assertJsonPath('data.foundations.active', 2)
            ->assertJsonPath('data.totals.users', 3)->assertJsonPath('data.totals.platform_admins', 1)
            ->assertJsonPath('data.system.status', 'ok');
        $body = $r->getContent();
        foreach (['secret-person', 'Private Place', 'admin@example.test'] as $private) {
            $this->assertStringNotContainsString($private, $body, 'the platform dashboard must not leak foundation data');
        }
        $this->assertArrayNotHasKey('users', $r->json('data.foundations'));
    }

    public function test_foundation_list_edit_and_detail_expose_no_records(): void
    {
        $this->actingAs($this->platformAdmin);
        $list = $this->getJson('/api/platform/foundations?q=test')->assertOk();
        $this->assertSame('Test Foundation', $list->json('data.0.name'));
        $this->assertSame(1, $list->json('data.0.users_count'));

        $this->patchJson("/api/platform/foundations/{$this->foundation->id}", ['name' => 'Renamed Foundation', 'country' => 'Philippines'])->assertOk()->assertJsonPath('data.name', 'Renamed Foundation');
        $this->patchJson("/api/platform/foundations/{$this->foundation->id}", ['timezone' => 'UTC'])->assertStatus(422);
        $this->patchJson("/api/platform/foundations/{$this->foundation->id}", ['status' => 'suspended'])->assertOk()->assertJsonPath('data.status', 'active');

        $detail = $this->getJson("/api/platform/foundations/{$this->foundation->id}")->assertOk();
        $this->assertSame('admin@example.test', $detail->json('data.administrators.0.email'));
        $this->assertSame(['users', 'programs', 'active_programs', 'organizations', 'devices', 'storage_bytes'], array_keys($detail->json('data.usage')));
        $this->getJson('/api/platform/foundations/'.\Illuminate\Support\Str::uuid())->assertStatus(404);
    }

    public function test_platform_can_add_an_administrator_and_reset_a_password(): void
    {
        $this->actingAs($this->platformAdmin);
        $added = $this->postJson("/api/platform/foundations/{$this->foundation->id}/administrators", ['name' => 'Second Admin', 'email' => 'second@example.test'])->assertCreated();
        $second = app(TenantContext::class)->asSystem(fn () => User::where('email', 'second@example.test')->first());
        $this->assertSame($this->foundation->id, $second->foundation_id);
        $this->assertTrue(Hash::check($added->json('data.temporary_password'), $second->password));

        $reset = $this->postJson("/api/platform/foundations/{$this->foundation->id}/administrators/{$this->admin->id}/reset-password")->assertOk();
        $this->assertTrue(Hash::check($reset->json('data.temporary_password'), app(TenantContext::class)->asSystem(fn () => User::find($this->admin->id))->password));

        // Only administrators can be reset this way - not arbitrary foundation staff.
        $staff = $this->makeUser('staff');
        $this->postJson("/api/platform/foundations/{$this->foundation->id}/administrators/{$staff->id}/reset-password")->assertStatus(404);
    }

    public function test_platform_users_are_managed_with_safety_rules(): void
    {
        $this->actingAs($this->platformAdmin);
        $created = $this->postJson('/api/platform/users', ['name' => 'Second Platform', 'email' => 'p2@example.test'])->assertCreated();
        $this->assertNotEmpty($created->json('data.temporary_password'));
        $this->getJson('/api/platform/users')->assertOk()->assertJsonCount(2, 'data');

        $this->patchJson('/api/platform/users/'.$this->platformAdmin->id, ['status' => 'disabled'])->assertStatus(403);
        $this->deleteJson('/api/platform/users/'.$this->platformAdmin->id)->assertStatus(403);

        // A foundation's own users are not addressable from here.
        $this->patchJson('/api/platform/users/'.$this->admin->id, ['name' => 'x'])->assertStatus(404);

        $p2 = app(TenantContext::class)->asSystem(fn () => User::where('email', 'p2@example.test')->first());
        $p2->forceFill(['must_change_password' => false])->save();
        $this->actingAs($p2)->patchJson('/api/platform/users/'.$this->platformAdmin->id, ['status' => 'disabled'])->assertOk();
        $this->actingAs($p2)->deleteJson('/api/platform/users/'.$p2->id)->assertStatus(403);   // self
    }

    public function test_platform_settings_and_activity(): void
    {
        $this->actingAs($this->platformAdmin);
        $keys = array_column($this->getJson('/api/platform/settings')->assertOk()->json('data'), 'key');
        $this->assertEqualsCanonicalizing(['app.name', 'app.timezone', 'app.locale', 'security.idle_lock_minutes'], $keys);

        $this->putJson('/api/platform/settings', ['settings' => ['app.name' => 'Hope Platform', 'security.idle_lock_minutes' => 30]])->assertOk();
        $this->putJson('/api/platform/settings', ['settings' => ['app.currency' => 'SAR']])->assertStatus(422);
        $this->putJson('/api/platform/settings', ['settings' => ['security.idle_lock_minutes' => 9999]])->assertStatus(422);
        $this->getJson('/api/system/status')->assertJsonPath('data.name', 'Hope Platform');

        $this->postJson('/api/platform/foundations', $this->newFoundationPayload())->assertCreated();
        $activity = $this->getJson('/api/platform/activity?action=foundation')->assertOk()->json('data');
        $this->assertSame(['foundation.created'], array_unique(array_column($activity, 'action')));
        $this->assertSame('Created foundation "Al-Noor Foundation"', $activity[0]['summary']);

        // Foundation-level events never show up in platform activity.
        $this->assertStringNotContainsString('provisioned', json_encode($this->getJson('/api/platform/activity?per_page=100')->json('data')));
    }

    public function test_commands_create_a_platform_admin_and_a_foundation(): void
    {
        $this->artisan('platform:admin', ['email' => 'ops@example.test', 'name' => 'Ops', '--password' => 'Ops-secret-pass-1'])->assertSuccessful();
        $ops = app(TenantContext::class)->asSystem(fn () => User::where('email', 'ops@example.test')->first());
        $this->assertNull($ops->foundation_id);

        $this->artisan('platform:admin', ['email' => 'ops@example.test', '--password' => 'Another-pass-22'])->assertSuccessful();   // resets
        $this->assertTrue(Hash::check('Another-pass-22', app(TenantContext::class)->asSystem(fn () => User::find($ops->id))->password));
        $this->artisan('platform:admin', ['email' => 'admin@example.test', '--password' => 'Another-pass-22'])->assertFailed();     // a foundation user

        $this->artisan('platform:foundation', ['name' => 'CLI Foundation', '--admin-name' => 'C', '--admin-email' => 'cli@example.test'])->assertSuccessful();
        $this->assertTrue(app(TenantContext::class)->asSystem(fn () => Foundation::where('name', 'CLI Foundation')->exists()));
    }
}
