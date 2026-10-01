<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Registration extends Model
{
    use Syncable, BelongsToFoundation;

    public const PENDING_REVIEW = 'pending_review';
    public const NEEDS_CORRECTION = 'needs_correction';
    public const APPROVED = 'approved';
    public const STATUSES = [self::PENDING_REVIEW, self::NEEDS_CORRECTION, self::APPROVED];

    protected $guarded = [];
    protected $hidden = ['access_token_hash', 'submitted_ip'];
    /** An applicant's answers are personal data; the log records that a registration changed, not what it says. */
    protected array $auditExcept = ['answers', 'applicant_name', 'applicant_email', 'applicant_phone', 'access_token_hash', 'submitted_ip'];
    protected $casts = ['answers' => 'array', 'submitted_at' => 'datetime', 'last_submitted_at' => 'datetime', 'reviewed_at' => 'datetime'];

    public static function syncFields(): array
    {
        return ['program_id', 'form_id', 'form_version_id', 'reference', 'status', 'applicant_name', 'applicant_email', 'applicant_phone', 'answers',
            'submitted_at', 'last_submitted_at', 'submission_count', 'reviewer_id', 'reviewed_at', 'review_note', 'aytam_id', 'duplicate_decision'];
    }

    public function auditLabel(): string
    {
        return (string) $this->reference;
    }

    public function form(): BelongsTo
    {
        return $this->belongsTo(RegistrationForm::class, 'form_id');
    }

    public function formVersion(): BelongsTo
    {
        return $this->belongsTo(RegistrationFormVersion::class, 'form_version_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RegistrationEvent::class)->orderBy('created_at');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class, 'registration_id');
    }
}
