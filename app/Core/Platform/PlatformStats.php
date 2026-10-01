<?php

namespace App\Core\Platform;

use App\Models\AuditLog;
use App\Models\Foundation;
use App\Models\SystemState;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Numbers for the platform dashboard. COUNTS ONLY: the platform runs the service, it does not read the foundations'
 * records, so nothing about individual people, families or documents is ever selected here.
 */
class PlatformStats
{
    public function __construct(private TenantContext $tenant) {}

    public function summary(): array
    {
        return $this->tenant->asSystem(fn () => [
            'foundations' => $this->foundationCounts(),
            'totals' => [
                'users' => (int) DB::table('users')->whereNotNull('foundation_id')->whereNull('deleted_at')->count(),
                'active_users' => (int) DB::table('users')->whereNotNull('foundation_id')->whereNull('deleted_at')->where('status', 'active')->count(),
                'platform_admins' => (int) DB::table('users')->whereNull('foundation_id')->whereNull('deleted_at')->count(),
                'programs' => (int) DB::table('programs')->whereNull('deleted_at')->count(),
                'active_programs' => (int) DB::table('programs')->whereNull('deleted_at')->where('status', 'active')->count(),
                'organizations' => (int) DB::table('organizations')->whereNull('deleted_at')->count(),
            ],
            'storage' => ['documents' => (int) DB::table('documents')->whereNull('deleted_at')->count(), 'bytes' => (int) DB::table('documents')->whereNull('deleted_at')->sum('size')],
            'system' => $this->system(),
        ]) + ['recent_activity' => $this->recentActivity()];
    }

    private function foundationCounts(): array
    {
        $by = DB::table('foundations')->whereNull('deleted_at')->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status');

        return [
            'total' => (int) $by->sum(),
            'active' => (int) ($by[Foundation::ACTIVE] ?? 0),
            'inactive' => (int) ($by[Foundation::INACTIVE] ?? 0),
            'suspended' => (int) ($by[Foundation::SUSPENDED] ?? 0),
        ];
    }

    private function system(): array
    {
        $healthy = true;
        try {
            DB::connection()->getPdo();
        } catch (\Throwable) {
            $healthy = false;
        }
        $pending = 0;
        try {
            $ran = Schema::hasTable('migrations') ? DB::table('migrations')->pluck('migration')->all() : [];
            $pending = count(array_diff(array_map(fn ($f) => basename($f, '.php'), glob(database_path('migrations/*.php'))), $ran));
        } catch (\Throwable) {
        }
        $dir = storage_path();

        return [
            'status' => $healthy && $pending === 0 && is_writable($dir) ? 'ok' : 'attention',
            'version' => config('foundation.version'),
            'schema_version' => (int) SystemState::get('schema_version', 0),
            'database' => DB::connection()->getDriverName(),
            'pending_migrations' => $pending,
            'storage_writable' => is_writable($dir),
            'server_time' => now()->toIso8601String(),
        ];
    }

    /** Platform-level events only (the platform scope hides every foundation's own activity). */
    private function recentActivity(): array
    {
        return AuditLog::query()->orderByDesc('occurred_at')->limit(10)->get()
            ->map(fn (AuditLog $l) => ['id' => $l->id, 'occurred_at' => $l->occurred_at?->toIso8601String(), 'action' => $l->action, 'summary' => $l->summary, 'user_name' => $l->user_name])
            ->all();
    }
}
