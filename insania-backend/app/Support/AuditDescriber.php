<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\ChildApplication;
use App\Models\Child;
use App\Models\Education\Curriculum;
use App\Models\Education\Institution;
use App\Models\Education\Teacher;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Turns the audit log's short codes into sentences a person can read ("Renewed a teacher's contract - ALM-0002, until 2027-05-20"), with
 * who did it, what it was about and the details that were recorded. Still no names of orphans or documents: only references.
 */
final class AuditDescriber
{
    /** action => [area, English, Arabic] */
    public const ACTIONS = [
        'login' => ['security', 'Signed in', 'سجّل الدخول'], 'login.failed' => ['security', 'Failed sign-in attempt', 'محاولة دخول فاشلة'], 'login.locked' => ['security', 'Account locked after too many wrong passwords', 'قُفل الحساب بعد كلمات مرور خاطئة كثيرة'],
        'password.changed' => ['security', 'Changed their own password', 'غيّر كلمة مروره'], 'password.change_failed' => ['security', 'Typed a wrong current password while changing the password', 'كتب كلمة المرور الحالية خطأ أثناء التغيير'],
        'stepup.failed' => ['security', 'Typed a wrong password to confirm a change', 'كتب كلمة مرور خاطئة لتأكيد تغيير'], 'session.ended' => ['security', 'Signed out a device', 'أنهى جلسة جهاز'], 'session.ended_others' => ['security', 'Signed out all other devices', 'أنهى كل الجلسات الأخرى'],
        'user.created' => ['accounts', 'Created an account', 'أنشأ حسابًا'], 'user.updated' => ['accounts', 'Changed an account', 'عدّل حسابًا'], 'user.password_reset' => ['accounts', 'Reset an account\'s password', 'أعاد تعيين كلمة مرور حساب'], 'user.sessions_revoked' => ['accounts', 'Ended all sessions of an account', 'أنهى كل جلسات حساب'],
        'president.changed' => ['accounts', 'Changed the President Admin', 'غيّر مدير الرئاسة'], 'president.change_denied' => ['accounts', 'Tried to change the President Admin and was refused', 'حاول تغيير مدير الرئاسة فرُفض'],
        'orphan.account_created' => ['accounts', 'Created a sign-in account for an orphan', 'أنشأ حساب دخول ليتيم'], 'orphan.account_reset' => ['accounts', 'Gave an orphan a new temporary password', 'أعطى يتيمًا كلمة مرور مؤقتة جديدة'], 'orphan.accounts_created' => ['accounts', 'Created sign-in accounts for orphans', 'أنشأ حسابات دخول للأيتام'],
        'profile.avatar' => ['accounts', 'Changed their profile picture', 'غيّر صورته الشخصية'], 'profile.username' => ['accounts', 'Changed their username', 'غيّر اسم المستخدم'],
        'api_key.created' => ['system', 'Issued a partner key', 'أصدر مفتاح شريك'], 'api_key.revoked' => ['system', 'Revoked a partner key', 'ألغى مفتاح شريك'], 'settings.updated' => ['system', 'Changed system settings', 'غيّر إعدادات النظام'],
        'update.applied' => ['system', 'Applied an update', 'طبّق تحديثًا'], 'update.failed' => ['system', 'An update failed', 'فشل تحديث'], 'update.rejected' => ['system', 'An update was refused (bad signature or file)', 'رُفض تحديث (توقيع أو ملف غير صالح)'], 'update.undone' => ['system', 'Undid an update', 'تراجع عن تحديث'],
        'child.created' => ['orphans', 'Added an orphan', 'أضاف يتيمًا'], 'child.updated' => ['orphans', 'Edited an orphan', 'عدّل بيانات يتيم'], 'child.photo_added' => ['orphans', 'Added a photo of an orphan', 'أضاف صورة ليتيم'], 'child.photo_removed' => ['orphans', 'Removed a photo of an orphan', 'حذف صورة ليتيم'],
        'child.samples_removed' => ['orphans', 'Removed the sample orphans', 'حذف الأيتام التجريبيين'], 'children.imported' => ['orphans', 'Imported orphans from Excel', 'استورد أيتامًا من إكسل'],
        'bank.verified' => ['orphans', 'Verified a bank account', 'وثّق حسابًا بنكيًا'], 'bank.unverified' => ['orphans', 'Removed the verification of a bank account', 'ألغى توثيق حساب بنكي'],
        'document.uploaded' => ['orphans', 'Uploaded an orphan\'s document', 'رفع مستندًا ليتيم'], 'document.downloaded' => ['orphans', 'Opened an orphan\'s document', 'فتح مستندًا ليتيم'],
        'application.received' => ['orphans', 'A new application was received', 'وصل طلب جديد'], 'application.approved' => ['orphans', 'Approved an application', 'وافق على طلب'], 'application.rejected' => ['orphans', 'Rejected an application', 'رفض طلبًا'],
        'payment.cash_received' => ['finance', 'Recorded a cash payment', 'سجّل دفعة نقدية'], 'report.exported' => ['finance', 'Exported a report', 'صدّر تقريرًا'],
        'project.created' => ['projects', 'Created a project', 'أنشأ مشروعًا'], 'project.updated' => ['projects', 'Changed a project', 'عدّل مشروعًا'],
        'site.photo_uploaded' => ['website', 'Uploaded a website photo', 'رفع صورة للموقع'], 'attendance.employee_added' => ['attendance', 'Added an employee', 'أضاف موظفًا'], 'attendance.employee_changed' => ['attendance', 'Changed an employee', 'عدّل موظفًا'], 'attendance.face_enrolled' => ['attendance', 'Saved an employee\'s reference face photo', 'حفظ صورة الوجه المرجعية لموظف'], 'attendance.record_edited' => ['attendance', 'Corrected an attendance day', 'صحّح يوم حضور'], 'attendance.record_cleared' => ['attendance', 'Cleared an attendance day', 'مسح يوم حضور'], 'attendance.leave_added' => ['attendance', 'Added a leave', 'أضاف إجازة'], 'attendance.leave_removed' => ['attendance', 'Removed a leave', 'حذف إجازة'], 'attendance.holiday_added' => ['attendance', 'Added a holiday', 'أضاف عطلة'], 'attendance.holiday_removed' => ['attendance', 'Removed a holiday', 'حذف عطلة'], 'work.samples_loaded' => ['attendance', 'Loaded sample work activity', 'حمّل نشاط عمل تجريبي'], 'work.samples_removed' => ['attendance', 'Removed sample work activity', 'حذف نشاط العمل التجريبي'], 'attendance.samples_loaded' => ['attendance', 'Loaded sample attendance data', 'حمّل بيانات حضور تجريبية'], 'attendance.samples_removed' => ['attendance', 'Removed sample attendance data', 'حذف بيانات الحضور التجريبية'], 'user.access_changed' => ['accounts', 'Changed what an account can open', 'غيّر ما يمكن لحساب فتحه'], 'site.update_posted' => ['website', 'Posted an update on the website', 'نشر تحديثًا على الموقع'], 'site.published' => ['website', 'Published the website', 'نشر الموقع'], 'site.reverted' => ['website', 'Went back to an earlier website version', 'عاد إلى نسخة سابقة من الموقع'],
        'edu.teacher_created' => ['education', 'Added a teacher', 'أضاف معلمًا'], 'edu.teacher_updated' => ['education', 'Edited a teacher', 'عدّل بيانات معلم'], 'edu.contract_created' => ['education', 'Made a contract for a teacher', 'أنشأ عقدًا لمعلم'], 'edu.contract_renewed' => ['education', 'Renewed a teacher\'s contract', 'جدّد عقد معلم'],
        'edu.contract_updated' => ['education', 'Changed a teacher\'s contract', 'عدّل عقد معلم'], 'edu.salaries_generated' => ['education', 'Created the salary lines of a month', 'أنشأ أسطر رواتب شهر'], 'edu.salary_recorded' => ['education', 'Recorded a teacher\'s salary', 'سجّل راتب معلم'],
        'edu.institution_created' => ['education', 'Added a madrasa or markaz', 'أضاف مدرسة أو مركزًا'], 'edu.institution_updated' => ['education', 'Edited a madrasa or markaz', 'عدّل مدرسة أو مركزًا'], 'edu.curriculum_created' => ['education', 'Added a curriculum', 'أضاف منهجًا'],
        'edu.curriculum_updated' => ['education', 'Edited a curriculum', 'عدّل منهجًا'], 'edu.unit_added' => ['education', 'Added a unit to a curriculum', 'أضاف وحدة إلى منهج'], 'edu.unit_removed' => ['education', 'Removed a unit from a curriculum', 'حذف وحدة من منهج'],
        'chat.share' => ['chat', 'Shared a card in a chat', 'شارك بطاقة في محادثة'], 'chat.group_created' => ['chat', 'Created a group chat', 'أنشأ مجموعة محادثة'], 'chat.group_updated' => ['chat', 'Changed a group chat', 'عدّل مجموعة محادثة'], 'chat.cover_set' => ['chat', 'Set a chat cover photo', 'عيّن صورة غلاف لمحادثة'],
        'chat.members_added' => ['chat', 'Added people to a group chat', 'أضاف أشخاصًا إلى مجموعة'], 'chat.member_removed' => ['chat', 'Removed someone from a group chat', 'أزال شخصًا من مجموعة'], 'chat.group_left' => ['chat', 'Left a group chat', 'غادر مجموعة'],
    ];

