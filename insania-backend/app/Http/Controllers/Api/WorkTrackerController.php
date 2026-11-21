<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance\Employee;
use App\Models\Attendance\Record;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\WorkActivity;
use App\Services\AttendanceCalc as Calc;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\AuditDescriber;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * The work tracker for HR and the President: how each staff member uses the app. Counts and times only (see App\Services\WorkTracker).
 */
class WorkTrackerController extends Controller
{
    private function staff(Request $request)
    {
        return User::where('foundation_id', $request->user()->foundation_id)->where('role', 'staff')->where('active', true)->orderBy('name')->get();
    }

    /** [from, to] dates for ?range=today|week|month (&month=YYYY-MM). */
    private function range(Request $request): array
    {
        $now = Calc::now();
        $r = (string) $request->query('range', 'today');
        if ($r === 'month') {
            $m = (string) $request->query('month', '');
            $first = Carbon::parse((preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : $now->format('Y-m')).'-01', Calc::tz());

            return ['month', $first->toDateString(), $first->copy()->endOfMonth()->toDateString()];
        }
        if ($r === 'week') {
            return ['week', $now->copy()->subDays(6)->toDateString(), $now->toDateString()];
        }

        return ['today', $now->toDateString(), $now->toDateString()];
    }

    private static function status(?Carbon $last, Carbon $now): string
    {
        if (! $last) {
            return 'never';
        }
        $mins = $last->diffInMinutes($now);

        return $mins <= 5 ? 'active' : ($mins <= 60 ? 'recent' : 'away');
    }

    public function summary(Request $request)
    {
        [$range, $from, $to] = $this->range($request);
        $now = Calc::now();
        $today = $now->toDateString();
        $staff = $this->staff($request);
        $ids = $staff->pluck('id');

        $rows = WorkActivity::whereIn('user_id', $ids)->whereBetween('day', [$from, $to])->get()->groupBy('user_id');
        $last = WorkActivity::whereIn('user_id', $ids)->where('day', '>=', $now->copy()->subDays(60)->toDateString())->selectRaw('user_id, max(last_at) as last_at')->groupBy('user_id')->pluck('last_at', 'user_id');
        $emps = Employee::whereIn('user_id', $ids)->get()->keyBy('user_id');
        $clock = Record::whereIn('employee_id', $emps->pluck('id'))->whereBetween('work_date', [$from, $to])->whereNotNull('clock_in_at')->get()->groupBy('employee_id');
        $todayRec = Record::whereIn('employee_id', $emps->pluck('id'))->where('work_date', $today)->get()->keyBy('employee_id');

        $out = [];
        $tot = ['active_now' => 0, 'active_today' => 0, 'idle_clocked_in' => 0, 'actions' => 0, 'active_seconds' => 0];
        foreach ($staff as $u) {
            $mine = $rows->get($u->id, collect());
            $lastAt = isset($last[$u->id]) ? Carbon::parse($last[$u->id], 'UTC')->setTimezone(Calc::tz()) : null;
            $status = self::status($lastAt, $now);
            $areas = [];
            foreach ($mine as $r) {
                foreach ((array) $r->areas as $a => $n) {
                    $areas[$a] = ($areas[$a] ?? 0) + $n;
                }
            }
            arsort($areas);
            $emp = $emps->get($u->id);
            $rec = $emp ? $todayRec->get($emp->id) : null;
            $clockedNow = (bool) ($rec && $rec->clock_in_at && ! $rec->clock_out_at);
            $todayRow = $mine->first(fn ($r) => $r->day->toDateString() === $today);
            $idle = $clockedNow && (! $lastAt || $lastAt->diffInMinutes($now) > 30);
            $o = [
                'id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'avatar' => $u->avatar_v, 'department' => $u->department_key,
                'last_seen_at' => $lastAt?->toIso8601String(), 'status' => $status,
                'days_active' => $mine->where('requests', '>', 0)->count(), 'active_seconds' => (int) $mine->sum('active_seconds'),
                'views' => (int) $mine->sum('views'), 'actions' => (int) $mine->sum('actions'), 'areas' => array_slice($areas, 0, 4, true),
                'linked' => (bool) $emp, 'clocked_days' => $emp ? $clock->get($emp->id, collect())->count() : null,
                'clocked_in_now' => $clockedNow, 'clock_in' => $rec && $rec->clock_in_at ? Calc::local($rec->clock_in_at)->format('H:i') : null,
                'idle_while_clocked_in' => $idle, 'today_seconds' => (int) ($todayRow?->active_seconds ?? 0),
            ];
            $out[] = $o;
            $tot['active_now'] += $status === 'active' ? 1 : 0;
            $tot['active_today'] += $todayRow && $todayRow->requests > 0 ? 1 : 0;
            $tot['idle_clocked_in'] += $idle ? 1 : 0;
            $tot['actions'] += $o['actions'];
            $tot['active_seconds'] += $o['active_seconds'];
        }

        return ApiResponse::ok(['range' => $range, 'from' => $from, 'to' => $to, 'now' => $now->toIso8601String(), 'totals' => $tot, 'staff' => $out,
            'samples' => WorkActivity::where('is_sample', true)->exists(), 'timezone' => Calc::tz()]);
    }

