<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ConflictController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\FoundationController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\SystemController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
| Loaded under the "web" middleware group with prefix /api (see bootstrap/app.php): same-origin
| cookie session + CSRF, plus the optional device token. Three layers, kept separate:
|   public    - login page branding, auth
|   app       - UI endpoints (online administration)
|   sync      - /api/sync/*  device-authenticated replication, the only path that writes syncable data from clients
*/

// ---- public ----
Route::get('/system/status', [SystemController::class, 'status']);
Route::get('/auth/csrf', [AuthController::class, 'csrf']);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

// ---- authenticated (user session) ----
Route::middleware(['auth', 'account.usable', 'device'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'show']);
    Route::patch('/auth/profile', [AuthController::class, 'updateProfile']);
    Route::put('/auth/password', [AuthController::class, 'changePassword'])->middleware('throttle:password');
    Route::get('/auth/sessions', [AuthController::class, 'sessions']);
    Route::delete('/auth/sessions/{handle}', [AuthController::class, 'revokeSession']);

    Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->middleware('permission:dashboard.view');
    Route::get('/settings', [SystemController::class, 'settings'])->middleware('permission:settings.view');
    Route::get('/system/health', [SystemController::class, 'health'])->middleware('permission:settings.manage');

    Route::get('/foundation', [FoundationController::class, 'show'])->middleware('permission:foundation.view');
    Route::post('/foundation/logo', [FoundationController::class, 'uploadLogo'])->middleware(['permission:foundation.manage', 'throttle:upload']);
    Route::delete('/foundation/logo', [FoundationController::class, 'deleteLogo'])->middleware('permission:foundation.manage');

    Route::get('/users', [UserController::class, 'index'])->middleware('permission:users.view');
    Route::get('/users/{user}', [UserController::class, 'show'])->middleware('permission:users.view');
    Route::post('/users', [UserController::class, 'store'])->middleware('permission:users.manage');
    Route::patch('/users/{user}', [UserController::class, 'update'])->middleware('permission:users.manage');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->middleware('permission:users.manage');
    Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword'])->middleware(['permission:users.manage', 'throttle:credentials']);

    Route::get('/roles', [RoleController::class, 'index'])->middleware('permission:roles.view');
    Route::get('/permissions', [RoleController::class, 'permissions'])->middleware('permission:roles.view');
    Route::put('/roles/{role}/permissions', [RoleController::class, 'updatePermissions'])->middleware('permission:roles.manage');

    Route::get('/devices', [DeviceController::class, 'index'])->middleware('permission:devices.view');
    Route::get('/devices/current', [DeviceController::class, 'current'])->middleware('device:required');
    Route::post('/devices', [DeviceController::class, 'store'])->middleware('permission:devices.manage');
    Route::post('/devices/{device}/claim', [DeviceController::class, 'claim'])->middleware('permission:devices.manage');
    Route::patch('/devices/{device}', [DeviceController::class, 'update'])->middleware('permission:devices.manage');
    Route::post('/devices/{device}/rotate-token', [DeviceController::class, 'rotateToken'])->middleware('permission:devices.manage');
    Route::post('/devices/{device}/revoke', [DeviceController::class, 'revoke'])->middleware('permission:devices.manage');

    Route::get('/audit-logs', [AuditLogController::class, 'index'])->middleware('permission:audit.view');

    // ---- sync: user session AND a valid device token ----
    Route::prefix('sync')->middleware(['device:required', 'permission:sync.use'])->group(function () {
        Route::get('/status', [SyncController::class, 'status']);
        Route::get('/schema', [SyncController::class, 'schema']);
        Route::get('/pull', [SyncController::class, 'pull'])->middleware('throttle:sync-pull');
        Route::post('/push', [SyncController::class, 'push'])->middleware('throttle:sync-push');
        Route::get('/conflicts', [ConflictController::class, 'index']);
        Route::post('/conflicts/{conflict}/resolve', [ConflictController::class, 'resolve'])->middleware('permission:sync.manage');
    });
});
