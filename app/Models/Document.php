<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

/** One VERSION of one document. Versions of the same slot share group_id; exactly one is current. */
class Document extends Model
{
    use Syncable, BelongsToFoundation;

    public const TYPES = ['passport', 'birth_certificate', 'diploma', 'transcript', 'photo', 'medical_certificate', 'police_clearance', 'recommendation_letter', 'other'];
    public const PENDING = 'pending';
    public const VERIFIED = 'verified';
    public const REJECTED = 'rejected';
    public const EXPIRED = 'expired';
    public const STATUSES = [self::PENDING, self::VERIFIED, self::REJECTED, self::EXPIRED];

    protected $guarded = [];
    protected $hidden = ['storage_path'];
    /** A file name can contain a child's name; the log records that a document changed, not what it was called. */
    protected array $auditExcept = ['original_name', 'notes', 'sha256'];
    protected $casts = ['is_current' => 'boolean', 'verified_at' => 'datetime', 'expires_on' => 'date:Y-m-d', 'size' => 'integer', 'doc_version' => 'integer'];

    /** The storage path never leaves the server. */
    public static function syncFields(): array
    {
        return ['aytam_id', 'registration_id', 'group_id', 'doc_version', 'is_current', 'type', 'original_name', 'mime', 'size', 'sha256',
            'verification_status', 'verified_by', 'verified_at', 'rejection_reason', 'expires_on', 'notes'];
    }

    public function auditLabel(): string
    {
        return $this->type.' v'.$this->doc_version;
    }

    /** True when an expiry date has passed (shown as "expired" without waiting for a nightly job). */
    public function effectiveStatus(): string
    {
        return $this->expires_on && $this->expires_on->isPast() && $this->verification_status === self::VERIFIED ? self::EXPIRED : $this->verification_status;
    }
}
