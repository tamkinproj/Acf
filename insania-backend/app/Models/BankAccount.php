<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'child_id', 'holder', 'bank_name', 'last4', 'verified', 'verified_by', 'verified_at'];

    protected $casts = ['verified' => 'boolean', 'verified_at' => 'datetime'];

    public function child()
    {
        return $this->belongsTo(Child::class);
    }
}
