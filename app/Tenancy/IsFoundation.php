<?php

namespace App\Tenancy;

use LogicException;

/** The tenant itself: scoped by its own id. Platform administrators may list every foundation; tenants see only theirs. */
trait IsFoundation
{
    public static function bootIsFoundation(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function () {
            if (app(TenantContext::class)->isTenant()) {
                throw new LogicException('A foundation cannot create another foundation.');
            }
        });
    }

    public function tenantColumn(): string
    {
        return 'id';
    }

    public function tenantOwnerId(): ?string
    {
        return $this->getKey();
    }

    public function platformSeesAllTenants(): bool
    {
        return true;
    }
}
