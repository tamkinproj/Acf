<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/** Restricts every query on a tenant-owned model to the current tenant. Fails closed. */
final class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $ctx = app(TenantContext::class);
        $column = $model->getTable().'.'.$model->tenantColumn();

        match ($ctx->mode()) {
            TenantContext::TENANT => $builder->where($column, $ctx->id()),
            TenantContext::PLATFORM => $model->platformSeesAllTenants() ? null : $builder->whereNull($column),
            TenantContext::SYSTEM => null,
            default => $builder->whereRaw('1 = 0'),
        };
    }
}
