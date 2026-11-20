<?php

use App\Http\Controllers\InstallController;
use Illuminate\Support\Facades\Route;

// Plain pages with no cookies or sessions: the one-time setup code from storage/app/install/token is the protection.
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/install', [InstallController::class, 'show']);
    Route::post('/install', [InstallController::class, 'run']);
    Route::post('/install/reconnect', [InstallController::class, 'reconnect']);
    Route::get('/upgrade', [InstallController::class, 'upgradeShow']);
    Route::post('/upgrade', [InstallController::class, 'upgradeRun']);
    Route::post('/upgrade/patch', [InstallController::class, 'patchCheck']);
    Route::post('/upgrade/apply', [InstallController::class, 'patchApply']);
    Route::post('/upgrade/undo', [InstallController::class, 'patchUndo']);
});
