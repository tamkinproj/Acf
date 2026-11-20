<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Foundation extends Model
{
    protected $fillable = ['name', 'name_ar', 'slug', 'status'];

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
