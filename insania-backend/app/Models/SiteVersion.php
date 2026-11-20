<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class SiteVersion extends Model
{
    use BelongsToFoundation;

    public $timestamps = false;

    protected $fillable = ['foundation_id', 'version', 'content', 'published_by'];

    protected $casts = ['content' => 'array', 'created_at' => 'datetime'];
}
