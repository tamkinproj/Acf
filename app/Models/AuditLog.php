<?php

namespace App\Models;

use App\Sync\Concerns\HasSyncMetadata;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** Append-only activity record. Replicates like any other entity but can never be edited or removed. */
class AuditLog extends Model
{
    use HasSyncMetadata, BelongsToFoundation;

    protected $guarded = [];
    protected $casts = [
        'occurred_at' => 'datetime',
        'old_values' => 'array',
        'new_values' => 'array',
    ];

    public static function syncFields(): array
    {
        return ['occurred_at', 'device_id', 'user_id', 'user_name', 'action', 'subject_type', 'subject_id',
            'summary', 'old_values', 'new_values', 'correlation_id', 'ip_address'];
    }

    public function isAudited(): bool
    {
        return false;
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit log entries cannot be deleted.'));
    }
}
