<?php

use App\Http\Controllers\Api\Platform\PlatformActivityController;
use App\Http\Controllers\Api\Platform\PlatformDashboardController;
use App\Http\Controllers\Api\Platform\PlatformFoundationController;
use App\Http\Controllers\Api\Platform\PlatformSettingsController;
use App\Http\Controllers\Api\Platform\PlatformUserController;
use Illuminate\Support\Facades\Route;

// ---- platform administrators (no devices, no sync, no foundation records) ----
Route::prefix('platform')->middleware(['auth', 'tenancy:platform', 'account.usable'])->group(function () {
    Route::get('/dashboard', [PlatformDashboardController::class, 'summary'])->middleware('permission:platform.view');

    Route::get('/foundations', [PlatformFoundationController::class, 'index'])->middleware('permission:platform.view');
    Route::get('/foundations/{foundation}', [PlatformFoundationController::class, 'show'])->middleware('permission:platform.view');
    Route::post('/foundations', [PlatformFoundationController::class, 'store'])->middleware('permission:platform.foundations.manage');
    Route::patch('/foundations/{foundation}', [PlatformFoundationController::class, 'update'])->middleware('permission:platform.foundations.manage');
    Route::post('/foundations/{foundation}/status', [PlatformFoundationController::class, 'status'])->middleware('permission:platform.foundations.manage');
    Route::post('/foundations/{foundation}/administrators', [PlatformFoundationController::class, 'addAdmin'])->middleware(['permission:platform.foundations.manage', 'throttle:credentials']);
    Route::post('/foundations/{foundation}/administrators/{user}/reset-password', [PlatformFoundationController::class, 'resetAdminPassword'])->middleware(['permission:platform.foundations.manage', 'throttle:credentials']);

    Route::get('/users', [PlatformUserController::class, 'index'])->middleware('permission:platform.users.manage');
    Route::post('/users', [PlatformUserController::class, 'store'])->middleware('permission:platform.users.manage');
    Route::patch('/users/{user}', [PlatformUserController::class, 'update'])->middleware('permission:platform.users.manage');
    Route::delete('/users/{user}', [PlatformUserController::class, 'destroy'])->middleware('permission:platform.users.manage');
    Route::post('/users/{user}/reset-password', [PlatformUserController::class, 'resetPassword'])->middleware(['permission:platform.users.manage', 'throttle:credentials']);

    Route::get('/activity', [PlatformActivityController::class, 'index'])->middleware('permission:platform.view');
    Route::get('/settings', [PlatformSettingsController::class, 'index'])->middleware('permission:platform.view');
    Route::put('/settings', [PlatformSettingsController::class, 'update'])->middleware('permission:platform.settings.manage');
});
