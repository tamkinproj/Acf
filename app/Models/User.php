<?php

namespace App\Models;

use App\Core\Access\RoleCatalog;
use App\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Syncable;

    protected $guarded = [];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /** `password` is deliberately absent: credentials never replicate to devices. */
    public static function syncFields(): array
    {
        return ['name', 'email', 'phone', 'role_id', 'status', 'locale'];
    }

    public function setEmailAttribute(string $value): void
    {
        $this->attributes['email'] = strtolower(trim($value));
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->deleted_at === null;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->key === RoleCatalog::SUPER_ADMIN;
    }

    public function hasPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($key, $this->role?->permissions ?? [], true);
    }

    /** @return list<string> */
    public function permissionKeys(): array
    {
        return $this->isSuperAdmin() ? \App\Core\Access\PermissionCatalog::keys() : array_values($this->role?->permissions ?? []);
    }
}
