<?php

namespace App\Core;

use App\Models\AuditLog;
use App\Models\Device;
use App\Models\Location;
use App\Models\SyncChange;
use App\Models\SyncConflict;
use App\Models\SystemState;
use App\Models\User;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard architecture: a list of card providers. Phase 1 registers only the
 * system cards; a module (Aytam, Donations...) calls DashboardService::extend()
 * from its provider to add its own cards without touching this file.
 */
class DashboardService
{
    /** Future modules the dashboard will show; "available" flips when a module registers cards. */
    public const PLANNED_MODULES = ['aytam', 'beneficiaries', 'relief', 'donations', 'projects', 'inventory', 'volunteers', 'reports'];

    /** @var array<string,Closure(User):?array> */
    private static array $providers = [];

    public static function extend(string $key, Closure $provider): void
    {
        self::$providers[$key] = $provider;
    }

    public function summary(User $user): array
    {
        $cards = [];
        foreach (self::$providers as $key => $provider) {
            if ($card = $provider($user)) {
                $cards[$key] = $card;
            }
        }

        return [
            'system' => $this->system(),
            'sync' => $this->sync(),
            'counts' => $this->counts($user),
            'cards' => $cards,
            'modules' => collect(self::PLANNED_MODULES)->mapWithKeys(fn ($m) => [$m => ['available' => isset($cards[$m])]])->all(),
        ];
    }

    private function system(): array
    {
        return [
            'version' => config('foundation.version'),
            'schema_version' => (int) SystemState::get('schema_version', 0),
            'installed_at' => SystemState::get('installed_at'),
            'deployment_model' => app(Settings\SettingsService::class)->get('deployment.model'),
            'database' => DB::connection()->getDriverName(),
            'display_timezone' => app(Settings\SettingsService::class)->get('app.timezone'),
            'storage_timezone' => 'UTC',
            'server_time' => now()->toIso8601String(),
        ];
    }

    private function sync(): array
    {
        $window = now()->subMinutes((int) config('foundation.device.online_window_minutes'));

        return [
            'latest_seq' => (int) SyncChange::query()->max('seq'),
            'open_conflicts' => SyncConflict::query()->where('status', 'open')->count(),
            'devices' => [
                'total' => Device::query()->whereNull('revoked_at')->count(),
                'online' => Device::query()->whereNull('revoked_at')->where('last_seen_at', '>=', $window)->count(),
                'never_synced' => Device::query()->whereNull('revoked_at')->whereNull('last_seen_at')->count(),
            ],
        ];
    }

    private function counts(User $user): array
    {
        $counts = [];
        if ($user->hasPermission('users.view')) {
            $counts['users'] = User::query()->where('status', 'active')->count();
        }
        if ($user->hasPermission('locations.view')) {
            $counts['locations'] = Location::query()->count();
        }
        if ($user->hasPermission('audit.view')) {
            $counts['audit_events_today'] = AuditLog::query()->where('occurred_at', '>=', now()->startOfDay())->count();
        }

        return $counts;
    }
}
