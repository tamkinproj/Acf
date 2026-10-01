<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use Syncable, BelongsToFoundation;

    /** Hierarchy order. A child must sit at a deeper level than its parent (levels may be skipped). */
    public const LEVELS = ['country' => 1, 'region' => 2, 'province' => 3, 'municipality' => 4, 'barangay' => 5, 'site' => 6];

    protected $guarded = [];
    protected $casts = ['is_active' => 'boolean', 'depth' => 'integer', 'latitude' => 'float', 'longitude' => 'float'];

    private static bool $refreshing = false;

    protected static function booted(): void
    {
        // A move changes every descendant's derived path; each descendant save replicates normally.
        static::updated(function (Location $location) {
            if (! self::$refreshing && $location->wasChanged('path')) {
                self::$refreshing = true;
                try {
                    app(\App\Core\Locations\LocationService::class)->refreshDescendants($location, (string) $location->getOriginal('path'));
                } finally {
                    self::$refreshing = false;
                }
            }
        });
    }

    public static function syncFields(): array
    {
        return ['parent_id', 'level', 'name', 'code', 'path', 'depth', 'latitude', 'longitude', 'is_active'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
