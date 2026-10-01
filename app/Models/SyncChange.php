<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One row of the replication change feed. Append-only. */
class SyncChange extends Model
{
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
