<?php

namespace App\Http\Controllers\Api;

use App\Core\Audit\Auditor;
use App\Core\Devices\DeviceService;
use App\Http\Controllers\Controller;
use App\Models\Device;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DeviceController extends Controller
{
    public function __construct(private DeviceService $devices, private Auditor $auditor) {}

    public function index(): JsonResponse
    {
        return ApiResponse::ok(Device::query()->orderByDesc('is_primary')->orderBy('name')->get()->map(fn (Device $d) => $this->present($d)));
    }

    /** The device making this request (identified by its token). */
    public function current(Request $request): JsonResponse
    {
        return ApiResponse::ok($this->present($request->attributes->get('device')));
    }

    /** Register a new device. The token is returned exactly once. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(config('foundation.device.types'))],
        ]);
        [$device, $token] = $this->devices->register($data['name'], $data['type'], $request->user()->getKey());

        return ApiResponse::created($this->present($device) + ['token' => $token]);
    }

    /** Claim the unclaimed device the installer created, from the browser that should become it. */
    public function claim(Device $device): JsonResponse
    {
        if ($device->isClaimed() || $device->isRevoked()) {
            return ApiResponse::error('DEVICE_ALREADY_CLAIMED', 'This device has already been claimed.', 409);
        }
        $token = $this->devices->issueToken($device);
        $this->auditor->record('device.claimed', "Claimed device \"{$device->name}\"", 'devices', $device->getKey());

        return ApiResponse::ok($this->present($device->fresh()) + ['token' => $token]);
    }

    /** Rename only: the device_code never changes. */
    public function update(Request $request, Device $device): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $device->update($data);

        return ApiResponse::ok($this->present($device->fresh()));
    }

    public function rotateToken(Device $device): JsonResponse
    {
        if ($device->isRevoked()) {
            return ApiResponse::error('DEVICE_REVOKED', 'Revoked devices cannot be given a new token.', 409);
        }
        $token = $this->devices->issueToken($device);
        $this->auditor->record('device.token_rotated', "Rotated token for \"{$device->name}\"", 'devices', $device->getKey());

        return ApiResponse::ok(['token' => $token]);
    }

    public function revoke(Device $device): JsonResponse
    {
        if ($device->is_primary) {
            return ApiResponse::error('FORBIDDEN', 'The primary installation device cannot be revoked.', 409);
        }
        $this->devices->revoke($device);
        $this->auditor->record('device.revoked', "Revoked device \"{$device->name}\"", 'devices', $device->getKey());

        return ApiResponse::ok($this->present($device->fresh()));
    }

    private function present(Device $d): array
    {
        return [
            'id' => $d->getKey(), 'device_code' => $d->device_code, 'name' => $d->name, 'type' => $d->type,
            'is_primary' => $d->is_primary, 'claimed' => $d->isClaimed(), 'revoked' => $d->isRevoked(),
            'online' => $d->isOnline(), 'last_seen_at' => $d->last_seen_at?->toIso8601String(),
            'last_pull_seq' => $d->last_pull_seq, 'app_version' => $d->app_version,
        ];
    }
}
