<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class ChannelHistory extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'child_id', 'channel', 'since'];

    protected $casts = ['since' => 'date'];
}
