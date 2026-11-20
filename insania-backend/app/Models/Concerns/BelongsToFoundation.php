<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Replaces the old per-school scope. While someone is signed in, every query on a model using this trait
 * is limited to that person's foundation, and new rows are stamped with it.
 * Fails closed: a signed-in account with no foundation sees nothing.
 * Console jobs and seeders (nobody signed in) are not scoped and must set foundation_id themselves.
 */
trait BelongsToFoundation
{
    protected static function bootBelongsToFoundation(): void
    {
        static::addGlobalScope('foundation', function (Builder $query) {
            $user = auth()->user();

            if ($user) {
                $query->where($query->getModel()->getTable().'.foundation_id', $user->foundation_id ?? 0);
            }
        });

        static::creating(function ($model) {
            $user = auth()->user();

            if (! $model->foundation_id && $user && $user->foundation_id) {
                $model->foundation_id = $user->foundation_id;
            }
        });
    }
}
