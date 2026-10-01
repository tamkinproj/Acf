<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use Syncable;

    protected $guarded = [];
    protected $casts = ['permissions' => 'array', 'is_system' => 'boolean'];

    public static function syncFields(): array
    {
        return ['key', 'name', 'description', 'is_system', 'permissions'];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
