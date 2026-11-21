<?php

namespace App\Models;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

class WorkActivity extends Model
{
    use BelongsToFoundation;

    protected $table = 'work_activity';

    protected $guarded = ['id'];

    protected $casts = ['day' => 'date:Y-m-d', 'first_at' => 'datetime', 'last_at' => 'datetime', 'areas' => 'array', 'is_sample' => 'boolean'];
}
