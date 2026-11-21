<?php

namespace App\Models\Attendance;

use App\Models\Concerns\BelongsToFoundation;
use Illuminate\Database\Eloquent\Model;

/** Base of the attendance records: every one belongs to a foundation. */
abstract class Att extends Model
{
    use BelongsToFoundation;

    protected $guarded = ['id'];
}
