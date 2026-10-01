<?php

namespace App\Core\Settings;

use App\Models\Setting;

class SettingsService
{
    /** @var array<string,mixed>|null */
    private ?array $cache = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? (SettingsCatalog::all()[$key]['default'] ?? null));
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->cache ??= Setting::query()->pluck('value', 'key')->all();
    }

    public function forget(): void
    {
        $this->cache = null;
    }

    /** Create a row for every catalogued key that does not exist yet (install and upgrade). */
    public function ensureDefaults(array $overrides = []): int
    {
        $existing = Setting::withTrashed()->pluck('key')->all();
        $created = 0;
        foreach (SettingsCatalog::all() as $key => $def) {
            if (in_array($key, $existing, true)) {
                continue;
            }
            Setting::create(['key' => $key, 'group' => $def['group'], 'value' => $overrides[$key] ?? $def['default']]);
            $created++;
        }
        $this->forget();

        return $created;
    }

    /** Push stored settings into the running configuration (name, locale, currency). */
    public function applyRuntime(): void
    {
        try {
            $s = $this->all();
        } catch (\Throwable) {
            return;   // database not ready (e.g. mid-install): keep config defaults
        }
        if (isset($s['app.name'])) {
            config(['app.name' => $s['app.name']]);
        }
        // Deliberately NOT applying app.timezone: it is a display preference for clients. The server
        // stores and exchanges UTC only, so changing it can never reinterpret existing timestamps.
        if (! empty($s['app.locale'])) {
            app()->setLocale($s['app.locale']);
        }
        if (! empty($s['app.currency'])) {
            config(['foundation.currency' => $s['app.currency']]);
        }
    }
}
