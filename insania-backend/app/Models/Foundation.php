<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Foundation extends Model
{
    protected $fillable = ['name', 'name_ar', 'slug', 'status'];

    /**
     * The foundation this installation serves. Normally there is exactly one; if there are several, it is the one with the most accounts
     * (that is where the staff work), then the oldest. Used where nobody is signed in: the public application form.
     */
    public static function primary(): ?self
    {
        $id = \Illuminate\Support\Facades\DB::table('users')->whereNotNull('foundation_id')->groupBy('foundation_id')
            ->orderByRaw('count(*) desc')->orderBy('foundation_id')->value('foundation_id');

        return $id ? self::query()->find($id) : self::query()->orderBy('id')->first();
    }

    public function departments()
    {
        return $this->hasMany(Department::class);
    }
}
