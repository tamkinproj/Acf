<?php

namespace App\Core\Settings;

use App\Models\Setting;
use App\Tenancy\TenantContext;

/** Settings of the current context: a foundation's own, or the platform's (foundation_id NULL). Cached per context. */
class SettingsService
{
    /** @var array<string,array<string,mixed>> */
    private array $cache = [];

    public function __construct(private TenantContext $tenant) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? (SettingsCatalog::all()[$key]['default'] ?? null));
    }

    /** @return array<string,mixed> */
    public function all(): array
    {
        return $this->cache[$this->tenant->key()] ??= Setting::query()->pluck('value', 'key')->all();
    }

    public function forget(): void
    {
        $this->cache = [];
    }

    /**
     * Create a row for every catalogued key that does not exist yet (install, upgrade, new foundation).
     * Runs for the current context, or for $foundationId (null foundation + $platform = the platform's own).
     */
    public function ensureDefaults(array $overrides = [], ?string $foundationId = null, bool $platform = false): int
    {
        return $this->tenant->asSystem(function () use ($overrides, $foundationId, $platform) {
            $existing = Setting::withTrashed()->where('foundation_id', $foundationId)->pluck('key')->all();
            $created = 0;
            foreach (SettingsCatalog::forScope($platform) as $key => $def) {
                if (in_array($key, $existing, true)) {
                    continue;
                }
                Setting::create(['foundation_id' => $foundationId, 'key' => $key, 'group' => $def['group'], 'value' => $overrides[$key] ?? $def['default']]);
                $created++;
            }
            $this->forget();

            return $created;
        });
    }

    /** Push the current context's settings into the running configuration (name, locale, currency). */
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
