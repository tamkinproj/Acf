<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationForm extends Model
{
    use Syncable, BelongsToFoundation;

    public const DRAFT = 'draft';
    public const PUBLISHED = 'published';
    public const UNPUBLISHED = 'unpublished';

    protected $guarded = [];
    /** The link token is a credential: it must never reach the activity log or a sync payload. */
    protected $hidden = ['public_token'];
    protected array $auditExcept = ['public_token'];
    protected $casts = ['settings' => 'array'];

    public static function syncFields(): array
    {
        return ['program_id', 'title', 'description', 'status', 'published_version', 'settings'];
    }

    public function auditLabel(): string
    {
        return (string) $this->title;
    }

    public function sections(): HasMany
    {
        return $this->hasMany(RegistrationFormSection::class, 'form_id')->orderBy('position');
    }

    public function fields(): HasMany
    {
        return $this->hasMany(RegistrationFormField::class, 'form_id')->orderBy('position');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RegistrationFormVersion::class, 'form_id')->orderByDesc('version');
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class, 'form_id');
    }

    public function isOpen(): bool
    {
        $closes = $this->settings['closes_on'] ?? null;

        return $this->status === self::PUBLISHED && ($closes === null || now()->lte(\Illuminate\Support\Carbon::parse($closes)->endOfDay()));
    }
}
