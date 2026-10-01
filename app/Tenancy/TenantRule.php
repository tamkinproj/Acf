<?php

namespace App\Tenancy;

use Closure;
use Illuminate\Database\Eloquent\Builder;

/**
 * Validation rules that respect tenancy. Laravel's stock `exists:` rule queries the table directly,
 * ignoring global scopes, so a user could reference another foundation's record by guessing its id.
 */
final class TenantRule
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  (Closure(Builder):void)|null  $constrain  extra constraints (e.g. same program)
     */
    public static function exists(string $model, string $column = 'id', ?Closure $constrain = null): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($model, $column, $constrain) {
            if ($value === null || $value === '') {
                return;
            }
            $query = $model::query();
            if ($constrain) {
                $constrain($query);
            }
            if (! is_scalar($value) || ! $query->where($column, $value)->exists()) {
                $fail('The selected '.str_replace('_', ' ', $attribute).' is invalid.');
            }
        };
    }
}
