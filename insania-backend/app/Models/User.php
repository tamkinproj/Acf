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
        'name', 'email', 'username', 'password', 'role', 'foundation_id', 'department_id', 'guardian_id', 'phone', 'active', 'preferences', 'access_overrides', 'avatar_path', 'avatar_v', 'child_id', 'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token', 'avatar_path'];

    protected static function booted(): void
    {
        // every account has a username: one is made from the email when none is given
        static::creating(function (User $u) {
            $u->username = \App\Support\Usernames::normalise($u->username);
            if ($u->username === '') {
                $u->username = \App\Support\Usernames::suggest((string) ($u->email ?: $u->name), fn ($c) => static::where('username', $c)->exists());
            }
        });
    }

    public function setUsernameAttribute($v): void
    {
        $this->attributes['username'] = \App\Support\Usernames::normalise($v);
    }

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'active' => 'boolean',
        'must_change_password' => 'boolean',
        'preferences' => 'array',
        'access_overrides' => 'array',
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

    public function child()
    {
        return $this->belongsTo(Child::class)->withoutGlobalScopes();
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
            'username' => $this->username,
            'avatar' => $this->avatar_v,   // a version number when the person has a picture, else null
            'role' => $this->role,
            'role_label' => config("roles.roles.{$this->role}.label"),
            'kind' => config("roles.roles.{$this->role}.kind"),
            'department' => $this->department_key,
            'foundation_id' => $this->foundation_id,
            'active' => $this->active,
            'must_change_password' => (bool) $this->must_change_password,
            'permissions' => \App\Support\PermissionCatalog::forUser($this),
            'preferences' => $this->preferences ?? ['reminders' => true, 'show_amounts' => true],
        ];
    }
}
