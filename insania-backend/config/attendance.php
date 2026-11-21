<?php

/** Office attendance: where the office is, what the pay rules assume, and how clock-in photos are kept. */
return [
    // Pagadian City. Dates, shift times and "late" are always worked out in this time zone, whatever the phone's clock says.
    'timezone' => env('ATTENDANCE_TZ', 'Asia/Manila'),
    'currency' => 'PHP',
    // A face photo is taken at every clock in and clock out. Set to false to let people clock without it.
    'require_photo' => true,
    'photo_max_kb' => 4096,
    // What a new employee starts with (each one can be changed on the employee's page).
    'defaults' => ['shift_start' => '08:00', 'shift_end' => '17:00', 'break_minutes' => 60, 'grace_minutes' => 0, 'work_days' => '1,2,3,4,5'],
];
