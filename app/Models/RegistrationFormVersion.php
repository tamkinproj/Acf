<?php

namespace App\Models;

use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** An immutable snapshot of a published form. Submissions point at one, so old answers keep their meaning. */
class RegistrationFormVersion extends Model
{
    use HasUuids, BelongsToFoundation;

    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['schema' => 'array', 'published_at' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('A published form version cannot be changed.'));
    }
}
