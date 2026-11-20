<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'name_en', 'name_ar', 'location', 'budget', 'spent', 'status'];

    protected $casts = ['budget' => 'decimal:2', 'spent' => 'decimal:2'];

    public function toApi(): array
    {
        $budget = (float) $this->budget;

        return [
            'id' => $this->id, 'name_en' => $this->name_en, 'name_ar' => $this->name_ar, 'location' => $this->location,
            'budget' => $budget, 'spent' => (float) $this->spent, 'status' => $this->status,
            'progress' => $budget > 0 ? (int) round((float) $this->spent / $budget * 100) : 0,
        ];
    }
}
