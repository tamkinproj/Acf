<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class SyncConflict extends Model
{
    use HasUuids;

    protected $guarded = [];
    protected $casts = [
        'conflicting_fields' => 'array',
        'local_payload' => 'array',
        'server_payload' => 'array',
        'resolved_at' => 'datetime',
        'base_version' => 'integer',
        'server_version' => 'integer',
    ];

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }
}
