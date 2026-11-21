<?php

namespace App\Support;

use App\Models\Child;

/** Reads config/orphan_fields.php: which keys exist, how to check them, and how a record becomes the flat list the app shows. */
final class OrphanFields
{
    public static function all(): array
    {
        return (array) config('orphan_fields.fields');
    }

    private static function ruleOf(array $def): array
    {
        return array_values(array_filter($def, fn ($v, $k) => is_int($k), ARRAY_FILTER_USE_BOTH));
    }

    /** Keys stored as columns of `children`. */
    public static function childKeys(): array
    {
        return array_values(array_filter(array_keys(self::all()), fn ($k) => ! str_starts_with($k, 'guardian_') && ! str_starts_with($k, 'bank_')));
    }

    /** Laravel rules for the flat keys. $publicOnly drops the keys only the foundation fills in. */
    public static function rules(bool $publicOnly = false, string $prefix = ''): array
    {
        $out = [];
        foreach (self::all() as $key => $def) {
            if ($publicOnly && ! empty($def['staff'])) {
                continue;
            }
            $out[$prefix.$key] = array_merge(['nullable'], self::ruleOf($def));
        }

        return $out;
    }

    public static function publicKeys(): array
    {
        return array_values(array_filter(array_keys(self::all()), fn ($k) => empty(self::all()[$k]['staff'])));
    }

    /** The child's information as one flat map (dates as text), including the guardian and bank account. */
    public static function details(Child $c): array
    {
        $c->loadMissing(['guardian', 'bankAccount']);
        $out = [];
        foreach (self::childKeys() as $k) {
            $v = $c->{$k};
            $out[$k] = $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : ($v === null ? null : (is_numeric($v) && in_array($k, ['brothers', 'sisters', 'aided_siblings'], true) ? (int) $v : (string) $v));
        }
        $g = $c->guardian;
        $b = $c->bankAccount;

        return $out + [
            'guardian_name' => $g?->name, 'guardian_relation' => $g?->relation, 'guardian_phone' => $g?->phone,
            'bank_holder' => $b?->holder, 'bank_name' => $b?->bank_name, 'bank_last4' => $b?->last4,
        ];
    }
}
