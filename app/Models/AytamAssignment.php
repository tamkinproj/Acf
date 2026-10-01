<?php

namespace App\Models;

use App\Sync\Concerns\HasSyncMetadata;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A field worker assigned to an Aytam record; it is what lets them (and only them) see it. */
class AytamAssignment extends Model
{
    use HasSyncMetadata, BelongsToFoundation;

    protected $guarded = [];

    public static function syncFields(): array
    {
        return ['program_id', 'aytam_id', 'user_id', 'assigned_by'];
    }

    public function auditLabel(): string
    {
        return $this->aytam_id.' / '.$this->user_id;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function aytam(): BelongsTo
    {
        return $this->belongsTo(Aytam::class);
    }
}
