<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = [
        'name', 'key_prefix', 'key_hash', 'abilities', 'foundation_id',
        'created_by', 'last_used_at', 'expires_at', 'revoked_at',
    ];

    protected $casts = [
        'abilities' => 'array',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function foundation()
    {
        return $this->belongsTo(Foundation::class);
    }

    public function isActive(): bool
    {
        if ($this->revoked_at) {
            return false;
        }

        return ! $this->expires_at || $this->expires_at->isFuture();
    }
}
