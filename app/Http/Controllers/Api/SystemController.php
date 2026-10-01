<?php

namespace App\Http\Controllers\Api;

use App\Core\Settings\SettingsCatalog;
use App\Core\Settings\SettingsService;
use App\Http\Controllers\Controller;
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
        // The sign-in page belongs to the platform: it never reveals which foundations exist.
        $name = app(\App\Tenancy\TenantContext::class)->asPlatform(fn () => $settings->get('app.name'));

        return ApiResponse::ok([
            'installed' => true,
            'version' => config('foundation.version'),
            'name' => $name,
            'short_name' => null,
            'logo_hash' => null,
            'locale' => 'en',
        ]);
    }

    /** All settings with their catalog metadata (writes go through sync). */
    public function settings(SettingsService $settings): JsonResponse
    {
        $values = $settings->all();

        return ApiResponse::ok(collect(SettingsCatalog::all())->map(fn ($def, $key) => [
            'key' => $key, 'group' => $def['group'], 'value' => $values[$key] ?? $def['default'],
        ])->when(app(\App\Tenancy\TenantContext::class)->isPlatform(), fn ($c) => $c->only(SettingsCatalog::PLATFORM_KEYS))->values());
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
