<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Installation facts (installed_at, version, schema_version...). Local to this installation; never synced. */
class SystemState extends Model
{
    protected $table = 'system_state';
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    protected $guarded = [];
    protected $casts = ['value' => 'json'];

    public static function get(string $key, mixed $default = null): mixed
    {
        $row = static::query()->find($key);

        return $row ? $row->value : $default;
    }

    public static function put(string $key, mixed $value): void
    {
        static::query()->updateOrInsert(['key' => $key], ['value' => json_encode($value), 'updated_at' => now()]);
    }
}
