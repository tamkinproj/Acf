<?php

namespace App\Models;

use App\Programs\ProgramModule;
use App\Programs\ProgramModules;
use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use Syncable, BelongsToFoundation;

    public const DRAFT = 'draft';
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const ARCHIVED = 'archived';
    public const STATUSES = [self::DRAFT, self::ACTIVE, self::INACTIVE, self::ARCHIVED];

    /** From → allowed next statuses. */
    public const TRANSITIONS = [
        self::DRAFT => [self::ACTIVE, self::ARCHIVED],
        self::ACTIVE => [self::INACTIVE, self::ARCHIVED],
        self::INACTIVE => [self::ACTIVE, self::ARCHIVED],
        self::ARCHIVED => [self::INACTIVE],
    ];

    protected $guarded = [];
    protected $hidden = ['logo_path'];
    protected $casts = ['config' => 'array', 'start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d'];

    public static function syncFields(): array
    {
        return ['name', 'slug', 'description', 'category', 'module', 'status', 'start_date', 'end_date', 'config'];
    }

    public function moduleDefinition(): ?ProgramModule
    {
        return ProgramModules::get($this->module);
    }

    public function isActive(): bool
    {
        return $this->status === self::ACTIVE;
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProgramUser::class);
    }

    public function organizationLinks(): HasMany
    {
        return $this->hasMany(ProgramOrganization::class);
    }
}
