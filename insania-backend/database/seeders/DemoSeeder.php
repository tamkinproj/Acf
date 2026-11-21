<?php

namespace Database\Seeders;

use App\Models\BankAccount;
use App\Models\ChannelHistory;
use App\Models\Child;
use App\Models\Department;
use App\Models\Foundation;
use App\Models\Guardian;
use App\Models\Project;
use App\Models\Sponsor;
use App\Models\Sponsorship;
use App\Models\SponsorshipPayment;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Fictional demo data, the same as the staff app prototype. Local use only: DatabaseSeeder refuses to run it in production.
 * Every account uses the demo password below. Never use it anywhere real.
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'DemoPass#2026';

    private const KIDS = [
        ['Amina Hassan', 'أمينة حسن', 'f', 2014, 'Hassan Ibrahim'], ['Yusuf Ali', 'يوسف علي', 'm', 2012, 'Ali Mahmoud'],
        ['Fatima Noor', 'فاطمة نور', 'f', 2015, 'Noor Salim'], ['Ibrahim Saleh', 'إبراهيم صالح', 'm', 2011, 'Saleh Khalid'],
        ['Maryam Omar', 'مريم عمر', 'f', 2013, 'Omar Faisal'], ['Zaid Karim', 'زيد كريم', 'm', 2016, 'Karim Adel'],
        ['Aisha Rahman', 'عائشة رحمن', 'f', 2012, 'Rahman Tariq'], ['Hamza Yousef', 'حمزة يوسف', 'm', 2014, 'Yousef Nabil'],
        ['Layla Sami', 'ليلى سامي', 'f', 2015, 'Sami Walid'], ['Omar Nasser', 'عمر ناصر', 'm', 2010, 'Nasser Hamid'],
        ['Sara Idris', 'سارة إدريس', 'f', 2013, 'Idris Jamal'], ['Khalid Mansour', 'خالد منصور', 'm', 2011, 'Mansour Rashid'],
    ];

    public function run(): void
    {
        $f = Foundation::firstOrCreate(['slug' => 'al-insania'], ['name' => 'Al Insania Charity Foundation Inc.', 'name_ar' => 'مؤسسة الإنسانية الخيرية']);

        foreach (config('roles.departments') as $key => $d) {
            Department::firstOrCreate(['foundation_id' => $f->id, 'key' => $key], ['name_en' => $d['name_en'], 'name_ar' => $d['name_ar']]);
        }

        $partner = Sponsor::firstOrCreate(['foundation_id' => $f->id, 'name' => 'Dar Al Ber Society'], ['organization' => 'Dar Al Ber Society']);
        Sponsor::firstOrCreate(['foundation_id' => $f->id, 'name' => 'Dubai Charity Association'], ['organization' => 'Dubai Charity Association']);

        $family = null;
        foreach (self::KIDS as $i => [$en, $ar, $gender, $year, $guardianName]) {
            $id = $i + 1;
            $guardian = Guardian::create(['foundation_id' => $f->id, 'name' => $guardianName, 'relation' => $id % 2 ? 'Mother' : 'Uncle', 'phone' => '+63 900 000 00'.str_pad((string) $id, 2, '0', STR_PAD_LEFT)]);
            $family ??= $guardian;

            $child = Child::create([
                'foundation_id' => $f->id, 'ref' => 'ACF-'.str_pad((string) $id, 4, '0', STR_PAD_LEFT), 'name_en' => $en, 'name_ar' => $ar, 'gender' => $gender,
                'birth_date' => "$year-0".(($i % 9) + 1).'-1'.($i % 9), 'city' => ['Pagadian City', 'Zamboanga City', 'Molave', 'Dimataling'][$i % 4],
                'health_status' => $i % 5 === 3 ? 'needs_followup' : 'good', 'grade' => ['Grade 3', 'Grade 5', 'Grade 2', 'Grade 6', 'Grade 4', 'Grade 1'][$i % 6],
                'mother_name' => 'Mother of '.explode(' ', $en)[0], 'mother_marital_status' => 'widowed', 'father_death_date' => (2018 + ($i % 5)).'-0'.(($i % 8) + 1).'-15',
                'brothers' => $i % 3, 'sisters' => ($i + 1) % 3, 'guardian_id' => $guardian->id, 'status' => 'active',
            ]);

            $bankPay = $id % 3 !== 0;
            $channel = $id % 2 ? 'partner' : 'direct';
            $amount = $id % 2 ? 200 : 150;

            if ($bankPay) {
                BankAccount::create(['foundation_id' => $f->id, 'child_id' => $child->id, 'holder' => $guardianName, 'bank_name' => ['Sample Bank A', 'Sample Bank B', 'Sample Bank C'][$i % 3],
                    'last4' => (string) (1000 + $id * 137), 'verified' => $id % 4 !== 1]);
            }
            ChannelHistory::create(['foundation_id' => $f->id, 'child_id' => $child->id, 'channel' => $channel, 'since' => '2025-0'.(($i % 9) + 1).'-01']);

            $s = Sponsorship::create(['foundation_id' => $f->id, 'child_id' => $child->id, 'sponsor_id' => $channel === 'partner' ? $partner->id : null, 'channel' => $channel,
                'status' => 'active', 'pay_type' => $bankPay ? 'bank' : 'cash', 'monthly_amount' => $amount, 'start_date' => '2025-0'.(($i % 9) + 1).'-01']);

            foreach (['2026-10' => true, '2026-11' => ! ($id % 4 === 0 || $id === 6)] as $month => $paid) {
                SponsorshipPayment::create(['foundation_id' => $f->id, 'sponsorship_id' => $s->id, 'month' => $month, 'amount' => $amount, 'method' => $s->pay_type,
                    'status' => $paid ? 'paid' : 'pending', 'paid_at' => $paid ? "$month-05 10:00:00" : null]);
            }
        }

        foreach ([['Clean water well', 'بئر مياه نظيفة', 'Zone 5, Pagadian City', 4800, 3100, 'active'], ['School supplies, term 2', 'مستلزمات مدرسية للفصل الثاني', 'Madrasa, Molave', 2200, 2200, 'completed'],
            ['Winter clothing drive', 'حملة ملابس الشتاء', 'Zamboanga City', 1500, 600, 'active'], ['Madrasa classroom repair', 'ترميم فصول المدرسة', 'Dimataling', 6400, 1900, 'active']] as [$en, $ar, $loc, $budget, $spent, $status]) {
            Project::create(['foundation_id' => $f->id, 'name_en' => $en, 'name_ar' => $ar, 'location' => $loc, 'budget' => $budget, 'spent' => $spent, 'status' => $status]);
        }

        $dept = fn (string $key) => Department::where('foundation_id', $f->id)->where('key', $key)->value('id');
        foreach ([
            ['System Admin', 'system@insania.test', 'system_admin', null, null], ['Admin Maintenance', 'maintenance@insania.test', 'admin_maintenance', null, null],
            ['Security Admin', 'security@insania.test', 'security_admin', null, null], ['President Admin', 'president@insania.test', 'president_admin', null, null],
            ['Aytam Staff', 'aytam@insania.test', 'staff', $dept('aytam'), null], ['Finance Staff', 'finance@insania.test', 'staff', $dept('finance'), null],
            ['Projects Staff', 'projects@insania.test', 'staff', $dept('projects'), null], ['Education Staff', 'education@insania.test', 'staff', $dept('education'), null], ['HR Staff', 'hr@insania.test', 'staff', $dept('hr'), null], ['Family Account', 'family@insania.test', 'user', null, $family->id],
        ] as [$name, $email, $role, $deptId, $guardianId]) {
            User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD, 'role' => $role, 'foundation_id' => $f->id,
                'department_id' => $deptId, 'guardian_id' => $guardianId, 'active' => true]);
        }
    }
}
