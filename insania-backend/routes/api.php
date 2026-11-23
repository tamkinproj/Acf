<?php

use App\Http\Controllers\Api\AuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BankVerificationController;
use App\Http\Controllers\Api\ChildController;
use App\Http\Controllers\Api\ChildDocumentController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FamilyController;
use App\Http\Controllers\Api\PartnerController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\UserAdminController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API for the staff app
|--------------------------------------------------------------------------
| Every route below the login is behind a Sanctum token AND a permission (see config/roles.php).
| A role that lacks the permission gets 403 before any controller code runs.
*/

Route::middleware('api.json')->group(function () {
    Route::get('public/site', \App\Http\Controllers\Api\PublicSiteController::class)->middleware('throttle:60,1');
    // The public application form: anyone with the link may send an application. It only adds to the review queue.
    Route::get('public/apply', [\App\Http\Controllers\Api\PublicApplyController::class, 'status'])->middleware('throttle:60,1');
    Route::post('public/apply', [\App\Http\Controllers\Api\PublicApplyController::class, 'store'])->middleware(['throttle:apply', 'block.uploads']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth_login');

    Route::middleware(['auth:sanctum', 'password.fresh', 'idempotent', 'work.track'])->group(function () {
        // Any signed-in account
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('me/password', [AuthController::class, 'changePassword'])->middleware('throttle:6,1');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth_logout');
        Route::patch('me/profile', [AuthController::class, 'updateProfile'])->middleware('throttle:20,1');
        Route::post('me/avatar', [AuthController::class, 'setAvatar'])->middleware(['throttle:20,1', 'block.uploads']);
        Route::delete('me/avatar', [AuthController::class, 'removeAvatar']);
        Route::get('avatars/{user}', [AuthController::class, 'avatar'])->whereNumber('user');
        Route::get('system/security', [\App\Http\Controllers\Api\SystemController::class, 'security'])->middleware('permission:system.health');
        Route::get('me/sessions', [AuthController::class, 'sessions']);
        Route::get('me/devices', [AuthController::class, 'trustedDevices']);
        Route::post('me/devices', [AuthController::class, 'trustThisDevice'])->middleware('throttle:6,1');
        Route::delete('me/devices/{id}', [AuthController::class, 'removeTrustedDevice'])->whereNumber('id');
        Route::post('me/sessions/end-others', [AuthController::class, 'endOtherSessions'])->middleware('throttle:10,1');
        Route::delete('me/sessions/{id}', [AuthController::class, 'endSession'])->whereNumber('id');
        Route::get('notices/count', [\App\Http\Controllers\Api\NoticeController::class, 'count']);
        Route::get('notices', [\App\Http\Controllers\Api\NoticeController::class, 'index']);
        Route::post('notices/read', [\App\Http\Controllers\Api\NoticeController::class, 'read'])->middleware('throttle:60,1');
        Route::get('me/preferences', [AuthController::class, 'preferences']);
        Route::patch('me/preferences', [AuthController::class, 'updatePreferences']);
        Route::get('dashboard', DashboardController::class);
        Route::get('setup', [\App\Http\Controllers\Api\SetupController::class, 'steps']);

        // Technical roles
        Route::middleware('permission:system.cloudflare')->group(function () {
            Route::get('system/cloudflare', [\App\Http\Controllers\Api\CloudflareController::class, 'show']);
            Route::post('system/cloudflare', [\App\Http\Controllers\Api\CloudflareController::class, 'connect'])->middleware('throttle:6,1');
            Route::patch('system/cloudflare', [\App\Http\Controllers\Api\CloudflareController::class, 'update'])->middleware('throttle:30,1');
            Route::patch('system/cloudflare/rules/{rule}', [\App\Http\Controllers\Api\CloudflareController::class, 'rule'])->middleware('throttle:30,1');
            Route::delete('system/cloudflare', [\App\Http\Controllers\Api\CloudflareController::class, 'disconnect']);
        });
        // Partners: everyone sees the list; only the President Admin pauses or resumes one (there is no delete)
        Route::get('partners', [\App\Http\Controllers\Api\PartnersController::class, 'index']);
        Route::post('partners/{code}/pause', [\App\Http\Controllers\Api\PartnersController::class, 'pause'])->middleware(['permission:partners.manage', 'throttle:10,1'])->where('code', '[a-z0-9_]+');
        Route::post('partners/{code}/resume', [\App\Http\Controllers\Api\PartnersController::class, 'resume'])->middleware(['permission:partners.manage', 'throttle:10,1'])->where('code', '[a-z0-9_]+');
        Route::get('system/health', [SystemController::class, 'health'])->middleware('permission:system.health');
        Route::get('system/settings', [SystemController::class, 'settings'])->middleware('permission:system.settings');
        Route::get('system/limits', [SystemController::class, 'limits'])->middleware('permission:system.settings');
        Route::patch('system/limits', [SystemController::class, 'updateLimits'])->middleware(['permission:system.settings', 'throttle:10,1']);
        Route::patch('system/settings', [SystemController::class, 'updateSettings'])->middleware('permission:system.settings');
        Route::middleware('permission:system.api_keys')->group(function () {
            Route::get('system/api-keys', [\App\Http\Controllers\Api\ApiKeyController::class, 'index']);
            Route::post('system/api-keys', [\App\Http\Controllers\Api\ApiKeyController::class, 'store']);
            Route::delete('system/api-keys/{id}', [\App\Http\Controllers\Api\ApiKeyController::class, 'destroy'])->whereNumber('id');
        });

        Route::get('system/president-admin', [\App\Http\Controllers\Api\SetupController::class, 'president'])->middleware('permission:system.first_admin');
        Route::patch('system/president-admin', [\App\Http\Controllers\Api\SetupController::class, 'changePresident'])->middleware(['permission:system.first_admin', 'throttle:6,1']);
        Route::post('system/president-admin', [\App\Http\Controllers\Api\SetupController::class, 'firstPresidentAdmin'])->middleware('permission:system.first_admin');
        Route::middleware('permission:system.updates')->group(function () {
            Route::get('system/updates', [\App\Http\Controllers\Api\UpdateController::class, 'index']);
            Route::post('system/updates/auto', [\App\Http\Controllers\Api\UpdateController::class, 'auto']);
            Route::post('system/updates/check', [\App\Http\Controllers\Api\UpdateController::class, 'check']);
            Route::post('system/updates/{id}/apply', [\App\Http\Controllers\Api\UpdateController::class, 'apply'])->where('id', '[a-f0-9]{24}');
            Route::post('system/updates/{entry}/undo', [\App\Http\Controllers\Api\UpdateController::class, 'undo'])->where('entry', '[0-9A-Za-z-]+');
        });

        Route::middleware('permission:users.manage')->group(function () {
            Route::get('users', [UserAdminController::class, 'index']);
            Route::get('users/access-catalog', [UserAdminController::class, 'accessCatalog']);
            Route::post('users', [UserAdminController::class, 'store']);
            Route::patch('users/{user}', [UserAdminController::class, 'update']);
            Route::post('users/{user}/password', [UserAdminController::class, 'resetPassword']);
            Route::post('users/{user}/revoke-tokens', [UserAdminController::class, 'revokeTokens']);
        });
        Route::get('audit', [AuditController::class, 'index'])->middleware('permission:audit.read');

        // Education: Alimni teachers (contracts, salaries), the madrasa and partner markaz, and the curriculum
        Route::prefix('edu')->middleware('partner')->group(function () {
            $c = \App\Http\Controllers\Api\EducationController::class;
            Route::middleware('permission:education.view')->group(function () use ($c) {
                Route::get('summary', [$c, 'summary']);
                Route::get('teachers', [$c, 'teachers']);
                Route::get('teachers/{teacher}', [$c, 'teacher'])->whereNumber('teacher');
                Route::get('institutions', [$c, 'institutions']);
                Route::get('institutions/{institution}', [$c, 'institution'])->whereNumber('institution');
                Route::get('curricula', [$c, 'curricula']);
                Route::get('curricula/{curriculum}', [$c, 'curriculum'])->whereNumber('curriculum');
            });
            Route::middleware('permission:education.manage')->group(function () use ($c) {
                Route::post('teachers', [$c, 'storeTeacher']);
                Route::patch('teachers/{teacher}', [$c, 'updateTeacher'])->whereNumber('teacher');
                Route::post('teachers/{teacher}/contracts', [$c, 'storeContract'])->whereNumber('teacher');
                Route::patch('contracts/{contract}', [$c, 'updateContract'])->whereNumber('contract');
                Route::put('teachers/{teacher}/progress/{unit}', [$c, 'setProgress'])->whereNumber(['teacher', 'unit']);
                Route::post('institutions', [$c, 'storeInstitution']);
                Route::patch('institutions/{institution}', [$c, 'updateInstitution'])->whereNumber('institution');
                Route::post('curricula', [$c, 'storeCurriculum']);
                Route::patch('curricula/{curriculum}', [$c, 'updateCurriculum'])->whereNumber('curriculum');
                Route::post('curricula/{curriculum}/units', [$c, 'storeUnit'])->whereNumber('curriculum');
                Route::patch('units/{unit}', [$c, 'updateUnit'])->whereNumber('unit');
                Route::delete('units/{unit}', [$c, 'deleteUnit'])->whereNumber('unit');
            });
            Route::middleware('permission:education.salary')->group(function () use ($c) {
                Route::get('salaries', [$c, 'salaries']);
                Route::post('salaries/generate', [$c, 'generateSalaries'])->middleware('throttle:20,1');
                Route::put('teachers/{teacher}/salaries/{month}', [$c, 'setSalary'])->whereNumber('teacher');
            });
        });

        // Work tracker (HR and the President): how staff use the app
        Route::middleware('permission:work.view')->prefix('work')->group(function () {
            $w = \App\Http\Controllers\Api\WorkTrackerController::class;
            Route::get('summary', [$w, 'summary']);
            Route::get('staff/{user}', [$w, 'staffDetail'])->whereNumber('user');
            Route::post('samples', [$w, 'loadSamples']);
            Route::delete('samples', [$w, 'removeSamples']);
        });

        // Attendance: clock in and out with a face photo, the office board, employees, records, leaves, holidays, payroll
        Route::prefix('attendance')->group(function () {
            $c = \App\Http\Controllers\Api\AttendanceController::class;
            Route::middleware('permission:attendance.clock')->group(function () use ($c) {
                Route::get('me', [$c, 'me']);
                Route::post('clock', [$c, 'clock'])->middleware(['throttle:30,1', 'block.uploads']);
            });
            Route::get('records', [$c, 'records'])->middleware('permission:attendance.clock,attendance.manage');
            Route::get('records/{record}/photo/{which}', [$c, 'photo'])->middleware('permission:attendance.clock,attendance.manage')->whereNumber('record');
            Route::middleware('permission:attendance.manage')->group(function () use ($c) {
                Route::get('board', [$c, 'board']);
                Route::get('employees', [$c, 'employees']);
                Route::post('employees', [$c, 'storeEmployee']);
                Route::patch('employees/{employee}', [$c, 'updateEmployee'])->whereNumber('employee');
                Route::post('employees/{employee}/face', [$c, 'enrollFace'])->middleware(['throttle:30,1', 'block.uploads'])->whereNumber('employee');
                Route::get('employees/{employee}/face', [$c, 'face'])->whereNumber('employee');
                Route::put('records', [$c, 'setRecord']);
                Route::get('leaves', [$c, 'leaves']);
                Route::post('leaves', [$c, 'storeLeave']);
                Route::delete('leaves/{leave}', [$c, 'deleteLeave'])->whereNumber('leave');
                Route::get('holidays', [$c, 'holidays']);
                Route::post('holidays', [$c, 'storeHoliday']);
                Route::delete('holidays/{holiday}', [$c, 'deleteHoliday'])->whereNumber('holiday');
                Route::post('samples', [$c, 'loadSamples']);
                Route::delete('samples', [$c, 'removeSamples']);
            });
            Route::get('payroll', [$c, 'payroll'])->middleware('permission:attendance.salary');
        });

        // Office chat: the General group and one-to-one chats (President Admin and staff). Cards are links, opened by each person's own access.
        Route::middleware('permission:chat.use')->group(function () {
            Route::get('chat/conversations', [\App\Http\Controllers\Api\ChatController::class, 'conversations']);
            Route::get('chat/unread', [\App\Http\Controllers\Api\ChatController::class, 'unread']);
            Route::get('chat/people', [\App\Http\Controllers\Api\ChatController::class, 'people']);
            Route::post('chat/direct', [\App\Http\Controllers\Api\ChatController::class, 'direct']);
            Route::post('chat/groups', [\App\Http\Controllers\Api\ChatController::class, 'createGroup'])->middleware('throttle:20,1');
            Route::get('chat/conversations/{conversation}/info', [\App\Http\Controllers\Api\ChatController::class, 'info']);
            Route::patch('chat/conversations/{conversation}', [\App\Http\Controllers\Api\ChatController::class, 'update'])->middleware('throttle:30,1');
            Route::get('chat/conversations/{conversation}/cover', [\App\Http\Controllers\Api\ChatController::class, 'cover']);
            Route::post('chat/conversations/{conversation}/cover', [\App\Http\Controllers\Api\ChatController::class, 'setCover'])->middleware(['throttle:10,1', 'block.uploads']);
            Route::delete('chat/conversations/{conversation}/cover', [\App\Http\Controllers\Api\ChatController::class, 'removeCover']);
            Route::post('chat/conversations/{conversation}/members', [\App\Http\Controllers\Api\ChatController::class, 'addMembers'])->middleware('throttle:30,1');
            Route::delete('chat/conversations/{conversation}/members/{user}', [\App\Http\Controllers\Api\ChatController::class, 'removeMember'])->whereNumber('user');
            Route::get('chat/conversations/{conversation}/messages', [\App\Http\Controllers\Api\ChatController::class, 'messages']);
            Route::get('chat/conversations/{conversation}/audience', [\App\Http\Controllers\Api\ChatController::class, 'audience']);
            Route::post('chat/conversations/{conversation}/messages', [\App\Http\Controllers\Api\ChatController::class, 'send'])->middleware('throttle:90,1');
        });

        // Applications from the public form (Aytam staff)
        Route::middleware(['partner', 'permission:applications.review'])->group(function () {
            Route::get('applications', [\App\Http\Controllers\Api\ApplicationController::class, 'index']);
            Route::get('applications/{application}', [\App\Http\Controllers\Api\ApplicationController::class, 'show']);
            Route::get('applications/{application}/photos/{n}', [\App\Http\Controllers\Api\ApplicationController::class, 'photo'])->whereNumber('n');
            Route::post('applications/{application}/approve', [\App\Http\Controllers\Api\ApplicationController::class, 'approve']);
            Route::post('applications/{application}/reject', [\App\Http\Controllers\Api\ApplicationController::class, 'reject']);
        });

        // Office: children (Aytam), inside the partner the app works in
        Route::middleware('partner')->group(function () {
            Route::get('children', [ChildController::class, 'index'])->middleware('permission:children.view');
            Route::delete('children/samples', [ChildController::class, 'removeSamples'])->middleware('permission:children.update');
            Route::get('children/accounts', [\App\Http\Controllers\Api\OrphanAccountController::class, 'summary'])->middleware('permission:orphans.accounts');
            Route::get('children/{child}', [ChildController::class, 'show'])->middleware('permission:children.view');
            Route::get('children/{child}/photos/{photo}', [ChildController::class, 'photo'])->middleware('permission:children.view')->whereNumber('photo');
            Route::post('children/{child}/photos', [ChildController::class, 'addPhoto'])->middleware(['permission:children.update', 'block.uploads']);
            Route::delete('children/{child}/photos/{photo}', [ChildController::class, 'removePhoto'])->middleware('permission:children.update')->whereNumber('photo');
            Route::get('applications/{application}/documents/{n}', [\App\Http\Controllers\Api\ApplicationController::class, 'document'])->middleware('permission:applications.review')->whereNumber('n');
            Route::post('children/accounts', [\App\Http\Controllers\Api\OrphanAccountController::class, 'store'])->middleware(['permission:orphans.accounts', 'throttle:60,1']);
            Route::post('children/{child}/account', [\App\Http\Controllers\Api\OrphanAccountController::class, 'reset'])->middleware(['permission:orphans.accounts', 'throttle:30,1']);
            Route::post('children/import', [\App\Http\Controllers\Api\ChildImportController::class, 'store'])->middleware(['permission:children.create', 'block.uploads', 'throttle:20,1']);
            Route::post('children', [ChildController::class, 'store'])->middleware('permission:children.create');
            Route::patch('children/{child}', [ChildController::class, 'update'])->middleware('permission:children.update');
            Route::put('children/{child}/bank/verification', [BankVerificationController::class, 'update'])->middleware('permission:bank.verify');
            Route::get('children/{child}/documents', [ChildDocumentController::class, 'index'])->middleware('permission:documents.view');
            Route::get('children/{child}/documents/{type}/download', [ChildDocumentController::class, 'download'])->middleware('permission:documents.view');
            Route::post('children/{child}/documents', [ChildDocumentController::class, 'store'])->middleware(['permission:documents.upload', 'block.uploads']);
        });

        // Office: money (Finance)
        Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
        Route::post('payments/{child}/mark-cash', [PaymentController::class, 'markCash'])->middleware('permission:payments.mark_cash');
        Route::get('reports/summary', [ReportController::class, 'summary'])->middleware('permission:reports.view');
        Route::get('reports/payments.csv', [ReportController::class, 'paymentsCsv'])->middleware('permission:reports.export');

        // Office: the public landing page (President Admin)
        Route::get('site', [\App\Http\Controllers\Api\SiteController::class, 'show'])->middleware('permission:site.edit');
        Route::put('site', [\App\Http\Controllers\Api\SiteController::class, 'save'])->middleware('permission:site.edit');
        Route::post('site/images', [\App\Http\Controllers\Api\SiteController::class, 'upload'])->middleware('permission:site.edit');
        Route::post('site/posts', [\App\Http\Controllers\Api\SiteController::class, 'post'])->middleware('permission:site.publish');
        Route::post('site/publish', [\App\Http\Controllers\Api\SiteController::class, 'publish'])->middleware('permission:site.publish');
        Route::post('site/revert/{version}', [\App\Http\Controllers\Api\SiteController::class, 'revert'])->middleware('permission:site.publish')->whereNumber('version');

        // Office: projects
        Route::get('projects', [ProjectController::class, 'index'])->middleware('permission:projects.view');
        Route::post('projects', [ProjectController::class, 'store'])->middleware('permission:projects.manage');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->middleware('permission:projects.manage');

        // Family account
        Route::get('my/family', FamilyController::class)->middleware('permission:family.view_own');
    });

    // A partner's report, with an API key instead of a login.
    Route::get('partner/summary', [PartnerController::class, 'summary'])->middleware(['api_key', 'throttle:60,1']);
});
