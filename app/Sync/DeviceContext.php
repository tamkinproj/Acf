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
    /** @var array<string,?string> primary device id per tenant context key */
    private array $primary = [];

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
        $this->primary = [];
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

    /** The foundation's own server device (a write made on the server is, truthfully, made by that device). */
    private function primaryDeviceId(): ?string
    {
        $tenant = app(\App\Tenancy\TenantContext::class);
        if (! $tenant->isTenant()) {
            return null;
        }
        $key = $tenant->key();
        if (! array_key_exists($key, $this->primary) || $this->primary[$key] === null) {
            try {
                $this->primary[$key] = Device::query()->where('is_primary', true)->value('id');
            } catch (\Throwable) {
                $this->primary[$key] = null;
            }
        }

        return $this->primary[$key];
    }
}
