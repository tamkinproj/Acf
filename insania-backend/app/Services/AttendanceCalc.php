<?php

namespace App\Services;

use App\Models\Attendance\Employee;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\Leave;
use App\Models\Attendance\Record;
use Carbon\Carbon;

/**
 * The attendance rules in one place.
 *
 *   late        minutes after the shift start, counted from the start once the grace period has passed
 *   early       minutes the person left before the shift end
 *   absent      a working day (not a rest day, holiday or leave) with no clock in
 *   pay         daily rate = monthly salary / working days of the month; minute rate = daily rate / paid minutes of the shift.
 *               Late and early minutes cost the minute rate, an absent day and an unpaid leave day cost the daily rate.
 */
final class AttendanceCalc
{
    public static function tz(): string
    {
        return (string) config('attendance.timezone', 'Asia/Manila');
    }

    public static function now(): Carbon
    {
        return Carbon::now(self::tz());
    }

    public static function mins(string $hhmm): int
    {
        $p = explode(':', substr($hhmm, 0, 5));

        return ((int) $p[0]) * 60 + (int) ($p[1] ?? 0);
    }

    public static function workDays(Employee $e): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string) $e->work_days))));
    }

    /** Minutes the shift pays for: start to end, less the unpaid break. */
    public static function paidMinutes(Employee $e): int
    {
        return max(60, self::mins($e->shift_end) - self::mins($e->shift_start) - (int) $e->break_minutes);
    }

    public static function local($dt): ?Carbon
    {
        return $dt ? Carbon::parse($dt)->setTimezone(self::tz()) : null;
    }

    public static function minuteOfDay(Carbon $t): int
    {
        return $t->hour * 60 + $t->minute;
    }

    public static function isWorkday(Employee $e, Carbon $d, array $holidays): bool
    {
        return in_array($d->dayOfWeekIso, self::workDays($e), true) && ! isset($holidays[$d->toDateString()]);
    }

    public static function lateMinutes(Employee $e, Carbon $in, bool $workday): int
    {
        if (! $workday) {
            return 0;
        }
        $late = self::minuteOfDay($in) - self::mins($e->shift_start);

        return $late > (int) $e->grace_minutes ? $late : 0;
    }

    /** @return array{0:int,1:int} early minutes, overtime minutes */
    public static function leaveTimes(Employee $e, Carbon $out, bool $workday): array
    {
        $diff = self::minuteOfDay($out) - self::mins($e->shift_end);

        return [$workday && $diff < 0 ? -$diff : 0, $diff > 0 ? $diff : 0];
    }

    /** Holidays of a date range, keyed by date. */
    public static function holidays(string $from, string $to): array
    {
        return Holiday::whereBetween('holiday_date', [$from, $to])->get()->mapWithKeys(fn ($h) => [$h->holiday_date->toDateString() => $h->name_en])->all();
    }

    public static function leaveFor(array $leaves, string $date): ?Leave
    {
        foreach ($leaves as $l) {
            if ($l->start_date->toDateString() <= $date && $l->end_date->toDateString() >= $date) {
                return $l;
            }
        }

        return null;
    }

    /** What happened (or should have happened) on one day. */
    public static function day(Employee $e, string $date, ?Record $r, array $holidays, array $leaves, ?Carbon $now = null): array
    {
        $now ??= self::now();
        $d = Carbon::parse($date, self::tz());
        $workday = self::isWorkday($e, $d, $holidays);
        $in = self::local($r?->clock_in_at);
        $out = self::local($r?->clock_out_at);
        $leave = self::leaveFor($leaves, $date);
        $out_row = [
            'date' => $date, 'weekday' => $d->dayOfWeekIso, 'workday' => $workday, 'status' => 'upcoming',
            'holiday' => $holidays[$date] ?? null, 'leave' => $leave ? ['kind' => $leave->kind, 'paid' => (bool) $leave->paid, 'reason' => $leave->reason] : null,
            'clock_in' => $in?->format('H:i'), 'clock_out' => $out?->format('H:i'),
            'clock_in_at' => $in?->toIso8601String(), 'clock_out_at' => $out?->toIso8601String(),
            'late' => 0, 'early' => 0, 'overtime' => 0, 'worked' => 0,
            'record_id' => $r?->id, 'in_photo' => (bool) ($r && $r->in_photo), 'out_photo' => (bool) ($r && $r->out_photo),
            'in_method' => $r?->in_method, 'out_method' => $r?->out_method, 'note' => $r?->note, 'no_clock_out' => false,
        ];
        if ($e->hired_on && $date < $e->hired_on->toDateString()) {
            return ['status' => 'before'] + $out_row;
        }
        if ($r && $r->status === 'absent' && ! $in) {
            return ['status' => 'absent'] + $out_row;
        }
        if ($in) {
            $out_row['late'] = (int) $r->late_minutes;
            $out_row['early'] = (int) $r->early_minutes;
            $out_row['overtime'] = (int) $r->overtime_minutes;
            $out_row['worked'] = $out ? max(0, (int) round(($out->getTimestamp() - $in->getTimestamp()) / 60) - ($workday ? (int) $e->break_minutes : 0)) : 0;
            $out_row['no_clock_out'] = ! $out && $date < $now->toDateString();

            return ['status' => $out_row['late'] > 0 ? 'late' : 'present'] + $out_row;
        }
        if ($r && $r->status === 'excused') {
            return ['status' => 'excused'] + $out_row;
        }
        if (isset($holidays[$date])) {
            return ['status' => 'holiday'] + $out_row;
        }
        if (! $workday) {
            return ['status' => 'rest'] + $out_row;
        }
        if ($leave) {
            return ['status' => $leave->paid ? 'leave' : 'leave_unpaid'] + $out_row;
        }
        $today = $now->toDateString();
        if ($date < $today) {
            return ['status' => 'absent'] + $out_row;
        }
        if ($date === $today) {
            return ['status' => self::minuteOfDay($now) > self::mins($e->shift_end) ? 'absent' : 'pending'] + $out_row;
        }

        return $out_row;
    }

    /** Every day of a month for one employee, with the month's totals and the pay. */
    public static function month(Employee $e, string $month, ?Carbon $now = null): array
    {
        $first = Carbon::parse($month.'-01', self::tz())->startOfMonth();
        $from = $first->toDateString();
        $to = $first->copy()->endOfMonth()->toDateString();
        $holidays = self::holidays($from, $to);
        $records = Record::where('employee_id', $e->id)->whereBetween('work_date', [$from, $to])->get()->keyBy(fn ($r) => $r->work_date->toDateString());
        $leaves = Leave::where('employee_id', $e->id)->where('start_date', '<=', $to)->where('end_date', '>=', $from)->get()->all();

        $days = [];
        $t = ['present' => 0, 'late_days' => 0, 'late_minutes' => 0, 'early_minutes' => 0, 'overtime_minutes' => 0, 'absent' => 0, 'leave_paid' => 0, 'leave_unpaid' => 0, 'excused' => 0, 'no_clock_out' => 0, 'worked_minutes' => 0];
        $scheduled = 0;   // working days of the whole month: what the daily rate divides by
        $payable = 0;     // working days from the hire date on
        for ($d = $first->copy(); $d->toDateString() <= $to; $d->addDay()) {
            $date = $d->toDateString();
            $row = self::day($e, $date, $records[$date] ?? null, $holidays, $leaves, $now);
            $days[] = $row;
            if (self::isWorkday($e, $d, $holidays)) {
                $scheduled++;
                if ($row['status'] !== 'before') {
                    $payable++;
                }
            }
            switch ($row['status']) {
                case 'present': $t['present']++; break;
                case 'late': $t['present']++; $t['late_days']++; break;
                case 'absent': $t['absent']++; break;
                case 'leave': $t['leave_paid']++; break;
                case 'leave_unpaid': $t['leave_unpaid']++; break;
                case 'excused': $t['excused']++; break;
            }
            $t['late_minutes'] += $row['late'];
            $t['early_minutes'] += $row['early'];
            $t['overtime_minutes'] += $row['overtime'];
            $t['worked_minutes'] += $row['worked'];
            $t['no_clock_out'] += $row['no_clock_out'] ? 1 : 0;
        }

        $salary = (float) $e->monthly_salary;
        $daily = $scheduled > 0 ? $salary / $scheduled : 0.0;
        $rate = $daily / self::paidMinutes($e);
        $gross = round($daily * $payable, 2);
        $ded = [
            'late' => round($t['late_minutes'] * $rate, 2), 'early' => round($t['early_minutes'] * $rate, 2),
            'absent' => round($t['absent'] * $daily, 2), 'unpaid_leave' => round($t['leave_unpaid'] * $daily, 2),
        ];
        $deductions = round(array_sum($ded), 2);

        return [
            'month' => $month, 'days' => $days, 'totals' => $t, 'working_days' => $scheduled,
            'pay' => ['currency' => $e->currency, 'monthly_salary' => round($salary, 2), 'daily_rate' => round($daily, 2), 'minute_rate' => round($rate, 4), 'gross' => $gross, 'deductions' => $ded, 'total_deductions' => $deductions, 'net' => round(max(0, $gross - $deductions), 2)],
        ];
    }

    /** Today in the office and the last week, for the dashboard: who is in, late, absent, on leave, and the latest clock-ins. */
    public static function overview(): array
    {
        $now = self::now();
        $date = $now->toDateString();
        $emps = Employee::where('status', 'active')->get();
        $holidays = self::holidays($date, $date);
        $recs = Record::where('work_date', $date)->get()->keyBy('employee_id');
        $leaves = Leave::where('start_date', '<=', $date)->where('end_date', '>=', $date)->get()->groupBy('employee_id');
        $c = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0, 'pending' => 0, 'off' => 0];
        $latest = [];
        foreach ($emps as $e) {
            $d = self::day($e, $date, $recs[$e->id] ?? null, $holidays, ($leaves[$e->id] ?? collect())->all(), $now);
            $k = match ($d['status']) { 'present' => 'present', 'late' => 'late', 'absent' => 'absent', 'leave', 'leave_unpaid', 'excused' => 'leave', 'pending', 'upcoming' => 'pending', default => 'off' };
            $c[$k]++;
            if ($d['clock_in']) {
                $latest[] = ['id' => $e->id, 'name_en' => $e->name_en, 'name_ar' => $e->name_ar, 'time' => $d['clock_in'], 'late' => $d['late'], 'at' => $d['clock_in_at']];
            }
        }
        usort($latest, fn ($a, $b) => strcmp((string) $b['at'], (string) $a['at']));

        $from = $now->copy()->subDays(6)->toDateString();
        $byDay = Record::whereBetween('work_date', [$from, $date])->whereNotNull('clock_in_at')->get()->groupBy(fn ($r) => $r->work_date->toDateString());
        $week = [];
        for ($d = $now->copy()->subDays(6); $d->toDateString() <= $date; $d->addDay()) {
            $rows = $byDay->get($d->toDateString(), collect());
            $week[] = ['date' => $d->toDateString(), 'weekday' => $d->dayOfWeekIso, 'present' => $rows->count(), 'late' => $rows->where('late_minutes', '>', 0)->count()];
        }

        return ['date' => $date, 'total' => $emps->count(), 'counts' => $c, 'clocked_in' => $c['present'] + $c['late'], 'latest' => array_slice($latest, 0, 5), 'week' => $week];
    }
}
