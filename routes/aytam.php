<?php

use App\Http\Controllers\Api\Aytam\AytamController;
use App\Http\Controllers\Api\Aytam\AytamDashboardController;
use App\Http\Controllers\Api\Aytam\AytamDocumentController;
use App\Http\Controllers\Api\Aytam\FamilyController;
use App\Http\Controllers\Api\Aytam\GuardianController;
use Illuminate\Support\Facades\Route;

/*
| The Aytam program module. Every route sits behind program:aytam,<permission>: the program must be an Aytam program of THIS
| foundation, the person needs the permission inside that program, and changes need an active program.
*/
Route::prefix('programs/{program}')->group(function () {
    Route::get('/aytam-dashboard', [AytamDashboardController::class, 'show'])->middleware('program:aytam,aytam.view');

    // records
    Route::get('/aytam', [AytamController::class, 'index'])->middleware('program:aytam,aytam.view');
    Route::post('/aytam', [AytamController::class, 'store'])->middleware('program:aytam,aytam.create');
    Route::get('/aytam/assignable', [AytamController::class, 'assignable'])->middleware('program:aytam,aytam.assign');
    Route::get('/aytam/{aytam}', [AytamController::class, 'show'])->middleware('program:aytam,aytam.view');
    Route::patch('/aytam/{aytam}', [AytamController::class, 'update'])->middleware('program:aytam,aytam.update');
    Route::post('/aytam/{aytam}/status', [AytamController::class, 'status'])->middleware('program:aytam,aytam.update,aytam.review');
    Route::put('/aytam/{aytam}/assignments', [AytamController::class, 'assign'])->middleware('program:aytam,aytam.assign');

    // documents
    Route::get('/aytam/{aytam}/documents', [AytamDocumentController::class, 'index'])->middleware('program:aytam,documents.view');
    Route::post('/aytam/{aytam}/documents', [AytamDocumentController::class, 'store'])->middleware(['program:aytam,documents.upload', 'throttle:documents']);
    Route::get('/documents/{document}/history', [AytamDocumentController::class, 'history'])->middleware('program:aytam,documents.view');
    Route::get('/documents/{document}/download', [AytamDocumentController::class, 'download'])->middleware('program:aytam,documents.view');
    Route::post('/documents/{document}/decision', [AytamDocumentController::class, 'decide'])->middleware('program:aytam,documents.verify');

    // families and guardians (whole-program data: reading needs "view all", writing needs "view all" + "update")
    Route::get('/families', [FamilyController::class, 'index'])->middleware('program:aytam,aytam.view_all');
    Route::get('/families/{family}', [FamilyController::class, 'show'])->middleware('program:aytam,aytam.view_all');
    Route::post('/families', [FamilyController::class, 'store'])->middleware('program:aytam,aytam.view_all+aytam.update');
    Route::patch('/families/{family}', [FamilyController::class, 'update'])->middleware('program:aytam,aytam.view_all+aytam.update');
    Route::delete('/families/{family}', [FamilyController::class, 'destroy'])->middleware('program:aytam,aytam.view_all+aytam.update');
    Route::get('/guardians', [GuardianController::class, 'index'])->middleware('program:aytam,aytam.view_all');
    Route::get('/guardians/{guardian}', [GuardianController::class, 'show'])->middleware('program:aytam,aytam.view_all');
    Route::post('/guardians', [GuardianController::class, 'store'])->middleware('program:aytam,aytam.view_all+aytam.update');
    Route::patch('/guardians/{guardian}', [GuardianController::class, 'update'])->middleware('program:aytam,aytam.view_all+aytam.update');
    Route::delete('/guardians/{guardian}', [GuardianController::class, 'destroy'])->middleware('program:aytam,aytam.view_all+aytam.update');

    require __DIR__.'/registration.php';
});
