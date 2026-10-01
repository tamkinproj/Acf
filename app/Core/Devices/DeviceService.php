<?php

namespace App\Core\Devices;

use App\Models\Device;
use Illuminate\Support\Str;

/**
 * Device identity. `device_code` (FOUNDATION-DEVICE-XXXXXXXX) is generated once and never
 * changes; the friendly name is editable. The secret token is shown once and only its
 * sha256 is stored, so a database leak does not yield working device credentials.
 */
class DeviceService
{
    // No 0/O/1/I: codes get read aloud and typed by humans.
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** @return array{0:Device,1:string} device and its plaintext token (returned once) */
    public function register(string $name, string $type, ?string $registeredBy = null, bool $primary = false, bool $withToken = true): array
    {
        $token = $withToken ? $this->newToken() : null;
        $device = new Device;
        $device->forceFill([
            'device_code' => $this->newCode(),
            'name' => $name,
            'type' => $type,
            'token_hash' => $token ? hash('sha256', $token) : null,
            'is_primary' => $primary,
            'registered_by' => $registeredBy,
        ])->save();

        return [$device, (string) $token];
    }

    /** Issue a fresh token (first claim of an installer-created device, or rotation after loss). */
    public function issueToken(Device $device): string
    {
        $token = $this->newToken();
        $device->forceFill(['token_hash' => hash('sha256', $token), 'revoked_at' => null])->save();

        return $token;
    }

    public function revoke(Device $device): void
    {
        $device->forceFill(['revoked_at' => now(), 'token_hash' => null])->save();
    }

    public function newToken(): string
    {
        return config('foundation.device.token_prefix').Str::random(48);
    }

    public function newCode(): string
    {
        do {
            $suffix = '';
            for ($i = 0; $i < 8; $i++) {
                $suffix .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $code = config('foundation.device.code_prefix').$suffix;
        } while (Device::withTrashed()->where('device_code', $code)->exists());

        return $code;
    }
}
