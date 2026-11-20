<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class SiteContent extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'draft', 'published', 'version', 'published_at', 'published_by', 'updated_by'];

    protected $casts = ['draft' => 'array', 'published' => 'array', 'published_at' => 'datetime'];
}
