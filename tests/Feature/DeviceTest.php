<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Device;
use Tests\Concerns\BootsFoundation;
use Tests\TestCase;

class DeviceTest extends TestCase
{
    use BootsFoundation;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bootFoundation();
    }

    public function test_registering_a_device_returns_the_token_once_and_stores_only_its_hash(): void
    {
        $res = $this->actingAs($this->admin)->postJson('/api/devices', ['name' => 'Field Phone A', 'type' => 'field'])->assertCreated();

        $token = $res->json('data.token');
        $code = $res->json('data.device_code');
        $this->assertStringStartsWith('fdt_', $token);
        $this->assertMatchesRegularExpression('/^FOUNDATION-DEVICE-[A-Z2-9]{8}$/', $code);

        $device = Device::where('device_code', $code)->sole();
        $this->assertSame(hash('sha256', $token), $device->token_hash);
        $this->assertNotSame($token, $device->token_hash);
        $this->getJson('/api/devices')->assertOk()->assertDontSee($token)->assertDontSee($device->token_hash);
        $this->assertStringNotContainsString($token, json_encode(\App\Models\SyncChange::all()));
    }

    public function test_device_codes_are_unique_and_stable_across_rename(): void
    {
        $this->actingAs($this->admin);
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = $this->postJson('/api/devices', ['name' => "D{$i}", 'type' => 'office'])->json('data.device_code');
        }
        $this->assertCount(10, array_unique($codes));

        $id = Device::where('device_code', $codes[0])->value('id');
        $this->patchJson("/api/devices/{$id}", ['name' => 'Renamed', 'device_code' => 'FOUNDATION-DEVICE-HACKED'])->assertOk()->assertJsonPath('data.name', 'Renamed');
        $this->assertSame($codes[0], Device::find($id)->device_code);
    }

    public function test_installer_device_is_claimed_once_by_an_authorised_user(): void
    {
        $unclaimed = Device::where('is_primary', true)->sole();
        $unclaimed->forceFill(['token_hash' => null])->save();

        $this->actingAs($this->makeUser('staff'))->postJson("/api/devices/{$unclaimed->id}/claim")->assertStatus(403);

        $token = $this->actingAs($this->admin)->postJson("/api/devices/{$unclaimed->id}/claim")->assertOk()->json('data.token');
        $this->assertSame(hash('sha256', $token), $unclaimed->fresh()->token_hash);
        $this->postJson("/api/devices/{$unclaimed->id}/claim")->assertStatus(409);
    }

    public function test_sync_requires_a_valid_device_token(): void
    {
        $this->actingAs($this->admin);
        $this->getJson('/api/sync/status')->assertStatus(401)->assertJsonPath('code', 'DEVICE_REQUIRED');
        $this->withToken('fdt_notarealtoken')->getJson('/api/sync/status')->assertStatus(401)->assertJsonPath('code', 'DEVICE_INVALID');
        $this->withToken('plainsecret')->getJson('/api/sync/status')->assertStatus(401)->assertJsonPath('code', 'DEVICE_INVALID');
        $this->withToken($this->deviceToken)->getJson('/api/sync/status')->assertOk()->assertJsonPath('data.device.id', $this->device->id);
    }

    public function test_a_device_token_alone_is_not_enough_without_a_user(): void
    {
        $this->withToken($this->deviceToken)->getJson('/api/sync/status')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
    }

    public function test_revoked_device_is_locked_out_and_primary_cannot_be_revoked(): void
    {
        [$device, $token] = $this->newDevice();
        $this->actingAs($this->admin)->withToken($token)->getJson('/api/sync/status')->assertOk();

        $this->withoutHeader('Authorization')->postJson("/api/devices/{$device->id}/revoke")->assertOk()->assertJsonPath('data.revoked', true);
        $this->withToken($token)->getJson('/api/sync/status')->assertStatus(401)->assertJsonPath('code', 'DEVICE_INVALID');
        $this->assertTrue(AuditLog::where('action', 'device.revoked')->exists());

        $this->withoutHeader('Authorization')->postJson("/api/devices/{$this->device->id}/revoke")->assertStatus(409);
    }

    public function test_rotating_a_token_invalidates_the_old_one(): void
    {
        [$device, $old] = $this->newDevice();
        $new = $this->actingAs($this->admin)->postJson("/api/devices/{$device->id}/rotate-token")->assertOk()->json('data.token');

        $this->withToken($old)->getJson('/api/sync/status')->assertStatus(401);
        $this->withToken($new)->getJson('/api/sync/status')->assertOk();
    }

    public function test_writes_are_stamped_with_the_devices_identity(): void
    {
        [$device, $token] = $this->newDevice('Field Phone');
        $this->actingAs($this->admin)->withToken($token)->patchJson('/api/auth/profile', ['name' => 'Renamed Admin'])->assertOk();

        $this->assertSame($device->id, $this->admin->fresh()->origin_device_id);
        $this->assertSame($device->id, AuditLog::where('action', 'user.updated')->latest('occurred_at')->first()->device_id);
    }

    public function test_writes_without_a_device_token_are_attributed_to_the_installation_device(): void
    {
        $this->actingAs($this->admin)->patchJson('/api/auth/profile', ['name' => 'Another Name'])->assertOk();
        $this->assertSame($this->device->id, $this->admin->fresh()->origin_device_id);
    }
}
