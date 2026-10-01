<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use Syncable, BelongsToFoundation;

    protected $guarded = [];
    protected $casts = ['value' => 'json'];

    public static function syncFields(): array
    {
        return ['key', 'group', 'value'];
    }

    public function auditLabel(): string
    {
        return $this->key;
    }
}
