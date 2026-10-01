<?php

namespace App\Models;

use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ImportRow extends Model
{
    use HasUuids, BelongsToFoundation;

    public const PENDING = 'pending';
    public const VALID = 'valid';
    public const INVALID = 'invalid';
    public const DUPLICATE = 'duplicate';
    public const IMPORTED = 'imported';
    public const SKIPPED = 'skipped';

    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['raw' => 'array', 'data' => 'array', 'errors' => 'array', 'matches' => 'array'];
}
