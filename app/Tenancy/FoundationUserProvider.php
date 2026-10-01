<?php

namespace App\Tenancy;

use Illuminate\Auth\EloquentUserProvider;

/**
 * Sessions are resolved BEFORE the tenant is known (the user decides the tenant), so looking the user up must not
 * itself be tenant-scoped. The tenancy middleware sets the context from the user immediately afterwards.
 */
final class FoundationUserProvider extends EloquentUserProvider
{
    protected function newModelQuery($model = null)
    {
        return parent::newModelQuery($model)->withoutGlobalScope(TenantScope::class);
    }
}
