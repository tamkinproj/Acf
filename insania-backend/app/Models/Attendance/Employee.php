<?php

namespace App\Models\Attendance;

use App\Models\User;

class Employee extends Att
{
    protected $table = 'att_employees';

    protected $casts = ['hired_on' => 'date', 'monthly_salary' => 'decimal:2', 'is_sample' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function records()
    {
        return $this->hasMany(Record::class, 'employee_id');
    }

    public function leaves()
    {
        return $this->hasMany(Leave::class, 'employee_id');
    }
}
