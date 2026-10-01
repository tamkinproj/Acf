<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\IsFoundation;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A foundation: one tenant of the platform. Its users, programs and records belong to it and to nobody else. */
class Foundation extends Model
{
    use Syncable, IsFoundation;

    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const SUSPENDED = 'suspended';
    public const STATUSES = [self::ACTIVE, self::INACTIVE, self::SUSPENDED];

    protected $guarded = [];
    protected $hidden = ['logo_path'];
    protected $casts = ['status_changed_at' => 'datetime'];

    public static function syncFields(): array
    {
        return ['name', 'legal_name', 'short_name', 'description', 'address', 'country', 'phone', 'email', 'website',
            'registration_number', 'registration_info', 'logo_hash', 'default_location_id', 'status'];
    }

    /** The foundation of the current tenant context (null for platform, system or unresolved contexts). */
    public static function current(): ?self
    {
        $ctx = app(TenantContext::class);

        return $ctx->isTenant() ? static::query()->find($ctx->id()) : null;
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE && $this->deleted_at === null;
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }
}
