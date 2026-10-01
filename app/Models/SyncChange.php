<?php

namespace App\Models;

use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

/** One row of the replication change feed. Append-only. */
class SyncChange extends Model
{
    use BelongsToFoundation;

    protected $primaryKey = 'seq';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = [
        'fields' => 'array',
        'payload' => 'array',
        'version' => 'integer',
        'created_at' => 'datetime',
        'client_ts' => 'datetime',
    ];
}
