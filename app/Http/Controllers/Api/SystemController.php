<?php

namespace App\Http\Controllers\Api;

use App\Core\Settings\SettingsCatalog;
use App\Core\Settings\SettingsService;
use App\Http\Controllers\Controller;
use App\Models\Foundation;
use App\Models\SystemState;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SystemController extends Controller
{
    /** Public, non-sensitive: lets the login page and offline shell brand themselves. */
    public function status(SettingsService $settings): JsonResponse
    {
        $f = Foundation::current();

        return ApiResponse::ok([
            'installed' => true,
            'version' => config('foundation.version'),
            'name' => $f?->name ?? $settings->get('app.name'),
            'short_name' => $f?->short_name,
            'logo_hash' => $f?->logo_hash,
            'locale' => $settings->get('app.locale'),
        ]);
    }

    /** All settings with their catalog metadata (writes go through sync). */
    public function settings(SettingsService $settings): JsonResponse
    {
        $values = $settings->all();

        return ApiResponse::ok(collect(SettingsCatalog::all())->map(fn ($def, $key) => [
            'key' => $key, 'group' => $def['group'], 'value' => $values[$key] ?? $def['default'],
        ])->values());
    }

    public function health(): JsonResponse
    {
        $checks = [];
        try {
            DB::connection()->getPdo();
            $checks['database'] = ['ok' => true];
        } catch (\Throwable) {
            $checks['database'] = ['ok' => false];
        }
        foreach (['storage' => storage_path(), 'install_state' => config('foundation.install_path')] as $name => $dir) {
            $checks[$name] = ['ok' => is_dir($dir) && is_writable($dir)];
        }
        $pending = 0;
        try {
            $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
            $pending = count(array_diff(array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php'))), $ran));
        } catch (\Throwable) {
        }
        $checks['migrations'] = ['ok' => $pending === 0, 'pending' => $pending];
        $checks['schema_version'] = [
            'ok' => (int) SystemState::get('schema_version', 0) === (int) config('foundation.schema_version'),
            'installed' => (int) SystemState::get('schema_version', 0), 'code' => (int) config('foundation.schema_version'),
        ];
        $healthy = collect($checks)->every(fn ($c) => $c['ok']);

        return ApiResponse::ok(['healthy' => $healthy, 'checks' => $checks], status: $healthy ? 200 : 503);
    }
}
