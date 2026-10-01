<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class OrganizationContact extends Model
{
    use Syncable, BelongsToFoundation;

    protected $guarded = [];
    protected $casts = ['is_primary' => 'boolean'];

    public static function syncFields(): array
    {
        return ['organization_id', 'name', 'title', 'email', 'phone', 'is_primary', 'notes'];
    }
}
