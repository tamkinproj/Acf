<?php

namespace App\Services;

use App\Models\Attendance\Employee;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\Leave;
use App\Models\Attendance\Record;
use Carbon\Carbon;

/**
 * Sample employees with about five weeks of attendance (on time, late, absent, leave, early leaving, overtime), so the screens
 * and the pay rules can be tried before real people are added. Everything made here is marked as a sample and can be removed again.
 */
final class AttendanceSamples
{
    private const PEOPLE = [
        // name_en, name_ar, position, salary, late tendency (0-100), absence tendency (0-100)
        ['Maryam Abdullah Salic', 'مريم عبدالله ساليك', 'Admin Assistant', 18000, 10, 3],
        ['Jamal Ibrahim Macapundag', 'جمال إبراهيم ماكابونداق', 'Accountant', 22000, 35, 6],
        ['Aisha Hadji Ali', 'عائشة حاجي علي', 'Social Worker', 20000, 20, 4],
        ['Nasser Sultan Dimaporo', 'ناصر سلطان ديمابورو', 'Driver', 15000, 55, 10],
        ['Fatima Disomangcop', 'فاطمة ديسومانكوب', 'Teacher Aide', 16500, 15, 2],
        ['Omar Pangandaman', 'عمر بانغاندامان', 'IT Support', 21000, 30, 5],
    ];

    public static function load(int $fid, ?int $actorId = null, ?string $actorName = null): int
    {
        if (Employee::withoutGlobalScopes()->where('foundation_id', $fid)->where('is_sample', true)->exists()) {
            return 0;
        }
        $now = AttendanceCalc::now();
        $people = self::PEOPLE;
        $link = null;
        if ($actorId && ! Employee::withoutGlobalScopes()->where('foundation_id', $fid)->where('user_id', $actorId)->exists()) {
            $link = $actorId;
            array_unshift($people, [$actorName ?: 'Me', null, 'Sample: you, to try clocking in', 25000, 15, 0]);
        }
        $n = 0;
        foreach ($people as $i => [$en, $ar, $pos, $salary, $lateP, $absP]) {
            $e = Employee::withoutGlobalScopes()->create(config('attendance.defaults') + [
                'foundation_id' => $fid, 'user_id' => $i === 0 ? $link : null, 'employee_no' => 'S-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'name_en' => $en, 'name_ar' => $ar, 'position' => $pos, 'department' => 'Office', 'monthly_salary' => $salary, 'currency' => config('attendance.currency', 'PHP'),
                'hired_on' => $now->copy()->subMonths(8)->toDateString(), 'is_sample' => true, 'grace_minutes' => $i % 3 === 0 ? 5 : 0, 'phone' => '0917 000 00'.str_pad((string) ($i + 10), 2, '0', STR_PAD_LEFT),
            ]);
            mt_srand(1000 + $i * 77);
            $holidays = array_map(fn ($h) => $h, self::holidayDates($now));
            for ($back = 36; $back >= 0; $back--) {
                $d = $now->copy()->subDays($back)->startOfDay();
                $date = $d->toDateString();
                if (! in_array($d->dayOfWeekIso, [1, 2, 3, 4, 5], true) || isset($holidays[$date])) {
                    continue;
                }
                $isToday = $back === 0;
                if ($isToday && $e->user_id) {
                    continue;                                   // the person trying the app clocks in themselves
                }
                $roll = mt_rand(1, 100);
                if (! $isToday && $roll <= $absP) {
                    continue;                                   // absent: no row, the system works it out
                }
                $start = 8 * 60;
                $inMin = $roll <= $absP + $lateP ? $start + mt_rand(4, 55) : $start - mt_rand(0, 12);
                $in = $d->copy()->setTimezone(AttendanceCalc::tz())->startOfDay()->addMinutes($inMin);
                if ($isToday && $in->greaterThan($now)) {
                    continue;
                }
                $out = null;
                if (! $isToday || $now->hour >= 17) {
                    $outMin = 17 * 60 + (mt_rand(1, 100) <= 12 ? mt_rand(-90, -20) : (mt_rand(1, 100) <= 20 ? mt_rand(10, 75) : mt_rand(0, 6)));
                    $out = $d->copy()->setTimezone(AttendanceCalc::tz())->startOfDay()->addMinutes($outMin);
                }
                $late = AttendanceCalc::lateMinutes($e, $in, true);
                [$early, $over] = $out ? AttendanceCalc::leaveTimes($e, $out, true) : [0, 0];
                Record::withoutGlobalScopes()->create([
                    'foundation_id' => $fid, 'employee_id' => $e->id, 'work_date' => $date, 'clock_in_at' => $in->copy()->utc(), 'clock_out_at' => $out?->copy()->utc(),
                    'in_method' => 'face', 'out_method' => $out ? 'face' : null, 'status' => 'present', 'late_minutes' => $late, 'early_minutes' => $early, 'overtime_minutes' => $over, 'is_sample' => true,
                ]);
            }
            // a paid sick leave and one unpaid day, a few days back, for some of the people
            if ($i % 2 === 0) {
                self::leave($fid, $e->id, $now->copy()->subDays(12), 'sick', true, 'Fever (medical note shown)');
            }
            if ($i % 3 === 1) {
                self::leave($fid, $e->id, $now->copy()->subDays(20), 'unpaid', false, 'Family matter');
            }
            $n++;
        }
        foreach (self::holidayDates($now, true) as $date => $name) {
            Holiday::withoutGlobalScopes()->firstOrCreate(['foundation_id' => $fid, 'holiday_date' => $date], ['name_en' => $name, 'is_sample' => true]);
        }

        return $n;
    }

    private static function leave(int $fid, int $eid, Carbon $day, string $kind, bool $paid, string $reason): void
    {
        $date = $day->toDateString();
        if ($day->dayOfWeekIso > 5 || Record::withoutGlobalScopes()->where('employee_id', $eid)->where('work_date', $date)->exists()) {
            return;
        }
        Leave::withoutGlobalScopes()->create(['foundation_id' => $fid, 'employee_id' => $eid, 'start_date' => $date, 'end_date' => $date, 'kind' => $kind, 'paid' => $paid, 'reason' => $reason, 'is_sample' => true]);
    }

    /** National holidays near today, keyed by date. */
    private static function holidayDates(Carbon $now, bool $upcoming = false): array
    {
        $y = (int) $now->format('Y');
        $out = [];
        foreach ([[$y - 1, '12-25', 'Christmas Day'], [$y - 1, '12-30', 'Rizal Day'], [$y, '01-01', "New Year's Day"], [$y, '11-30', 'Bonifacio Day'], [$y, '12-25', 'Christmas Day'], [$y, '12-30', 'Rizal Day'], [$y + 1, '01-01', "New Year's Day"]] as [$yy, $md, $name]) {
            $out["$yy-$md"] = $name;
        }

        return $out;
    }

    public static function remove(): int
    {
        $ids = Employee::where('is_sample', true)->pluck('id');
        $n = $ids->count();
        Employee::whereIn('id', $ids)->delete();
        Holiday::where('is_sample', true)->delete();

        return $n;
    }
}
