<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** The technical side. Everything here is about the system itself: nothing returned or accepted is a child's or a family's data. */
class SystemController extends Controller
{
    public function health()
    {
        $db = true;
        try {
            DB::select('select 1');
        } catch (\Throwable) {
            $db = false;
        }

        return ApiResponse::ok([
            'status' => $db ? 'ok' : 'degraded',
            'checks' => ['database' => $db, 'storage_writable' => is_writable(storage_path())],
            'versions' => ['php' => PHP_VERSION, 'laravel' => app()->version()],
            'environment' => app()->environment(),
            'time' => now()->toIso8601String(),
        ]);
    }

    public function settings()
    {
        return ApiResponse::ok(Setting::pluck('value', 'key'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'org_name' => ['sometimes', 'string', 'max:150'],
            'currency' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
            'reminders_default' => ['sometimes', 'boolean'],
        ]);

        foreach ($data as $key => $value) {
            Setting::updateOrCreate(['key' => $key, 'foundation_id' => $request->user()->foundation_id], ['value' => is_bool($value) ? (string) (int) $value : $value]);
        }

        Audit::record('settings.updated', $request, null, null, ['keys' => array_keys($data)]);

        return ApiResponse::updated(Setting::pluck('value', 'key'));
    }
}
