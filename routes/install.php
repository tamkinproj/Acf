<?php

use App\Http\Controllers\Install\InstallController;
use App\Http\Middleware\InstallerAccess;
use Illuminate\Support\Facades\Route;

/*
| The setup wizard. Mounted at /install by bootstrap/app.php. EnsureInstalled (global) already
| answers "This system is already installed." once the lock exists, so none of this is reachable
| after installation. InstallerAccess adds the one-time-token gate before it.
*/
Route::get('/token', [InstallController::class, 'tokenForm']);
Route::post('/token', [InstallController::class, 'tokenSubmit'])->middleware('throttle:20,1');

Route::middleware(InstallerAccess::class)->group(function () {
    Route::get('/', [InstallController::class, 'welcome']);
    Route::get('/requirements', [InstallController::class, 'requirements']);
    Route::post('/requirements', [InstallController::class, 'requirementsSave']);
    Route::get('/database', [InstallController::class, 'database']);
    Route::post('/database', [InstallController::class, 'databaseSave'])->middleware('throttle:30,1');
    Route::get('/system', [InstallController::class, 'system']);
    Route::post('/system', [InstallController::class, 'systemSave']);
    Route::get('/foundation', [InstallController::class, 'foundation']);
    Route::post('/foundation', [InstallController::class, 'foundationSave']);
    Route::get('/admin', [InstallController::class, 'admin']);
    Route::post('/admin', [InstallController::class, 'adminSave']);
    Route::get('/device', [InstallController::class, 'device']);
    Route::post('/device', [InstallController::class, 'deviceSave']);
    Route::get('/review', [InstallController::class, 'review']);
    Route::post('/run', [InstallController::class, 'run'])->middleware('throttle:6,1');
});
