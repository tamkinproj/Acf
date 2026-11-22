<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrustedDevice extends Model
{
    protected $guarded = ['id'];

    protected $casts = ['last_used_at' => 'datetime', 'expires_at' => 'datetime'];
}
