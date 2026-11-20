<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class Guardian extends Model
{
    use BelongsToFoundation;

    protected $fillable = ['foundation_id', 'name', 'relation', 'phone'];

    public function children()
    {
        return $this->hasMany(Child::class);
    }
}
