<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The organization profile. One row per installation. */
class Foundation extends Model
{
    use Syncable;

    protected $guarded = [];
    protected $hidden = ['logo_path'];

    public static function syncFields(): array
    {
        return ['name', 'short_name', 'description', 'address', 'phone', 'email', 'website',
            'registration_number', 'registration_info', 'logo_hash', 'default_location_id'];
    }

    public static function current(): ?self
    {
        return static::query()->orderBy('created_at')->first();
    }

    public function defaultLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id');
    }
}
