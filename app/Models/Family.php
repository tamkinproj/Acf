<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A household. Several Aytam can belong to one family; its details are stored once, not copied onto each child. */
class Family extends Model
{
    use Syncable, BelongsToFoundation;

    public const PARENT_STATUSES = ['living', 'deceased', 'unknown'];

    protected $guarded = [];
    protected array $auditExcept = ['phone', 'address_detail', 'notes'];

    public static function syncFields(): array
    {
        return ['program_id', 'name', 'father_name', 'father_status', 'mother_name', 'mother_status', 'guardian_id', 'phone',
            'country', 'region', 'province', 'city', 'barangay', 'address_detail', 'notes'];
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Aytam::class);
    }
}
