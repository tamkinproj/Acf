<?php

namespace App\Models\Attendance;

class Leave extends Att
{
    protected $table = 'att_leaves';

    protected $casts = ['start_date' => 'date:Y-m-d', 'end_date' => 'date:Y-m-d', 'paid' => 'boolean', 'is_sample' => 'boolean'];
}
