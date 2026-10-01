<?php

namespace App\Tenancy;

use LogicException;

/**
 * Who the current unit of work acts for. Every tenant-owned model reads this, so isolation holds
 * no matter which controller, job, or command touches the data.
 *
 *  tenant      a foundation's own users and staff: only that foundation's rows are visible
 *  platform    platform administrators: only platform-owned rows (foundation_id NULL) are visible;
 *              foundation business data is invisible by construction
 *  system      installer, upgrade and provisioning code that is explicitly allowed to cross tenants
 *  unresolved  nobody told us yet: reads return nothing and writes are refused (fail closed)
 */
final class TenantContext
{
    public const TENANT = 'tenant';
    public const PLATFORM = 'platform';
    public const SYSTEM = 'system';
    public const UNRESOLVED = 'unresolved';

    private string $mode = self::UNRESOLVED;
    private ?string $id = null;

    public function mode(): string
    {
        return $this->mode;
    }

    public function id(): ?string
    {
        return $this->mode === self::TENANT ? $this->id : null;
    }

    public function isTenant(): bool
    {
        return $this->mode === self::TENANT;
    }

    public function isPlatform(): bool
    {
        return $this->mode === self::PLATFORM;
    }

    /** Stable key for per-tenant caches. */
    public function key(): string
    {
        return $this->mode === self::TENANT ? 'tenant:'.$this->id : $this->mode;
    }

    public function requireId(): string
    {
        return $this->id() ?? throw new LogicException('This operation needs a foundation context.');
    }

    public function setTenant(string $foundationId): void
    {
        $this->mode = self::TENANT;
        $this->id = $foundationId;
    }

    public function setPlatform(): void
    {
        $this->mode = self::PLATFORM;
        $this->id = null;
    }

    public function setSystem(): void
    {
        $this->mode = self::SYSTEM;
        $this->id = null;
    }

    public function reset(): void
    {
        $this->mode = self::UNRESOLVED;
        $this->id = null;
    }

    /**
     * @template T
     * @param  callable():T  $fn
     * @return T
     */
    public function runAs(string $foundationId, callable $fn): mixed
    {
        return $this->swap(fn () => $this->setTenant($foundationId), $fn);
    }

    /**
     * @template T
     * @param  callable():T  $fn
     * @return T
     */
    public function asSystem(callable $fn): mixed
    {
        return $this->swap(fn () => $this->setSystem(), $fn);
    }

    /**
     * @template T
     * @param  callable():T  $fn
     * @return T
     */
    public function asPlatform(callable $fn): mixed
    {
        return $this->swap(fn () => $this->setPlatform(), $fn);
    }

    private function swap(callable $enter, callable $fn): mixed
    {
        [$mode, $id] = [$this->mode, $this->id];
        $enter();
        try {
            return $fn();
        } finally {
            [$this->mode, $this->id] = [$mode, $id];
        }
    }
}
