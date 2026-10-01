<?php

namespace App\Models;

use App\Sync\Concerns\Syncable;
use App\Tenancy\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    use Syncable, BelongsToFoundation;

    protected $guarded = [];
    protected array $auditExcept = ['phone', 'email', 'address', 'notes'];

    public static function syncFields(): array
    {
        return ['program_id', 'full_name', 'relationship', 'phone', 'email', 'address', 'notes'];
    }

    public function auditLabel(): string
    {
        return (string) $this->full_name;
    }
}
