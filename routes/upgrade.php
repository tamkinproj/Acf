<?php

use App\Http\Controllers\Install\UpgradeController;
use App\Http\Middleware\InstallerAccess;
use Illuminate\Support\Facades\Route;

/*
| Finishes an upgrade on a host without a terminal. Mounted at /upgrade. EnsureInstalled sends every request here while the
| uploaded code is newer than the installed data. Protected by the same one-time token as the installer: only someone with
| file access to the server (the person who uploaded the update) can run it.
*/
Route::get('/token', [UpgradeController::class, 'tokenForm']);
Route::post('/token', [UpgradeController::class, 'tokenSubmit'])->middleware('throttle:installer-token');

Route::middleware(InstallerAccess::class.':upgrade')->group(function () {
    Route::get('/', [UpgradeController::class, 'show']);
    Route::post('/run', [UpgradeController::class, 'run'])->middleware('throttle:installer-run');
});
