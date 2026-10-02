<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use Syncable, BelongsToFoundation;

    public const TYPES = ['partner', 'donor', 'government', 'school', 'hospital', 'ngo', 'community', 'business', 'other'];

    protected $guarded = [];
    protected array $auditExcept = ['address', 'email', 'phone', 'notes'];

    public static function syncFields(): array
    {
        return ['name', 'type', 'country', 'location', 'address', 'email', 'phone', 'website', 'notes', 'status'];
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(OrganizationContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    public function programLinks(): HasMany
    {
        return $this->hasMany(ProgramOrganization::class);
    }
}
