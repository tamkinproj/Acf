<?php

namespace App\Models;

use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** One line of a registration's review history. Append-only. */
class RegistrationEvent extends Model
{
    use HasUuids, BelongsToFoundation;

    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['data' => 'array', 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Review history cannot be edited.'));
        static::deleting(fn () => throw new \LogicException('Review history cannot be deleted.'));
    }
}
