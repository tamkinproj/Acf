<?php

namespace App\Http\Controllers\Api\Platform;

use App\Core\Settings\SettingsCatalog;
use App\Core\Settings\SettingsService;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PlatformSettingsController extends Controller
{
    public function index(SettingsService $settings): JsonResponse
    {
        $settings->ensureDefaults([], null, platform: true);
        $values = $settings->all();

        return ApiResponse::ok(collect(SettingsCatalog::forScope(true))->map(fn ($def, $key) => [
            'key' => $key, 'group' => $def['group'], 'value' => $values[$key] ?? $def['default'],
        ])->values());
    }

    public function update(Request $request, SettingsService $settings): JsonResponse
    {
        $input = $request->validate(['settings' => ['required', 'array', 'min:1']])['settings'];
        $catalog = SettingsCatalog::forScope(true);
        $unknown = array_diff(array_keys($input), array_keys($catalog));
        abort_if($unknown !== [], 422, 'Unknown platform setting: '.implode(', ', $unknown));

        foreach ($input as $key => $value) {
            Validator::make(['value' => $value], ['value' => $catalog[$key]['rules']])->validate();
        }
        $settings->ensureDefaults([], null, platform: true);
        foreach ($input as $key => $value) {
            Setting::query()->where('key', $key)->firstOrFail()->update(['value' => $value]);
        }
        $settings->forget();

        return $this->index($settings);
    }
}
