<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Per-installation preferences (e.g. upstream server URL). Never synced. */
class DeviceSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];

    public static function get(string $key, ?string $default = null): ?string
    {
        return static::query()->find($key)?->value ?? $default;
    }

    public static function put(string $key, ?string $value): void
    {
        static::query()->updateOrInsert(['key' => $key], ['value' => $value, 'updated_at' => now()]);
    }
}
