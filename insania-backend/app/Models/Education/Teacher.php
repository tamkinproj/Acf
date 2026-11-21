<?php

namespace App\Models\Education;

class Teacher extends Edu
{
    protected $table = 'edu_teachers';

    protected $casts = ['hired_on' => 'date'];

    public function institution()
    {
        return $this->belongsTo(Institution::class, 'institution_id');
    }

    public function curriculum()
    {
        return $this->belongsTo(Curriculum::class, 'curriculum_id');
    }

    public function contracts()
    {
        return $this->hasMany(Contract::class, 'teacher_id')->orderByDesc('start_date')->orderByDesc('id');
    }

    public function salaries()
    {
        return $this->hasMany(Salary::class, 'teacher_id')->orderByDesc('month');
    }

    public function current(): ?Contract
    {
        return $this->contracts->first();
    }
}
