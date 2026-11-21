<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notice extends Model
{
    public $timestamps = false;

    protected $guarded = ['id'];

    protected $casts = ['read_at' => 'datetime', 'created_at' => 'datetime'];
}
