<?php

namespace App\Tenancy;

use App\Models\Foundation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Marks a model as owned by one foundation. Reads are scoped to the current tenant, new rows are stamped with it,
 * and the owner can never be changed afterwards. A null owner means "platform-owned" (platform admins, platform roles).
 */
trait BelongsToFoundation
{
    public static function bootBelongsToFoundation(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            $ctx = app(TenantContext::class);
            $column = $model->tenantColumn();

            if ($ctx->isTenant()) {
                if ($model->{$column} === null) {
                    $model->{$column} = $ctx->id();
                } elseif ($model->{$column} !== $ctx->id()) {
                    throw new LogicException('A record cannot be created for another foundation.');
                }
            } elseif ($ctx->mode() === TenantContext::UNRESOLVED) {
                throw new LogicException('No foundation context: refusing to write '.$model::class.'.');
            }
        });

        static::updating(function ($model) {
            $column = $model->tenantColumn();
            if ($model->isDirty($column) && $model->getOriginal($column) !== null) {
                throw new LogicException('A record cannot move to another foundation.');
            }
        });
    }

    public function tenantColumn(): string
    {
        return 'foundation_id';
    }

    /** The owning foundation id (null for platform-owned rows). */
    public function tenantOwnerId(): ?string
    {
        return $this->{$this->tenantColumn()};
    }

    public function platformSeesAllTenants(): bool
    {
        return false;
    }

    public function foundation(): BelongsTo
    {
        return $this->belongsTo(Foundation::class, $this->tenantColumn())->withoutGlobalScopes();
    }
}
