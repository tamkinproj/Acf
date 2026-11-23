<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use App\Models\Concerns\BelongsToPartner;
use Illuminate\Database\Eloquent\Model;

class ChildApplication extends Model
{
    use BelongsToFoundation, BelongsToPartner;

    protected $fillable = ['foundation_id', 'partner_id', 'status', 'data', 'photos', 'child_id', 'ip_hash', 'reviewed_by', 'reviewed_at', 'note', 'documents', 'method'];

    protected $casts = ['data' => 'array', 'photos' => 'array', 'documents' => 'array', 'reviewed_at' => 'datetime'];

    public function reference(): string
    {
        return 'APP-'.str_pad((string) $this->id, 5, '0', STR_PAD_LEFT);
    }
}
