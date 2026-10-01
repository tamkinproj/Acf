<?php

namespace App\Models;

use App\Core\Access\PermissionCatalog;
use App\Core\Access\RoleCatalog;
use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

class User extends Authenticatable
{
    use Syncable, BelongsToFoundation;

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

    public function isPlatformAdmin(): bool
    {
        return $this->foundation_id === null && $this->role?->key === RoleCatalog::PLATFORM_ADMIN;
    }

    public function isFoundationAdmin(): bool
    {
        return $this->foundation_id !== null && $this->role?->key === RoleCatalog::FOUNDATION_ADMIN;
    }

    /** True for people who run a whole tenant: they implicitly hold every foundation and program permission. */
    public function holdsEverything(): bool
    {
        return $this->isPlatformAdmin() || $this->isFoundationAdmin();
    }

    public function programMemberships(): HasMany
    {
        return $this->hasMany(ProgramUser::class);
    }

    /** @var array<string,list<string>>|null program id => permissions granted by the program role */
    private ?array $programGrants = null;

    /**
     * Foundation-wide when $program is null; inside a program, the program role's grants count as well.
     * Platform permissions are held only by platform administrators; foundation administrators hold everything
     * in their own foundation.
     */
    public function hasPermission(string $key, ?Program $program = null): bool
    {
        $scope = PermissionCatalog::scopeOf($key);
        if ($scope === null) {
            return false;
        }
        if ($this->isPlatformAdmin()) {
            return $scope === PermissionCatalog::PLATFORM;
        }
        if ($scope === PermissionCatalog::PLATFORM || $this->foundation_id === null) {
            return false;
        }
        if ($this->isFoundationAdmin()) {
            return true;
        }
        if (in_array($key, $this->role?->permissions ?? [], true)) {
            return true;
        }

        return $program !== null && in_array($key, $this->programGrants()[$program->getKey()] ?? [], true);
    }

    /** @return array<string,list<string>> */
    public function programGrants(): array
    {
        return $this->programGrants ??= $this->programMemberships()->with('role:id,permissions')->get()
            ->mapWithKeys(fn (ProgramUser $m) => [$m->program_id => array_values($m->role?->permissions ?? [])])->all();
    }

    public function forgetGrants(): void
    {
        $this->programGrants = null;
        $this->unsetRelation('role');
    }

    /** Foundation-wide permissions (what the navigation and the dashboard care about). @return list<string> */
    public function permissionKeys(): array
    {
        if ($this->isPlatformAdmin()) {
            return PermissionCatalog::keys(PermissionCatalog::PLATFORM);
        }
        if ($this->foundation_id === null) {
            return [];
        }
        if ($this->isFoundationAdmin()) {
            return PermissionCatalog::keysIn([PermissionCatalog::FOUNDATION, PermissionCatalog::PROGRAM]);
        }

        return array_values(array_filter($this->role?->permissions ?? [], fn ($k) => PermissionCatalog::exists($k)));
    }
}
