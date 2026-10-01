<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use Syncable, BelongsToFoundation;

    protected $guarded = [];
    protected $casts = ['permissions' => 'array', 'is_system' => 'boolean'];

    protected static function booted(): void
    {
        static::saved(fn () => app(\App\Core\Access\GrantCache::class)->forget());
        static::deleted(fn () => app(\App\Core\Access\GrantCache::class)->forget());
    }

    public static function syncFields(): array
    {
        return ['key', 'name', 'description', 'is_system', 'scope', 'module', 'permissions'];
    }

    public function isFixed(): bool
    {
        return in_array($this->key, \App\Core\Access\RoleCatalog::FIXED, true);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