    public const AREAS = ['security', 'accounts', 'orphans', 'finance', 'projects', 'education', 'website', 'chat', 'attendance', 'system'];

    private const KEYS = [
        'fields' => ['Changed', 'ما تغيّر'], 'count' => ['Count', 'العدد'], 'new' => ['New', 'جديد'], 'updated' => ['Updated', 'محدَّث'], 'skipped' => ['Skipped', 'متخطى'], 'month' => ['Month', 'الشهر'], 'role' => ['Role', 'الدور'],
        'status' => ['Status', 'الحالة'], 'until' => ['Until', 'حتى'], 'added' => ['Added', 'المضافون'], 'members' => ['Members', 'الأعضاء'], 'photos' => ['Photos', 'الصور'], 'documents' => ['Documents', 'المستندات'], 'method' => ['Method', 'الطريقة'],
        'type' => ['Type', 'النوع'], 'kind' => ['Card', 'البطاقة'], 'made' => ['Created', 'المنشأ'], 'verified' => ['Verified', 'موثّق'],
    ];

    public static function areaOf(string $action): string
    {
        return self::ACTIONS[$action][0] ?? 'system';
    }

    /** @return list<string> actions that belong to an area */
    public static function actionsIn(string $area): array
    {
        return array_keys(array_filter(self::ACTIONS, fn ($a) => $a[0] === $area));
    }

