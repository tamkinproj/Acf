<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class ChildDocument extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'child_id', 'type', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    protected $hidden = ['path'];

    public function child()
    {
        return $this->belongsTo(Child::class);
    }
}
