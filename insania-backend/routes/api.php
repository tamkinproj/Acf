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
    Route::get('ping', fn () => \App\Support\ApiResponse::ok(['installed' => \App\Install\InstallState::installed()]));
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth_login');

    Route::middleware(['auth:sanctum', 'idempotent'])->group(function () {
        // Any signed-in account
        Route::get('me', [AuthController::class, 'me'])->name('me');
        Route::post('logout', [AuthController::class, 'logout'])->name('auth_logout');
        Route::get('me/preferences', [AuthController::class, 'preferences']);
        Route::patch('me/preferences', [AuthController::class, 'updatePreferences']);
        Route::get('dashboard', DashboardController::class);

        // Technical roles
        Route::get('system/health', [SystemController::class, 'health'])->middleware('permission:system.health');
        Route::get('system/settings', [SystemController::class, 'settings'])->middleware('permission:system.settings');
        Route::patch('system/settings', [SystemController::class, 'updateSettings'])->middleware('permission:system.settings');
        Route::middleware('permission:system.api_keys')->group(function () {
            Route::get('system/api-keys', [\App\Http\Controllers\Api\ApiKeyController::class, 'index']);
            Route::post('system/api-keys', [\App\Http\Controllers\Api\ApiKeyController::class, 'store']);
            Route::delete('system/api-keys/{id}', [\App\Http\Controllers\Api\ApiKeyController::class, 'destroy'])->whereNumber('id');
        });

        Route::middleware('permission:users.manage')->group(function () {
            Route::get('users', [UserAdminController::class, 'index']);
            Route::post('users', [UserAdminController::class, 'store']);
            Route::patch('users/{user}', [UserAdminController::class, 'update']);
            Route::post('users/{user}/password', [UserAdminController::class, 'resetPassword']);
            Route::post('users/{user}/revoke-tokens', [UserAdminController::class, 'revokeTokens']);
        });
        Route::get('audit', [AuditController::class, 'index'])->middleware('permission:audit.read');

        // Office: children (Aytam)
        Route::get('children', [ChildController::class, 'index'])->middleware('permission:children.view');
        Route::get('children/{child}', [ChildController::class, 'show'])->middleware('permission:children.view');
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
