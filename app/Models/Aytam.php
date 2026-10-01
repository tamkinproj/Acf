<?php

namespace App\Models;

use App\Modules\Aytam\NameNormalizer;
use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** The one permanent master record of an Aytam. Everything else (registrations, imports, documents) points at it. */
class Aytam extends Model
{
    use Syncable, BelongsToFoundation;

    protected $table = 'aytam';

    public const DRAFT = 'draft';
    public const PENDING_REVIEW = 'pending_review';
    public const NEEDS_CORRECTION = 'needs_correction';
    public const APPROVED = 'approved';
    public const ACTIVE = 'active';
    public const INACTIVE = 'inactive';
    public const ARCHIVED = 'archived';
    public const STATUSES = [self::DRAFT, self::PENDING_REVIEW, self::NEEDS_CORRECTION, self::APPROVED, self::ACTIVE, self::INACTIVE, self::ARCHIVED];

    /** Fields a person with only the restricted "assigned records" access may add or change. */
    public const FIELD_EDITABLE = ['phone', 'email', 'country', 'region', 'province', 'city', 'barangay', 'address_detail',
        'education_level', 'school', 'grade', 'education_notes'];

    protected $guarded = [];
    /** Contact details and free text stay out of the activity log: it records that they changed, not what they say. */
    protected array $auditExcept = ['phone', 'email', 'address_detail', 'education_notes', 'normalized_name'];
    protected $casts = ['date_of_birth' => 'date:Y-m-d', 'approved_at' => 'datetime'];

    public static function syncFields(): array
    {
        return ['aytam_code', 'first_name', 'middle_name', 'last_name', 'arabic_name', 'date_of_birth', 'gender', 'nationality',
            'country', 'region', 'province', 'city', 'barangay', 'address_detail', 'family_id', 'guardian_id',
            'education_level', 'school', 'grade', 'education_notes', 'phone', 'email', 'status', 'status_note', 'source',
            'registration_id', 'legacy_ref', 'approved_at', 'approved_by'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $a) {
            if ($a->isDirty(['first_name', 'middle_name', 'last_name', 'arabic_name']) || $a->normalized_name === null) {
                $a->normalized_name = NameNormalizer::name($a->first_name, $a->middle_name, $a->last_name, $a->arabic_name);
            }
        });
    }

    public function auditLabel(): string
    {
        return $this->aytam_code.' '.$this->fullName();
    }

    public function fullName(): string
    {
        return trim(implode(' ', array_filter([$this->first_name, $this->middle_name, $this->last_name])));
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function family(): BelongsTo
    {
        return $this->belongsTo(Family::class);
    }

    public function guardian(): BelongsTo
    {
        return $this->belongsTo(Guardian::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(AytamAssignment::class);
    }
}
