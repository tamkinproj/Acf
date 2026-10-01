<?php

namespace App\Sync;

use App\Models\Device;

/**
 * "Who and where" for the current unit of work. Every row written through a
 * Syncable model is stamped with these, and every audit entry records them.
 *
 * Device: the authenticated device when a request carries a device token;
 * otherwise the installation's own primary device (a write made directly on
 * this server is, truthfully, made by this server's device).
 * User: the authenticated user unless explicitly overridden (e.g. CLI jobs).
 */
class DeviceContext
{
    private ?string $deviceId = null;
    private ?string $userOverride = null;
    private bool $userOverridden = false;
    private ?string $ip = null;
    private ?string $primaryId = null;
    private bool $primaryLoaded = false;

    public function setDevice(?Device $device): void
    {
        $this->deviceId = $device?->getKey();
    }

    public function setIp(?string $ip): void
    {
        $this->ip = $ip;
    }

    public function actingAs(?string $userId): void
    {
        $this->userOverride = $userId;
        $this->userOverridden = true;
    }

    public function reset(): void
    {
        $this->deviceId = $this->userOverride = $this->ip = null;
        $this->userOverridden = false;
        $this->forgetPrimary();
    }

    public function forgetPrimary(): void
    {
        $this->primaryId = null;
        $this->primaryLoaded = false;
    }

    public function deviceId(): ?string
    {
        return $this->deviceId ?? $this->primaryDeviceId();
    }

    public function userId(): ?string
    {
        if ($this->userOverridden) {
            return $this->userOverride;
        }
        $id = auth()->id();

        return $id === null ? null : (string) $id;
    }

    public function userName(): ?string
    {
        return auth()->user()?->name;
    }

    public function ip(): ?string
    {
        return $this->ip ?? (app()->runningInConsole() ? null : request()->ip());
    }

    private function primaryDeviceId(): ?string
    {
        if (! $this->primaryLoaded) {
            try {
                $this->primaryId = Device::query()->where('is_primary', true)->value('id');
            } catch (\Throwable) {
                $this->primaryId = null;
            }
            $this->primaryLoaded = $this->primaryId !== null;
        }

        return $this->primaryId;
    }
}
