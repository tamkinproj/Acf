<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class SponsorshipPayment extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'sponsorship_id', 'month', 'amount', 'method', 'status', 'paid_at', 'recorded_by'];

    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];

    public function sponsorship()
    {
        return $this->belongsTo(Sponsorship::class);
    }
}
