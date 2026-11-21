<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Twenty made-up orphans (AY-001 .. AY-020) so the screens have something to show while the system is being set up.
 * Every one is flagged is_sample, and the Orphans page has a button that removes exactly these and nothing else.
 * Not added during automated tests, nor on a server that has no foundation yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        // the foundation the staff accounts belong to (normally the only one)
        $fid = DB::table('users')->whereNotNull('foundation_id')->groupBy('foundation_id')->orderByRaw('count(*) desc')->orderBy('foundation_id')->value('foundation_id')
            ?? DB::table('foundations')->orderBy('id')->value('id');
        if (app()->runningUnitTests() || ! $fid) {
            return;
        }
        if (DB::table('children')->where('foundation_id', $fid)->where('is_sample', true)->exists()) {
            return;                                   // already added
        }

        $boys = [['Abdulrahman', 'عبد الرحمن'], ['Yusuf', 'يوسف'], ['Omar', 'عمر'], ['Ibrahim', 'إبراهيم'], ['Hamza', 'حمزة'], ['Khalid', 'خالد'], ['Salman', 'سلمان'], ['Anas', 'أنس'], ['Bilal', 'بلال'], ['Zaid', 'زيد']];
        $girls = [['Aisha', 'عائشة'], ['Maryam', 'مريم'], ['Fatima', 'فاطمة'], ['Khadija', 'خديجة'], ['Zainab', 'زينب'], ['Amina', 'أمينة'], ['Hafsa', 'حفصة'], ['Safiya', 'صفية'], ['Ruqayya', 'رقية'], ['Sumaya', 'سمية']];
        $cities = [['Manila', 'مانيلا'], ['Pagadian City', 'باغاديان'], ['Zamboanga City', 'زامبوانغا'], ['Cotabato City', 'كوتاباتو'], ['Marawi City', 'مراوي'], ['Davao City', 'دافاو']];
        $levels = ['kindergarten' => ['الروضة', 'Kinder'], 'primary' => ['الابتدائية', 'Grade'], 'intermediate' => ['المتوسطة', 'Grade'], 'secondary' => ['الثانوية', 'Grade']];
        $perf = ['weak', 'average', 'good', 'very_good', 'excellent'];
        $quran = ['جزء عم', 'جزءان', 'سورة البقرة', '5 أجزاء', 'لم يبدأ بعد', 'جزء تبارك'];
        $arGrades = ['الأول', 'الثاني', 'الثالث', 'الرابع', 'الخامس', 'السادس'];
        $guardians = ['A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'];
        $now = now();

        foreach (range(1, 20) as $i) {
            $boy = $i % 2 === 1;
            [$en, $ar] = $boy ? $boys[intdiv($i - 1, 2) % 10] : $girls[intdiv($i - 1, 2) % 10];
            [$cityEn, $cityAr] = $cities[$i % 6];
            $age = 5 + ($i * 7) % 12;                                      // 5 .. 16
            $levelKey = $age < 6 ? 'kindergarten' : ($age < 12 ? 'primary' : ($age < 15 ? 'intermediate' : 'secondary'));
            $gradeNo = max(1, min(6, $age - 5));
            $ref = 'AY-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $g = $guardians[($i - 1) % 10];

            $guardianId = DB::table('guardians')->insertGetId(['foundation_id' => $fid, 'name' => "Sample Guardian $g", 'relation' => ['Uncle', 'Aunt', 'Grandmother', 'Brother', 'Mother'][$i % 5], 'phone' => '09'.(170000000 + $i * 1111), 'created_at' => $now, 'updated_at' => $now]);
            $childId = DB::table('children')->insertGetId([
                'foundation_id' => $fid, 'ref' => $ref, 'name_en' => "$en Sample", 'name_ar' => "$ar تجريبي", 'gender' => $boy ? 'm' : 'f',
                'birth_date' => now()->subYears($age)->subDays($i * 11)->toDateString(), 'city' => $cityEn, 'health_status' => $i % 7 === 0 ? 'needs_followup' : 'good',
                'grade' => $arGrades[$gradeNo - 1], 'mother_name' => "Sample Mother $g", 'mother_marital_status' => $i % 4 === 0 ? 'Married' : 'Widow', 'father_death_date' => now()->subYears(min($age - 1, 2 + $i % 5))->toDateString(),
                'brothers' => $i % 4, 'sisters' => ($i + 1) % 3, 'guardian_id' => $guardianId, 'status' => $i % 10 === 0 ? 'pending' : 'active',
                'nationality' => 'الفلبين - Philippines', 'birth_country' => 'الفلبين', 'residence_country' => 'الفلبين', 'address_ar' => "عنوان تجريبي $i", 'address_en' => "Sample address $i, $cityEn",
                'health_details' => $i % 7 === 0 ? 'يحتاج متابعة دورية للنظر' : 'بصحة جيدة', 'study_level' => $levelKey, 'madrasa_grade' => 'الصف '.$arGrades[$gradeNo - 1],
                'quran_memorization' => $quran[$i % 6], 'performance' => $perf[$i % 5], 'islamic_commitment' => $perf[($i + 2) % 5],
                'mother_name_ar' => "أم تجريبية $g", 'mother_works' => $i % 3 === 0 ? 'yes' : 'no', 'mother_salary' => $i % 3 === 0 ? 4000 + $i * 100 : 0,
                'aided_siblings' => $i % 2, 'other_aid' => $i % 5 === 0 ? 'سلة غذائية شهرية' : null,
                'responsible_person' => 'مكتب الكفالة', 'responsible_name_ar' => "مسؤول تجريبي $g", 'responsible_name_en' => "Sample Responsible $g", 'responsible_relation' => ['العم', 'الخالة', 'الجدة', 'الأخ الأكبر', 'الأم'][$i % 5],
                'responsible_since' => now()->subMonths(6 + $i)->toDateString(), 'channel_label' => 'Bank Beneficiaries', 'source_label' => 'Aytam (partner file)',
                'sponsor_name' => 'Sample Sponsor '.$guardians[($i + 3) % 10], 'sponsor_file_no' => 'SP-'.(1000 + $i),
                'notable_changes' => 'تطور ملحوظ في الدراسة والالتزام', 'organization_comment' => 'شعر اليتيم بوجود من يسانده عند الحاجة', 'message_to_sponsor' => 'نتوجه بالشكر للكفيل على كفالته الكريمة',
                'delay_reason' => $i % 9 === 0 ? 'تأخر تحويل البنك' : null, 'expected_send_date' => $i % 9 === 0 ? now()->addDays(10)->toDateString() : null,
                'form_submitted_at' => now()->subDays(40 - $i), 'is_sample' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('bank_accounts')->insert(['foundation_id' => $fid, 'child_id' => $childId, 'holder' => "Sample Guardian $g", 'bank_name' => $i % 2 ? 'AUB' : 'Hello Money', 'last4' => str_pad((string) (2381 + $i * 37 % 7000), 4, '0', STR_PAD_LEFT), 'verified' => $i % 3 !== 0, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('sponsorships')->insert(['foundation_id' => $fid, 'child_id' => $childId, 'sponsor_id' => null, 'channel' => 'direct', 'status' => 'active', 'pay_type' => 'bank', 'monthly_amount' => 0, 'start_date' => $now->toDateString(), 'created_at' => $now, 'updated_at' => $now]);
            DB::table('channel_histories')->insert(['foundation_id' => $fid, 'child_id' => $childId, 'channel' => 'direct', 'since' => $now->toDateString(), 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        DB::table('children')->where('is_sample', true)->delete();
    }
};
