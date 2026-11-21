<?php

namespace App\Models\Education;

class Institution extends Edu
{
    protected $table = 'edu_institutions';

    protected $casts = ['since' => 'date'];

    public function teachers()
    {
        return $this->hasMany(Teacher::class, 'institution_id');
    }
}
