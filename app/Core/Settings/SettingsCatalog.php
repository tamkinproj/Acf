<?php

namespace App\Core\Settings;

use DateTimeZone;
use Illuminate\Validation\Rule;

/**
 * Every system setting is declared here (key, group, default, validation).
 * Nothing is hard-coded elsewhere: the installer, the settings API and sync
 * all validate against this single list. Modules may register more.
 */
final class SettingsCatalog
{
    public const LOCALES = ['en', 'fil', 'ar'];
    public const CURRENCIES = ['PHP', 'USD', 'SAR', 'AED', 'EUR', 'GBP', 'MYR', 'IDR', 'SGD'];

    /** Settings the platform itself owns (the rest belong to a foundation). */
    public const PLATFORM_KEYS = ['app.name', 'app.timezone', 'app.locale', 'security.idle_lock_minutes'];

    /** @var array<string,array{group:string,default:mixed,rules:array}> */
    private static array $extra = [];

    /** @return array<string,array{group:string,default:mixed,rules:array}> */
    public static function all(): array
    {
        return array_merge([
            'app.name' => ['group' => 'general', 'default' => 'Foundation Management System', 'rules' => ['required', 'string', 'max:120']],
            // Display timezone only - the server stores and exchanges UTC.
            'app.timezone' => ['group' => 'general', 'default' => 'Asia/Manila', 'rules' => ['required', Rule::in(DateTimeZone::listIdentifiers())]],
            'app.locale' => ['group' => 'general', 'default' => 'en', 'rules' => ['required', Rule::in(self::LOCALES)]],
            'app.currency' => ['group' => 'general', 'default' => 'PHP', 'rules' => ['required', Rule::in(self::CURRENCIES)]],
            'deployment.model' => ['group' => 'system', 'default' => 'central', 'rules' => ['required', Rule::in(config('foundation.deployment_models'))]],
            'security.idle_lock_minutes' => ['group' => 'security', 'default' => 15, 'rules' => ['required', 'integer', 'between:1,480']],
            'sync.auto_interval_seconds' => ['group' => 'sync', 'default' => 60, 'rules' => ['required', 'integer', 'between:15,3600']],
        ], self::$extra);
    }

    /** @return array<string,array{group:string,default:mixed,rules:array}> the keys that apply to platform (true) or foundation (false) settings */
    public static function forScope(bool $platform): array
    {
        return $platform
            ? array_intersect_key(self::all(), array_flip(self::PLATFORM_KEYS))
            : self::all();
    }

    /** @param array<string,array{group:string,default:mixed,rules:array}> $settings */
    public static function register(array $settings): void
    {
        self::$extra = array_merge(self::$extra, $settings);
    }

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::all());
    }

    public static function rules(string $key): array
    {
        return self::all()[$key]['rules'] ?? ['prohibited'];
    }
}
