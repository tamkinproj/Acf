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

    Route::middleware(['auth:sanctum', 'idempotent'])->group(function () {
        // Any signed-in account
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('me/password', [AuthController::class, 'changePassword'])->middleware('throttle:6,1');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth_logout');
        Route::patch('me/profile', [AuthController::class, 'updateProfile'])->middleware('throttle:20,1');
        Route::post('me/avatar', [AuthController::class, 'setAvatar'])->middleware(['throttle:20,1', 'block.uploads']);
        Route::delete('me/avatar', [AuthController::class, 'removeAvatar']);
        Route::get('avatars/{user}', [AuthController::class, 'avatar'])->whereNumber('user');
        Route::get('me/preferences', [AuthController::class, 'preferences']);
        Route::patch('me/preferences', [AuthController::class, 'updatePreferences']);
        Route::get('dashboard', DashboardController::class);
        Route::get('setup', [\App\Http\Controllers\Api\SetupController::class, 'steps']);

        // Technical roles
        Route::get('system/health', [SystemController::class, 'health'])->middleware('permission:system.health');
        Route::get('system/settings', [SystemController::class, 'settings'])->middleware('permission:system.settings');
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
            Route::post('users', [UserAdminController::class, 'store']);
            Route::patch('users/{user}', [UserAdminController::class, 'update']);
            Route::post('users/{user}/password', [UserAdminController::class, 'resetPassword']);
            Route::post('users/{user}/revoke-tokens', [UserAdminController::class, 'revokeTokens']);
        });
        Route::get('audit', [AuditController::class, 'index'])->middleware('permission:audit.read');

        // Office chat: the General group and one-to-one chats (President Admin and staff). Cards are links, opened by each person's own access.
        Route::middleware('permission:chat.use')->group(function () {
            Route::get('chat/conversations', [\App\Http\Controllers\Api\ChatController::class, 'conversations']);
            Route::get('chat/unread', [\App\Http\Controllers\Api\ChatController::class, 'unread']);
            Route::get('chat/people', [\App\Http\Controllers\Api\ChatController::class, 'people']);
            Route::post('chat/direct', [\App\Http\Controllers\Api\ChatController::class, 'direct']);
            Route::get('chat/conversations/{conversation}/messages', [\App\Http\Controllers\Api\ChatController::class, 'messages']);
            Route::get('chat/conversations/{conversation}/audience', [\App\Http\Controllers\Api\ChatController::class, 'audience']);
            Route::post('chat/conversations/{conversation}/messages', [\App\Http\Controllers\Api\ChatController::class, 'send'])->middleware('throttle:90,1');
        });

        // Applications from the public form (Aytam staff)
        Route::middleware('permission:applications.review')->group(function () {
            Route::get('applications', [\App\Http\Controllers\Api\ApplicationController::class, 'index']);
            Route::get('applications/{application}', [\App\Http\Controllers\Api\ApplicationController::class, 'show']);
            Route::get('applications/{application}/photos/{n}', [\App\Http\Controllers\Api\ApplicationController::class, 'photo'])->whereNumber('n');
            Route::post('applications/{application}/approve', [\App\Http\Controllers\Api\ApplicationController::class, 'approve']);
            Route::post('applications/{application}/reject', [\App\Http\Controllers\Api\ApplicationController::class, 'reject']);
        });

        // Office: children (Aytam)
        Route::get('children', [ChildController::class, 'index'])->middleware('permission:children.view');
        Route::delete('children/samples', [ChildController::class, 'removeSamples'])->middleware('permission:children.update');
        Route::get('children/{child}', [ChildController::class, 'show'])->middleware('permission:children.view');
        Route::get('children/{child}/photos/{photo}', [ChildController::class, 'photo'])->middleware('permission:children.view')->whereNumber('photo');
        Route::post('children/{child}/photos', [ChildController::class, 'addPhoto'])->middleware(['permission:children.update', 'block.uploads']);
        Route::delete('children/{child}/photos/{photo}', [ChildController::class, 'removePhoto'])->middleware('permission:children.update')->whereNumber('photo');
        Route::get('applications/{application}/documents/{n}', [\App\Http\Controllers\Api\ApplicationController::class, 'document'])->middleware('permission:applications.review')->whereNumber('n');
        Route::post('children', [ChildController::class, 'store'])->middleware('permission:children.create');
        Route::patch('children/{child}', [ChildController::class, 'update'])->middleware('permission:children.update');
        Route::put('children/{child}/bank/verification', [BankVerificationController::class, 'update'])->middleware('permission:bank.verify');
        Route::get('children/{child}/documents', [ChildDocumentController::class, 'index'])->middleware('permission:documents.view');
        Route::get('children/{child}/documents/{type}/download', [ChildDocumentController::class, 'download'])->middleware('permission:documents.view');
        Route::post('children/{child}/documents', [ChildDocumentController::class, 'store'])->middleware(['permission:documents.upload', 'block.uploads']);

        // Office: money (Finance)
        Route::get('payments', [PaymentController::class, 'index'])->middleware('permission:payments.view');
        Route::post('payments/{child}/mark-cash', [PaymentController::class, 'markCash'])->middleware('permission:payments.mark_cash');
        Route::get('reports/summary', [ReportController::class, 'summary'])->middleware('permission:reports.view');
        Route::get('reports/payments.csv', [ReportController::class, 'paymentsCsv'])->middleware('permission:reports.export');

        // Office: the public landing page (President Admin)
        Route::get('site', [\App\Http\Controllers\Api\SiteController::class, 'show'])->middleware('permission:site.edit');
        Route::put('site', [\App\Http\Controllers\Api\SiteController::class, 'save'])->middleware('permission:site.edit');
        Route::post('site/images', [\App\Http\Controllers\Api\SiteController::class, 'upload'])->middleware('permission:site.edit');
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
