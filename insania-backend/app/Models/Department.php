<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['foundation_id', 'key', 'name_en', 'name_ar'];
}
