<?php

namespace App\Models\Education;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

/** Base of the Education department's records: every one belongs to a foundation. */
abstract class Edu extends Model
{
    use BelongsToFoundation;

    protected $guarded = ['id'];
}
