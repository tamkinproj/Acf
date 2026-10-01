<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InstallationLog extends Model
{
    protected $table = 'installation_log';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['context' => 'array', 'created_at' => 'datetime'];
}
