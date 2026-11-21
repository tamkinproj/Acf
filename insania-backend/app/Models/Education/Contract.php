<?php

namespace App\Models\Education;

class Contract extends Edu
{
    protected $table = 'edu_contracts';

    protected $casts = ['start_date' => 'date', 'end_date' => 'date', 'monthly_salary' => 'decimal:2'];

    /** active (more than 30 days left), ending_soon (30 days or fewer), expired. */
    public function state(): string
    {
        $today = now()->startOfDay();
        if ($this->end_date->lt($today)) {
            return 'expired';
        }

        return $this->end_date->lte($today->copy()->addDays(30)) ? 'ending_soon' : 'active';
    }

    public function daysLeft(): int
    {
        return (int) now()->startOfDay()->diffInDays($this->end_date, false);
    }
}
