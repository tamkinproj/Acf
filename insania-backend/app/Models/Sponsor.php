<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class Sponsor extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'name', 'organization', 'email', 'phone', 'notes'];
}
