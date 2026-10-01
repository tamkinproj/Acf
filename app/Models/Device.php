<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use Illuminate\Database\Eloquent\Model;

class Device extends Model
{
    use Syncable;

    protected $guarded = [];
    protected $hidden = ['token_hash'];
    protected $casts = [
        'is_primary' => 'boolean',
        'last_seen_at' => 'datetime',
        'revoked_at' => 'datetime',
        'last_pull_seq' => 'integer',
    ];

    /** `token_hash`, last_seen_at and cursors are server-local bookkeeping, not replicated. */
    public static function syncFields(): array
    {
        return ['device_code', 'name', 'type', 'is_primary', 'revoked_at'];
    }

    public function isClaimed(): bool
    {
        return $this->token_hash !== null;
    }

    public function isRevoked(): bool
    {
        return $this->revoked_at !== null || $this->deleted_at !== null;
    }

    public function isOnline(): bool
    {
        return $this->last_seen_at !== null
            && $this->last_seen_at->gt(now()->subMinutes((int) config('foundation.device.online_window_minutes')));
    }
}
