<?php

namespace App\Models\Education;

class Salary extends Edu
{
    protected $table = 'edu_salaries';

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function teacher()
    {
        return $this->belongsTo(Teacher::class, 'teacher_id');
    }
}
