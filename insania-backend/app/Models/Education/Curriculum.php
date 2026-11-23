<?php

namespace App\Models\Education;

class Curriculum extends Edu
{
    use \App\Models\Concerns\BelongsToPartner;

    protected $table = 'edu_curricula';

    public function units()
    {
        return $this->hasMany(Unit::class, 'curriculum_id')->orderBy('position')->orderBy('id');
    }

    public function teachers()
    {
        return $this->hasMany(Teacher::class, 'curriculum_id');
    }
}
