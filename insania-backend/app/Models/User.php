<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'role', 'foundation_id', 'department_id', 'guardian_id', 'phone', 'active', 'preferences',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
        'preferences' => 'array',
    ];

    public function foundation()
    {
        return $this->belongsTo(Foundation::class);
    }

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function guardian()
    {
        return $this->belongsTo(Guardian::class)->withoutGlobalScopes();
    }

    public function getDepartmentKeyAttribute(): ?string
    {
        return $this->department_id ? $this->department?->key : null;
    }

    public function isTechnical(): bool
    {
        return config("roles.roles.{$this->role}.kind") === 'technical';
    }

    /** What the staff app needs to know about the signed-in person. */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'role_label' => config("roles.roles.{$this->role}.label"),
            'kind' => config("roles.roles.{$this->role}.kind"),
            'department' => $this->department_key,
            'foundation_id' => $this->foundation_id,
            'active' => $this->active,
            'permissions' => \App\Support\PermissionCatalog::forUser($this),
            'preferences' => $this->preferences ?? ['reminders' => true, 'show_amounts' => true],
        ];
    }
}