    public function staffDetail(Request $request, User $user)
    {
        if ($user->foundation_id !== $request->user()->foundation_id || $user->role !== 'staff') {
            return ApiResponse::notFound('No such staff member.');
        }
        $now = Calc::now();
        $m = (string) $request->query('month', '');
        $first = Carbon::parse((preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : $now->format('Y-m')).'-01', Calc::tz());
        $from = $first->toDateString();
        $to = $first->copy()->endOfMonth()->toDateString();
        $rows = WorkActivity::where('user_id', $user->id)->whereBetween('day', [$from, $to])->get()->keyBy(fn ($r) => $r->day->toDateString());
        $emp = Employee::where('user_id', $user->id)->first();
        $recs = $emp ? Record::where('employee_id', $emp->id)->whereBetween('work_date', [$from, $to])->get()->keyBy(fn ($r) => $r->work_date->toDateString()) : collect();

        $days = [];
        $areas = [];
        for ($d = $first->copy(); $d->toDateString() <= $to; $d->addDay()) {
            $date = $d->toDateString();
            $r = $rows->get($date);
            $c = $recs->get($date);
            foreach ((array) ($r?->areas ?? []) as $a => $n) {
                $areas[$a] = ($areas[$a] ?? 0) + $n;
            }
            $days[] = [
                'date' => $date, 'weekday' => $d->dayOfWeekIso, 'active_seconds' => (int) ($r?->active_seconds ?? 0), 'requests' => (int) ($r?->requests ?? 0), 'views' => (int) ($r?->views ?? 0), 'actions' => (int) ($r?->actions ?? 0),
                'first' => $r?->first_at ? Calc::local($r->first_at)->format('H:i') : null, 'last' => $r?->last_at ? Calc::local($r->last_at)->format('H:i') : null,
                'clock_in' => $c && $c->clock_in_at ? Calc::local($c->clock_in_at)->format('H:i') : null, 'clock_out' => $c && $c->clock_out_at ? Calc::local($c->clock_out_at)->format('H:i') : null,
            ];
        }
        arsort($areas);

        $logs = AuditLog::where('user_id', $user->id)->whereNotIn('action', AuditDescriber::actionsIn('security'))->latest('id')->limit(30)->get();
        $recent = collect(AuditDescriber::many($logs))->map(fn ($l) => ['at' => $l['at'], 'area' => $l['area'], 'text' => $l['text'], 'subject' => $l['subject']])->values();

        return ApiResponse::ok([
            'user' => ['id' => $user->id, 'name' => $user->name, 'username' => $user->username, 'avatar' => $user->avatar_v, 'department' => $user->department_key], 'month' => $first->format('Y-m'),
            'days' => $days, 'areas' => $areas, 'recent' => $recent, 'linked' => (bool) $emp,
            'totals' => ['active_seconds' => array_sum(array_column($days, 'active_seconds')), 'actions' => array_sum(array_column($days, 'actions')), 'views' => array_sum(array_column($days, 'views')), 'days_active' => count(array_filter($days, fn ($d) => $d['requests'] > 0))],
        ]);
    }

    /** Sample activity for the staff accounts of the last two weeks, so the screens can be tried. Marked as samples and removable. */
    public function loadSamples(Request $request)
    {
        $fid = $request->user()->foundation_id;
        $now = Calc::now();
        $made = 0;
        foreach ($this->staff($request) as $i => $u) {
            mt_srand(500 + $u->id);
            for ($back = 14; $back >= 1; $back--) {
                $d = $now->copy()->subDays($back);
                if ($d->dayOfWeekIso > 5 || WorkActivity::where('user_id', $u->id)->where('day', $d->toDateString())->exists()) {
                    continue;
                }
                if (mt_rand(1, 100) <= 8) {
                    continue;   // a day off
                }
                $busy = 0.4 + (($u->id * 7) % 10) / 14;
                $secs = (int) (mt_rand(90, 400) * 60 * $busy);
                $start = $d->copy()->startOfDay()->addMinutes(480 + mt_rand(-10, 50));
                $areas = ['orphans' => mt_rand(5, 60), 'finance' => mt_rand(0, 25), 'chat' => mt_rand(2, 30), 'attendance' => mt_rand(1, 6), 'projects' => mt_rand(0, 8)];
                $actions = (int) (array_sum($areas) * mt_rand(12, 30) / 100);
                WorkActivity::withoutGlobalScopes()->create([
                    'foundation_id' => $fid, 'user_id' => $u->id, 'day' => $d->toDateString(), 'first_at' => $start->copy()->utc(), 'last_at' => $start->copy()->addSeconds($secs + mt_rand(600, 4000))->utc(),
                    'active_seconds' => $secs, 'requests' => array_sum($areas), 'views' => array_sum($areas) - $actions, 'actions' => $actions, 'areas' => array_filter($areas), 'is_sample' => true,
                ]);
                $made++;
            }
        }
        Audit::record('work.samples_loaded', $request, null, null, ['days' => $made]);

        return ApiResponse::created(['days' => $made]);
    }

    public function removeSamples(Request $request)
    {
        $n = WorkActivity::where('is_sample', true)->delete();
        Audit::record('work.samples_removed', $request, null, null, ['days' => $n]);

        return ApiResponse::ok(['removed' => $n]);
    }
}
