<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attendance\Employee;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\Leave;
use App\Models\Attendance\Record;
use App\Models\User;
use App\Services\AttendanceCalc as Calc;
use App\Services\AttendanceSamples;
use App\Support\ApiResponse;
use App\Support\Audit;
use App\Support\PermissionCatalog;
use App\Support\PrivateImages;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Office attendance. Everyone with an account linked to an employee clocks in and out with a face photo (the server stamps the date and time).
 * HR (attendance.manage) looks after the employees, corrects records, and sets leaves and holidays. Money (salaries, deductions, payroll)
 * is only sent to people who hold attendance.salary.
 */
class AttendanceController extends Controller
{
    private function salaryOk(Request $r): bool
    {
        return PermissionCatalog::allows($r->user(), 'attendance.salary');
    }

    private function manageOk(Request $r): bool
    {
        return PermissionCatalog::allows($r->user(), 'attendance.manage');
    }

    private function mine(Request $r): ?Employee
    {
        return Employee::where('user_id', $r->user()->id)->where('status', 'active')->first();
    }

    private static function nz(?string $v): ?string
    {
        $v = $v === null ? null : trim($v);

        return $v === '' ? null : $v;
    }

    private static function month(Request $r): string
    {
        $m = (string) $r->query('month', '');

        return preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m) ? $m : Calc::now()->format('Y-m');
    }

    private function emp(Employee $e, Request $r, bool $full = true): array
    {
        $o = [
            'id' => $e->id, 'employee_no' => $e->employee_no, 'name_en' => $e->name_en, 'name_ar' => $e->name_ar, 'position' => $e->position, 'department' => $e->department,
            'status' => $e->status, 'is_sample' => (bool) $e->is_sample, 'has_face' => (bool) $e->face_path, 'linked' => (bool) $e->user_id,
        ];
        if (! $full) {
            return $o;
        }
        $o += [
            'phone' => $e->phone, 'hired_on' => $e->hired_on?->toDateString(), 'shift_start' => $e->shift_start, 'shift_end' => $e->shift_end, 'break_minutes' => (int) $e->break_minutes,
            'grace_minutes' => (int) $e->grace_minutes, 'work_days' => Calc::workDays($e), 'notes' => $e->notes, 'user_id' => $e->user_id, 'user_name' => $e->user?->name,
        ];
        if ($this->salaryOk($r)) {
            $o['monthly_salary'] = (float) $e->monthly_salary;
            $o['currency'] = $e->currency;
        }

        return $o;
    }

    /** A month for one person, with the money only for those who may see it. */
    private function monthFor(Employee $e, string $month, Request $r, ?bool $money = null): array
    {
        $m = Calc::month($e, $month);
        if (! ($money ?? $this->salaryOk($r))) {
            unset($m['pay']);
        }

        return $m;
    }

    private function today(Employee $e): array
    {
        $now = Calc::now();
        $date = $now->toDateString();
        $rec = Record::where('employee_id', $e->id)->where('work_date', $date)->first();

        return Calc::day($e, $date, $rec, Calc::holidays($date, $date), Leave::where('employee_id', $e->id)->where('start_date', '<=', $date)->where('end_date', '>=', $date)->get()->all(), $now);
    }

    /* ------------------------------------------------------------ my day */
    public function me(Request $request)
    {
        $e = $this->mine($request);
        $now = Calc::now();
        if (! $e) {
            return ApiResponse::ok(['employee' => null, 'manage' => $this->manageOk($request), 'now' => $now->toIso8601String(), 'require_photo' => (bool) config('attendance.require_photo')]);
        }

        return ApiResponse::ok([
            'employee' => $this->emp($e, $request, false) + ['shift_start' => $e->shift_start, 'shift_end' => $e->shift_end, 'grace_minutes' => (int) $e->grace_minutes],
            'today' => $this->today($e), 'month' => $this->monthFor($e, $now->format('Y-m'), $request, false),
            'now' => $now->toIso8601String(), 'timezone' => Calc::tz(), 'require_photo' => (bool) config('attendance.require_photo'), 'manage' => $this->manageOk($request),
        ]);
    }

    /** Clock in or out with a face photo. The date and time come from the server, never from the phone. */
    public function clock(Request $request)
    {
        $d = $request->validate(['action' => ['required', 'in:in,out'], 'photo' => [config('attendance.require_photo') ? 'required' : 'nullable', 'file', 'max:'.(int) config('attendance.photo_max_kb', 4096), 'mimes:jpg,jpeg,png,webp']]);
        $e = $this->mine($request);
        if (! $e) {
            return ApiResponse::error('NOT_AN_EMPLOYEE', 'Your account is not linked to an employee yet. Ask HR to link it.', 422);
        }
        $now = Calc::now();
        $date = $now->toDateString();
        $rec = Record::firstOrNew(['employee_id' => $e->id, 'work_date' => $date]);
        if ($d['action'] === 'in' && $rec->clock_in_at) {
            return ApiResponse::error('ALREADY_IN', 'You already clocked in today at '.Calc::local($rec->clock_in_at)->format('g:i A').'.', 409);
        }
        if ($d['action'] === 'out' && (! $rec->clock_in_at || $rec->clock_out_at)) {
            return ApiResponse::error($rec->clock_out_at ? 'ALREADY_OUT' : 'NOT_IN', $rec->clock_out_at ? 'You already clocked out today.' : 'Clock in first.', 409);
        }
        $path = null;
        if ($request->hasFile('photo')) {
            $saved = PrivateImages::store($request->file('photo'), "attendance/{$e->foundation_id}/".$now->format('Y-m'), 640, (int) config('attendance.photo_max_kb', 4096));
            if (is_string($saved)) {
                return ApiResponse::error('BAD_PHOTO', $saved, 422, ['photo' => [$saved]]);
            }
            $path = $saved['path'];
        }
        $holidays = Calc::holidays($date, $date);
        $workday = Calc::isWorkday($e, $now, $holidays);
        $rec->foundation_id = $e->foundation_id;
        $rec->status = 'present';
        if ($d['action'] === 'in') {
            $rec->clock_in_at = $now->copy()->utc();
            $rec->in_photo = $path;
            $rec->in_method = $path ? 'face' : 'manual';
            $rec->late_minutes = Calc::lateMinutes($e, $now, $workday);
        } else {
            $rec->clock_out_at = $now->copy()->utc();
            $rec->out_photo = $path;
            $rec->out_method = $path ? 'face' : 'manual';
            [$rec->early_minutes, $rec->overtime_minutes] = Calc::leaveTimes($e, $now, $workday);
        }
        $rec->save();

        return ApiResponse::created(['today' => $this->today($e), 'now' => $now->toIso8601String()]);
    }

    /* ------------------------------------------------------------ the office today */
    public function board(Request $request)
    {
        $now = Calc::now();
        $date = $now->toDateString();
        $holidays = Calc::holidays($date, $date);
        $recs = Record::where('work_date', $date)->get()->keyBy('employee_id');
        $leaves = Leave::where('start_date', '<=', $date)->where('end_date', '>=', $date)->get()->groupBy('employee_id');
        $rows = Employee::where('status', 'active')->orderBy('name_en')->get()->map(function (Employee $e) use ($recs, $leaves, $holidays, $date, $now, $request) {
            return $this->emp($e, $request, false) + ['day' => Calc::day($e, $date, $recs[$e->id] ?? null, $holidays, ($leaves[$e->id] ?? collect())->all(), $now), 'shift_start' => $e->shift_start];
        })->values();
        $c = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'pending' => 0, 'off' => 0];
        foreach ($rows as $r) {
            $s = $r['day']['status'];
            $k = in_array($s, ['present'], true) ? 'present' : (in_array($s, ['late'], true) ? 'late' : (in_array($s, ['absent'], true) ? 'absent' : (in_array($s, ['leave', 'leave_unpaid', 'excused'], true) ? 'leave' : (in_array($s, ['pending', 'upcoming'], true) ? 'pending' : 'off'))));
            $c[$k]++;
        }

        return ApiResponse::ok(['date' => $date, 'now' => $now->toIso8601String(), 'counts' => $c, 'rows' => $rows, 'samples' => Employee::where('is_sample', true)->count()]);
    }

    /* ------------------------------------------------------------ employees */
    public function employees(Request $request)
    {
        $list = Employee::with('user')->orderByRaw("status = 'active' desc")->orderBy('name_en')->get()->map(fn ($e) => $this->emp($e, $request))->values();
        $taken = Employee::whereNotNull('user_id')->pluck('user_id')->all();
        $users = User::where('foundation_id', $request->user()->foundation_id)->whereIn('role', ['staff', 'president_admin'])->where('active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'taken' => in_array($u->id, $taken, true)])->values();

        return ApiResponse::ok(['employees' => $list, 'users' => $users, 'defaults' => config('attendance.defaults'), 'currency' => config('attendance.currency')]);
    }

    private function empRules(bool $create): array
    {
        $r = $create ? 'required' : 'sometimes';

        return [
            'name_en' => [$r, 'string', 'max:150'], 'name_ar' => ['nullable', 'string', 'max:150'], 'employee_no' => ['nullable', 'string', 'max:30'], 'position' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'], 'phone' => ['nullable', 'string', 'max:40'], 'hired_on' => ['nullable', 'date_format:Y-m-d'], 'monthly_salary' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'shift_start' => ['nullable', 'date_format:H:i'], 'shift_end' => ['nullable', 'date_format:H:i'], 'break_minutes' => ['nullable', 'integer', 'min:0', 'max:240'], 'grace_minutes' => ['nullable', 'integer', 'min:0', 'max:120'],
            'work_days' => ['nullable', 'array'], 'work_days.*' => ['integer', 'between:1,7'], 'user_id' => ['nullable', 'integer'], 'status' => ['nullable', 'in:active,ended'], 'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function fill(Employee $e, array $d, Request $request): ?\Illuminate\Http\JsonResponse
    {
        foreach (['name_en', 'name_ar', 'employee_no', 'position', 'department', 'phone', 'notes'] as $k) {
            if (array_key_exists($k, $d)) {
                $e->$k = $k === 'name_en' ? trim((string) $d[$k]) : self::nz($d[$k]);
            }
        }
        foreach (['hired_on', 'shift_start', 'shift_end', 'break_minutes', 'grace_minutes', 'status'] as $k) {
            if (array_key_exists($k, $d) && $d[$k] !== null) {
                $e->$k = $d[$k];
            }
        }
        if (array_key_exists('hired_on', $d) && $d['hired_on'] === null) {
            $e->hired_on = null;
        }
        if (array_key_exists('monthly_salary', $d) && $this->salaryOk($request)) {
            $e->monthly_salary = (float) ($d['monthly_salary'] ?? 0);
        }
        if (array_key_exists('work_days', $d) && $d['work_days']) {
            $e->work_days = implode(',', array_values(array_unique(array_map('intval', $d['work_days']))));
        }
        if (Calc::mins($e->shift_end ?: '17:00') <= Calc::mins($e->shift_start ?: '08:00')) {
            return ApiResponse::error('INVALID_SHIFT', 'The shift must end after it starts.', 422, ['shift_end' => ['The shift must end after it starts.']]);
        }
        if (array_key_exists('user_id', $d)) {
            if ($d['user_id']) {
                $u = User::where('id', $d['user_id'])->where('foundation_id', $request->user()->foundation_id)->whereIn('role', ['staff', 'president_admin'])->first();
                if (! $u) {
                    return ApiResponse::error('INVALID_USER', 'Choose an office account.', 422, ['user_id' => ['Choose an office account.']]);
                }
                if (Employee::where('user_id', $u->id)->where('id', '!=', $e->id ?? 0)->exists()) {
                    return ApiResponse::error('USER_TAKEN', 'That account is already linked to another employee.', 422, ['user_id' => ['That account is already linked to another employee.']]);
                }
                $e->user_id = $u->id;
            } else {
                $e->user_id = null;
            }
        }

        return null;
    }

    public function storeEmployee(Request $request)
    {
        $d = $request->validate($this->empRules(true));
        $e = new Employee(config('attendance.defaults') + ['currency' => config('attendance.currency')]);
        $e->work_days = config('attendance.defaults.work_days');
        if ($err = $this->fill($e, $d, $request)) {
            return $err;
        }
        $e->foundation_id = $request->user()->foundation_id;
        $e->hired_on ??= Calc::now()->toDateString();   // no hire date given: counted from today, never absent for days before they joined
        $e->save();
        Audit::record('attendance.employee_added', $request, 'att_employee', $e->id);

        return ApiResponse::created($this->emp($e->load('user'), $request));
    }

    public function updateEmployee(Request $request, Employee $employee)
    {
        $d = $request->validate($this->empRules(false));
        if ($err = $this->fill($employee, $d, $request)) {
            return $err;
        }
        $employee->save();
        Audit::record('attendance.employee_changed', $request, 'att_employee', $employee->id, ['fields' => array_keys($d)]);

        return ApiResponse::updated($this->emp($employee->load('user'), $request));
    }

    /** The reference face photo: HR keeps it so each clock-in photo can be compared with it. */
    public function enrollFace(Request $request, Employee $employee)
    {
        $request->validate(['photo' => ['required', 'file', 'max:'.(int) config('attendance.photo_max_kb', 4096), 'mimes:jpg,jpeg,png,webp']]);
        $saved = PrivateImages::store($request->file('photo'), "attendance/{$employee->foundation_id}/faces", 640, (int) config('attendance.photo_max_kb', 4096));
        if (is_string($saved)) {
            return ApiResponse::error('BAD_PHOTO', $saved, 422, ['photo' => [$saved]]);
        }
        if ($employee->face_path) {
            Storage::disk('local')->delete($employee->face_path);
        }
        $employee->update(['face_path' => $saved['path']]);
        Audit::record('attendance.face_enrolled', $request, 'att_employee', $employee->id);

        return ApiResponse::updated(['has_face' => true]);
    }

    public function face(Request $request, Employee $employee)
    {
        return ApplicationController::file($employee->face_path, 'image/jpeg');
    }

    /* ------------------------------------------------------------ records */
    public function records(Request $request)
    {
        $month = self::month($request);
        if ($this->manageOk($request)) {
            $e = Employee::find((int) $request->query('employee_id'));
        } else {
            $e = $this->mine($request);
        }
        if (! $e) {
            return ApiResponse::notFound('No such employee.');
        }

        return ApiResponse::ok(['employee' => $this->emp($e, $request, false)] + $this->monthFor($e, $month, $request, $this->manageOk($request) ? null : false));
    }

    /** HR corrects a day: set the times, or mark it absent or excused. A note saying why is required, and the change is logged. */
    public function setRecord(Request $request)
    {
        $d = $request->validate([
            'employee_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d'], 'status' => ['required', 'in:present,absent,excused,clear'],
            'clock_in' => ['nullable', 'date_format:H:i'], 'clock_out' => ['nullable', 'date_format:H:i'], 'note' => ['required', 'string', 'min:3', 'max:300'],
        ]);
        $e = Employee::find($d['employee_id']);
        if (! $e) {
            return ApiResponse::notFound('No such employee.');
        }
        if ($d['date'] > Calc::now()->toDateString()) {
            return ApiResponse::error('FUTURE_DATE', 'That day has not come yet.', 422, ['date' => ['That day has not come yet.']]);
        }
        $rec = Record::firstOrNew(['employee_id' => $e->id, 'work_date' => $d['date']]);
        $rec->foundation_id = $e->foundation_id;
        $day = Carbon::parse($d['date'], Calc::tz());
        $workday = Calc::isWorkday($e, $day, Calc::holidays($d['date'], $d['date']));
        if ($d['status'] === 'clear') {
            if ($rec->exists) {
                $rec->delete();
            }
            Audit::record('attendance.record_cleared', $request, 'att_employee', $e->id, ['date' => $d['date']]);

            return ApiResponse::updated(['cleared' => true]);
        }
        $rec->status = $d['status'];
        $rec->clock_in_at = $rec->clock_out_at = null;
        $rec->late_minutes = $rec->early_minutes = $rec->overtime_minutes = 0;
        if ($d['status'] === 'present') {
            if (empty($d['clock_in'])) {
                return ApiResponse::error('NEEDS_TIME', 'Enter the time the person came in.', 422, ['clock_in' => ['Enter the time the person came in.']]);
            }
            $in = Carbon::parse($d['date'].' '.$d['clock_in'], Calc::tz());
            $rec->clock_in_at = $in->copy()->utc();
            $rec->in_method = $rec->in_method ?: 'manual';
            $rec->late_minutes = Calc::lateMinutes($e, $in, $workday);
            if (! empty($d['clock_out'])) {
                $out = Carbon::parse($d['date'].' '.$d['clock_out'], Calc::tz());
                if ($out->lessThanOrEqualTo($in)) {
                    return ApiResponse::error('INVALID_TIMES', 'Clock out must be after clock in.', 422, ['clock_out' => ['Clock out must be after clock in.']]);
                }
                $rec->clock_out_at = $out->copy()->utc();
                $rec->out_method = $rec->out_method ?: 'manual';
                [$rec->early_minutes, $rec->overtime_minutes] = Calc::leaveTimes($e, $out, $workday);
            }
        }
        $rec->note = $d['note'];
        $rec->edited_by = $request->user()->id;
        $rec->save();
        Audit::record('attendance.record_edited', $request, 'att_employee', $e->id, ['date' => $d['date'], 'status' => $d['status']]);

        return ApiResponse::updated(['day' => Calc::day($e, $d['date'], $rec, Calc::holidays($d['date'], $d['date']), [], Calc::now())]);
    }

    public function photo(Request $request, Record $record, string $which)
    {
        if (! in_array($which, ['in', 'out'], true)) {
            return ApiResponse::notFound('No such photo.');
        }
        $own = $record->employee && $record->employee->user_id === $request->user()->id;
        if (! $own && ! $this->manageOk($request)) {
            return ApiResponse::forbidden();
        }

        return ApplicationController::file($which === 'in' ? $record->in_photo : $record->out_photo, 'image/jpeg');
    }

    /* ------------------------------------------------------------ payroll */
    public function payroll(Request $request)
    {
        $month = self::month($request);
        $rows = [];
        $sum = ['gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0];
        foreach (Employee::where('status', 'active')->orderBy('name_en')->get() as $e) {
            $m = Calc::month($e, $month);
            $rows[] = ['employee' => $this->emp($e, $request, false), 'working_days' => $m['working_days'], 'totals' => $m['totals'], 'pay' => $m['pay']];
            $sum['gross'] += $m['pay']['gross'];
            $sum['deductions'] += $m['pay']['total_deductions'];
            $sum['net'] += $m['pay']['net'];
        }

        return ApiResponse::ok(['month' => $month, 'currency' => config('attendance.currency'), 'rows' => $rows, 'sum' => array_map(fn ($v) => round($v, 2), $sum)]);
    }

    /* ------------------------------------------------------------ leaves and holidays */
    public function leaves(Request $request)
    {
        $month = self::month($request);
        $first = Carbon::parse($month.'-01');
        $from = $first->copy()->subMonths(1)->toDateString();
        $to = $first->copy()->endOfMonth()->addMonths(1)->toDateString();
        $rows = Leave::where('start_date', '<=', $to)->where('end_date', '>=', $from)->orderByDesc('start_date')->get()->map(fn ($l) => [
            'id' => $l->id, 'employee_id' => $l->employee_id, 'start_date' => $l->start_date->toDateString(), 'end_date' => $l->end_date->toDateString(), 'kind' => $l->kind, 'paid' => (bool) $l->paid, 'reason' => $l->reason, 'is_sample' => (bool) $l->is_sample,
        ])->values();

        return ApiResponse::ok($rows);
    }

    public function storeLeave(Request $request)
    {
        $d = $request->validate(['employee_id' => ['required', 'integer'], 'start_date' => ['required', 'date_format:Y-m-d'], 'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'kind' => ['required', 'in:annual,sick,emergency,unpaid,other'], 'reason' => ['nullable', 'string', 'max:300']]);
        $e = Employee::find($d['employee_id']);
        if (! $e) {
            return ApiResponse::notFound('No such employee.');
        }
        if (Carbon::parse($d['start_date'])->diffInDays(Carbon::parse($d['end_date'])) > 60) {
            return ApiResponse::error('TOO_LONG', 'A leave can be at most 60 days. Add it in parts.', 422, ['end_date' => ['A leave can be at most 60 days. Add it in parts.']]);
        }
        $l = Leave::create(['foundation_id' => $e->foundation_id, 'employee_id' => $e->id, 'start_date' => $d['start_date'], 'end_date' => $d['end_date'], 'kind' => $d['kind'], 'paid' => $d['kind'] !== 'unpaid', 'reason' => self::nz($d['reason'] ?? null), 'created_by' => $request->user()->id]);
        Audit::record('attendance.leave_added', $request, 'att_employee', $e->id, ['kind' => $d['kind'], 'from' => $d['start_date'], 'to' => $d['end_date']]);

        return ApiResponse::created(['id' => $l->id]);
    }

    public function deleteLeave(Request $request, Leave $leave)
    {
        Audit::record('attendance.leave_removed', $request, 'att_employee', $leave->employee_id);
        $leave->delete();

        return ApiResponse::deleted('Leave removed.');
    }

    public function holidays(Request $request)
    {
        $year = (int) $request->query('year', Calc::now()->format('Y'));
        $rows = Holiday::whereBetween('holiday_date', ["$year-01-01", "$year-12-31"])->orderBy('holiday_date')->get()->map(fn ($h) => ['id' => $h->id, 'date' => $h->holiday_date->toDateString(), 'name_en' => $h->name_en, 'name_ar' => $h->name_ar, 'is_sample' => (bool) $h->is_sample])->values();

        return ApiResponse::ok($rows);
    }

    public function storeHoliday(Request $request)
    {
        $d = $request->validate(['date' => ['required', 'date_format:Y-m-d'], 'name_en' => ['required', 'string', 'max:100'], 'name_ar' => ['nullable', 'string', 'max:100']]);
        if (Holiday::where('holiday_date', $d['date'])->exists()) {
            return ApiResponse::error('EXISTS', 'That date is already a holiday.', 422, ['date' => ['That date is already a holiday.']]);
        }
        $h = Holiday::create(['foundation_id' => $request->user()->foundation_id, 'holiday_date' => $d['date'], 'name_en' => trim($d['name_en']), 'name_ar' => self::nz($d['name_ar'] ?? null)]);
        Audit::record('attendance.holiday_added', $request, 'att_holiday', $h->id, ['date' => $d['date']]);

        return ApiResponse::created(['id' => $h->id]);
    }

    public function deleteHoliday(Request $request, Holiday $holiday)
    {
        Audit::record('attendance.holiday_removed', $request, 'att_holiday', $holiday->id);
        $holiday->delete();

        return ApiResponse::deleted('Holiday removed.');
    }

    /* ------------------------------------------------------------ sample data */
    public function loadSamples(Request $request)
    {
        $n = AttendanceSamples::load($request->user()->foundation_id, $request->user()->id, $request->user()->name);
        Audit::record('attendance.samples_loaded', $request, null, null, ['employees' => $n]);

        return ApiResponse::created(['employees' => $n]);
    }

    public function removeSamples(Request $request)
    {
        $n = AttendanceSamples::remove();
        Audit::record('attendance.samples_removed', $request, null, null, ['employees' => $n]);

        return ApiResponse::ok(['removed' => $n]);
    }
}
