<?php

namespace App\Services;

use App\Models\WorkActivity;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Records how staff use the app: per day, when they were active, for how long, and how many things they looked at and did,
 * by part of the app. It stores counts and times only. Background checks the app makes by itself (new messages, the bell) are not activity.
 */
final class WorkTracker
{
    /** An idle gap longer than this ends a stretch of activity and is not counted as working time. */
    public const GAP = 300;

    private const IGNORE = ['api/notices/count', 'api/chat/unread', 'api/chat/conversations'];

    /** Part of the app a request belongs to, from its address. */
    public static function area(string $path): string
    {
        $p = explode('/', trim(preg_replace('#^api/#', '', $path), '/'))[0] ?? '';

        return match ($p) {
            'children', 'applications' => 'orphans',
            'payments', 'reports' => 'finance',
            'projects' => 'projects',
            'edu' => 'education',
            'attendance' => 'attendance',
            'work' => 'attendance',
            'chat' => 'chat',
            'site' => 'website',
            'users', 'audit' => 'accounts',
            default => 'other',
        };
    }

    public static function counts(Request $request, int $status): bool
    {
        if ($status >= 400) {
            return false;
        }
        $path = $request->path();
        if ($request->isMethod('GET') && (in_array($path, self::IGNORE, true) || $request->query('after_id') !== null)) {
            return false;
        }

        return true;
    }

    public static function record(int $userId, int $foundationId, string $path, bool $write, ?Carbon $now = null): void
    {
        $now ??= Carbon::now();
        $day = $now->copy()->setTimezone(AttendanceCalc::tz())->toDateString();
        $area = self::area($path);

        DB::transaction(function () use ($userId, $foundationId, $day, $now, $area, $write) {
            $row = WorkActivity::withoutGlobalScopes()->where('user_id', $userId)->where('day', $day)->lockForUpdate()->first();
            if (! $row) {
                WorkActivity::withoutGlobalScopes()->create([
                    'foundation_id' => $foundationId, 'user_id' => $userId, 'day' => $day, 'first_at' => $now->copy()->utc(), 'last_at' => $now->copy()->utc(),
                    'requests' => 1, 'views' => $write ? 0 : 1, 'actions' => $write ? 1 : 0, 'areas' => [$area => 1],
                ]);

                return;
            }
            $gap = $row->last_at ? max(0, $now->getTimestamp() - $row->last_at->getTimestamp()) : self::GAP + 1;
            $areas = $row->areas ?? [];
            $areas[$area] = ($areas[$area] ?? 0) + 1;
            $row->forceFill([
                'last_at' => $now->copy()->utc(), 'requests' => $row->requests + 1, 'views' => $row->views + ($write ? 0 : 1), 'actions' => $row->actions + ($write ? 1 : 0),
                'active_seconds' => $row->active_seconds + ($gap <= self::GAP ? $gap : 0), 'areas' => $areas,
            ])->save();
        });
    }
}
