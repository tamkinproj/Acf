<?php

namespace App\Models;

use App\Sync\Concerns\HasSyncMetadata;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's role inside one program. */
class ProgramUser extends Model
{
    use HasSyncMetadata, BelongsToFoundation;

    protected $guarded = [];

    protected static function booted(): void
    {
        $forget = fn (self $m) => app(\App\Core\Access\GrantCache::class)->forget($m->user_id);
        static::saved($forget);
        static::deleted($forget);
    }

    public static function syncFields(): array
    {
        return ['program_id', 'user_id', 'role_id'];
    }

    public function auditLabel(): string
    {
        return ($this->user?->name ?? $this->user_id).' in '.($this->program?->name ?? $this->program_id);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
