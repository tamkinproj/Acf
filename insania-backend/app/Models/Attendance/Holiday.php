<?php

namespace App\Models\Attendance;

class Holiday extends Att
{
    protected $table = 'att_holidays';

    protected $casts = ['holiday_date' => 'date:Y-m-d', 'is_sample' => 'boolean'];
}
