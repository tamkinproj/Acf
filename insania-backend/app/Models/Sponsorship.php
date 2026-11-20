<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class Sponsorship extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'child_id', 'sponsor_id', 'channel', 'status', 'pay_type', 'monthly_amount', 'start_date', 'end_date'];

    protected $casts = ['monthly_amount' => 'decimal:2', 'start_date' => 'date', 'end_date' => 'date'];

    public function child()
    {
        return $this->belongsTo(Child::class);
    }

    public function sponsor()
    {
        return $this->belongsTo(Sponsor::class);
    }

    public function payments()
    {
        return $this->hasMany(SponsorshipPayment::class);
    }
}
