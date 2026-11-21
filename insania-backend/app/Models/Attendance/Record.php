<?php

namespace App\Models\Attendance;

class Record extends Att
{
    protected $table = 'att_records';

    protected $casts = ['work_date' => 'date:Y-m-d', 'clock_in_at' => 'datetime', 'clock_out_at' => 'datetime', 'is_sample' => 'boolean'];

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }
}