    private static function pretty(string $k): string
    {
        return ucfirst(str_replace('_', ' ', $k));
    }

    private static function value(mixed $v): string
    {
        if (is_bool($v)) {
            return $v ? 'yes' : 'no';
        }

        return is_array($v) ? implode(', ', array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x), $v)) : (string) $v;
    }

    /** @param  Collection<int, AuditLog>  $logs  @return list<array<string, mixed>> */
    public static function many(Collection $logs): array
    {
        $users = User::withoutGlobalScopes()->whereIn('id', $logs->pluck('user_id')->filter()->unique())->get()->keyBy('id');
        $ids = fn (string $type) => $logs->where('subject_type', $type)->pluck('subject_id')->filter()->unique()->all();
        $label = [
            'child' => Child::withoutGlobalScopes()->whereIn('id', $ids('child'))->pluck('ref', 'id'),
            'user' => User::withoutGlobalScopes()->whereIn('id', $ids('user'))->pluck('username', 'id')->map(fn ($u) => '@'.$u),
            'project' => Project::withoutGlobalScopes()->whereIn('id', $ids('project'))->pluck('name_en', 'id'),
            'edu_teacher' => Teacher::withoutGlobalScopes()->whereIn('id', $ids('edu_teacher'))->pluck('ref', 'id'),
            'edu_institution' => Institution::withoutGlobalScopes()->whereIn('id', $ids('edu_institution'))->pluck('name_en', 'id'),
            'edu_curriculum' => Curriculum::withoutGlobalScopes()->whereIn('id', $ids('edu_curriculum'))->pluck('title_en', 'id'),
            'application' => ChildApplication::withoutGlobalScopes()->whereIn('id', $ids('application'))->pluck('id', 'id')->map(fn ($i) => 'APP-'.str_pad((string) $i, 5, '0', STR_PAD_LEFT)),
        ];

        return $logs->map(function (AuditLog $l) use ($users, $label) {
            [$area, $en, $ar] = self::ACTIONS[$l->action] ?? ['system', $l->action, $l->action];
            $actor = $l->user_id ? $users->get($l->user_id) : null;
            $subject = null;
            if ($l->subject_type) {
                $name = $label[$l->subject_type][$l->subject_id] ?? null;
                $subject = ['type' => $l->subject_type, 'label' => $name ?? '#'.$l->subject_id];
            }
            $details = [];
            foreach ((array) $l->meta as $k => $v) {
                if ($v === null || $v === '' || $v === []) {
                    continue;
                }
                $kk = self::KEYS[$k] ?? [self::pretty((string) $k), self::pretty((string) $k)];
                $details[] = ['en' => $kk[0], 'ar' => $kk[1], 'value' => self::value($v)];
            }

            return [
                'id' => $l->id, 'at' => $l->created_at?->toIso8601String(), 'action' => $l->action, 'area' => $area, 'text' => ['en' => $en, 'ar' => $ar],
                'actor' => $actor ? ['id' => $actor->id, 'name' => $actor->name, 'username' => $actor->username, 'role_label' => config("roles.roles.{$actor->role}.label"), 'avatar' => $actor->avatar_v] : null,
                'subject' => $subject, 'details' => $details, 'ip' => in_array($l->action, ['login', 'login.failed', 'login.locked'], true) ? $l->ip : null,
            ];
        })->values()->all();
    }
}
