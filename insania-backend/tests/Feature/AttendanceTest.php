<?php

namespace Tests\Feature;

use App\Models\Attendance\Employee;
use App\Models\Attendance\Holiday;
use App\Models\Attendance\Record;
use App\Models\User;
use App\Services\AttendanceCalc;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsWorld;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use BuildsWorld, RefreshDatabase;

    private string $store;

    protected function setUp(): void
    {
        parent::setUp();
        $this->world();
        config(['attendance.timezone' => 'Asia/Manila']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function png(): UploadedFile
    {
        $im = imagecreatetruecolor(60, 80);
        imagefill($im, 0, 0, imagecolorallocate($im, 200, 160, 120));
        ob_start();
        imagepng($im);

        return UploadedFile::fake()->createWithContent('face.png', (string) ob_get_clean());
    }

    /** "2026-03-10 08:20" in Manila */
    private function at(string $local): void
    {
        Carbon::setTestNow(Carbon::parse($local, 'Asia/Manila')->utc());
    }

    private function employee(array $over = []): array
    {
        $this->actingAsRole('staff:hr');

        return $this->postJson('/api/attendance/employees', $over + ['name_en' => 'Test Person', 'monthly_salary' => 22000, 'hired_on' => '2025-01-01'])->assertCreated()->json('data');
    }

    public function test_who_can_reach_attendance(): void
    {
        foreach (['user', 'system_admin', 'security_admin'] as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/attendance/me')->assertForbidden();
            $this->getJson('/api/attendance/board')->assertForbidden();
        }
        foreach (['staff:aytam', 'staff:finance', 'staff:projects', 'staff:education'] as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/attendance/me')->assertOk();
            $this->getJson('/api/attendance/board')->assertForbidden();
            $this->getJson('/api/attendance/employees')->assertForbidden();
            $this->getJson('/api/attendance/payroll')->assertForbidden();
            $this->postJson('/api/attendance/samples')->assertForbidden();
        }
        foreach (['staff:hr', 'president_admin'] as $role) {
            $this->actingAsRole($role);
            $this->getJson('/api/attendance/board')->assertOk();
            $this->getJson('/api/attendance/payroll')->assertOk();
        }
    }

    public function test_clocking_in_late_is_stamped_by_the_server_and_costs_minutes(): void
    {
        $e = $this->employee(['user_id' => User::where('email', 'finance@insania.test')->value('id')]);
        $this->at('2026-03-10 08:20');   // a Tuesday
        $this->actingAsRole('staff:finance');
        $this->postJson('/api/attendance/clock', ['action' => 'in'])->assertStatus(422);           // the face photo is required
        $r = $this->post('/api/attendance/clock', ['action' => 'in', 'photo' => $this->png()], ['Accept' => 'application/json'])->assertCreated()->json('data.today');
        $this->assertSame('late', $r['status']);
        $this->assertSame(20, $r['late']);
        $this->assertSame('08:20', $r['clock_in']);
        $this->assertTrue($r['in_photo']);
        $this->post('/api/attendance/clock', ['action' => 'in', 'photo' => $this->png()], ['Accept' => 'application/json'])->assertStatus(409);

        $this->at('2026-03-10 16:30');
        $out = $this->post('/api/attendance/clock', ['action' => 'out', 'photo' => $this->png()], ['Accept' => 'application/json'])->assertCreated()->json('data.today');
        $this->assertSame(30, $out['early']);
        $this->post('/api/attendance/clock', ['action' => 'out', 'photo' => $this->png()], ['Accept' => 'application/json'])->assertStatus(409);

        // HR can look at the photo, other staff cannot, the person can see their own
        $rid = $r['record_id'];
        $this->getJson("/api/attendance/records/$rid/photo/in")->assertOk();
        $this->actingAsRole('staff:aytam');
        $this->getJson("/api/attendance/records/$rid/photo/in")->assertForbidden();
        $this->actingAsRole('staff:hr');
        $this->getJson("/api/attendance/records/$rid/photo/in")->assertOk();
    }

    public function test_grace_minutes_and_weekends(): void
    {
        $e = $this->employee(['grace_minutes' => 10, 'user_id' => User::where('email', 'finance@insania.test')->value('id')]);
        $this->at('2026-03-10 08:08');
        $this->actingAsRole('staff:finance');
        $this->assertSame(0, $this->post('/api/attendance/clock', ['action' => 'in', 'photo' => $this->png()], ['Accept' => 'application/json'])->assertCreated()->json('data.today.late'));
        $this->at('2026-03-14 09:30');   // a Saturday is not a working day: never late
        $this->assertSame(0, $this->post('/api/attendance/clock', ['action' => 'in', 'photo' => $this->png()], ['Accept' => 'application/json'])->assertCreated()->json('data.today.late'));
    }

    public function test_the_pay_rules_late_early_absent_and_leave(): void
    {
        // March 2026 has 22 weekdays. Salary 22,000 -> 1,000 a day; shift 08:00-17:00 with a 60 minute break = 480 paid minutes.
        $e = $this->employee(['monthly_salary' => 22000, 'hired_on' => '2025-01-01']);
        $this->at('2026-04-02 10:00');
        $put = fn (array $b) => $this->putJson('/api/attendance/records', $b + ['employee_id' => $e['id']])->assertOk();
        $put(['date' => '2026-03-02', 'status' => 'present', 'clock_in' => '08:48', 'clock_out' => '17:00', 'note' => 'test']);   // 48 late
        $put(['date' => '2026-03-03', 'status' => 'present', 'clock_in' => '08:00', 'clock_out' => '16:12', 'note' => 'test']);   // 48 early
        $put(['date' => '2026-03-04', 'status' => 'excused', 'note' => 'Office closed']);                                          // no deduction
        $this->postJson('/api/attendance/leaves', ['employee_id' => $e['id'], 'start_date' => '2026-03-05', 'end_date' => '2026-03-05', 'kind' => 'sick'])->assertCreated();     // paid
        $this->postJson('/api/attendance/leaves', ['employee_id' => $e['id'], 'start_date' => '2026-03-06', 'end_date' => '2026-03-06', 'kind' => 'unpaid'])->assertCreated();   // unpaid: 1,000
        $this->postJson('/api/attendance/holidays', ['date' => '2026-03-09', 'name_en' => 'Test holiday'])->assertCreated();                                                    // 21 working days then
        // every other weekday has no record: absent

        $m = $this->getJson('/api/attendance/records?employee_id='.$e['id'].'&month=2026-03')->assertOk()->json('data');
        $this->assertSame(21, $m['working_days']);
        $this->assertSame(48, $m['totals']['late_minutes']);
        $this->assertSame(48, $m['totals']['early_minutes']);
        $this->assertSame(1, $m['totals']['leave_unpaid']);
        $absent = 21 - 2 - 1 - 1 - 1;   // present x2, excused, paid leave, unpaid leave
        $this->assertSame($absent, $m['totals']['absent']);

        $daily = 22000 / 21;
        $rate = $daily / 480;
        $pay = $m['pay'];
        $this->assertEquals(round(48 * $rate, 2), $pay['deductions']['late']);
        $this->assertEquals(round(48 * $rate, 2), $pay['deductions']['early']);
        $this->assertEquals(round($absent * $daily, 2), $pay['deductions']['absent']);
        $this->assertEquals(round($daily, 2), $pay['deductions']['unpaid_leave']);
        $this->assertEqualsWithDelta($daily * 21 - (2 * 48 * $rate + ($absent + 1) * $daily), $pay['net'], 0.03);

        // the payroll agrees, and the person without the salary right never sees money
        $row = collect($this->getJson('/api/attendance/payroll?month=2026-03')->assertOk()->json('data.rows'))->firstWhere('employee.id', $e['id']);
        $this->assertEquals($pay['net'], $row['pay']['net']);
        $this->actingAsRole('staff:aytam');
        $this->assertArrayNotHasKey('pay', $this->getJson('/api/attendance/records?month=2026-03')->json('data') ?? []);
    }

    public function test_salary_is_hidden_without_the_salary_right(): void
    {
        $e = $this->employee(['monthly_salary' => 31000]);
        $this->assertEquals(31000, $this->getJson('/api/attendance/employees')->json('data.employees.0.monthly_salary'));
        // a manager without salary rights: give the HR role manage only
        $user = User::where('email', 'hr@insania.test')->first();
        $this->assertContains('attendance.salary', \App\Support\PermissionCatalog::forUser($user));
        $this->actingAsRole('staff:aytam');
        $this->getJson('/api/attendance/payroll')->assertForbidden();
    }

    public function test_samples_can_be_loaded_and_removed(): void
    {
        $this->actingAsRole('president_admin');
        $this->postJson('/api/attendance/samples')->assertCreated();
        $this->assertGreaterThanOrEqual(6, Employee::where('is_sample', true)->count());
        $this->assertGreaterThan(50, Record::where('is_sample', true)->count());
        $board = $this->getJson('/api/attendance/board')->assertOk()->json('data');
        $this->assertGreaterThanOrEqual(6, count($board['rows']));
        $me = $this->getJson('/api/attendance/me')->assertOk()->json('data');
        $this->assertNotNull($me['employee']);              // the person who loaded the samples can try clocking in
        $this->assertNotNull($this->getJson('/api/attendance/payroll')->json('data.rows.0.pay'));
        $this->postJson('/api/attendance/samples')->assertCreated()->assertJsonPath('data.employees', 0);   // not twice
        $this->deleteJson('/api/attendance/samples')->assertOk();
        $this->assertSame(0, Employee::count());
        $this->assertSame(0, Holiday::count());
    }

    public function test_an_account_is_linked_to_one_employee_only(): void
    {
        $uid = User::where('email', 'finance@insania.test')->value('id');
        $this->employee(['user_id' => $uid]);
        $this->postJson('/api/attendance/employees', ['name_en' => 'Second', 'user_id' => $uid])->assertStatus(422);
        $this->postJson('/api/attendance/employees', ['name_en' => 'Family', 'user_id' => User::where('email', 'family@insania.test')->value('id')])->assertStatus(422);
        $this->postJson('/api/attendance/employees', ['name_en' => 'Bad', 'shift_start' => '17:00', 'shift_end' => '08:00'])->assertStatus(422);
    }

    public function test_corrections_need_a_note_and_are_logged(): void
    {
        $e = $this->employee();
        $this->at('2026-04-02 10:00');
        $this->putJson('/api/attendance/records', ['employee_id' => $e['id'], 'date' => '2026-03-02', 'status' => 'absent'])->assertStatus(422);
        $this->putJson('/api/attendance/records', ['employee_id' => $e['id'], 'date' => '2026-04-20', 'status' => 'absent', 'note' => 'future'])->assertStatus(422);
        $this->putJson('/api/attendance/records', ['employee_id' => $e['id'], 'date' => '2026-03-02', 'status' => 'absent', 'note' => 'Did not come'])->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'attendance.record_edited']);
    }
}
