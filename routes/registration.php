<?php

use App\Http\Controllers\Api\Aytam\RegistrationFormController;
use App\Http\Controllers\Api\Aytam\RegistrationReviewController;
use Illuminate\Support\Facades\Route;

// ---- registration form builder ----
Route::get('/forms-catalogue', [RegistrationFormController::class, 'catalogue'])->middleware('program:aytam,forms.view');
Route::get('/forms', [RegistrationFormController::class, 'index'])->middleware('program:aytam,forms.view');
Route::post('/forms', [RegistrationFormController::class, 'store'])->middleware('program:aytam,forms.create');
Route::get('/forms/{form}', [RegistrationFormController::class, 'show'])->middleware('program:aytam,forms.view');
Route::patch('/forms/{form}', [RegistrationFormController::class, 'update'])->middleware('program:aytam,forms.update');
Route::put('/forms/{form}/structure', [RegistrationFormController::class, 'structure'])->middleware('program:aytam,forms.update');
Route::get('/forms/{form}/check', [RegistrationFormController::class, 'check'])->middleware('program:aytam,forms.view');
Route::post('/forms/{form}/publish', [RegistrationFormController::class, 'publish'])->middleware('program:aytam,forms.publish');
Route::post('/forms/{form}/unpublish', [RegistrationFormController::class, 'unpublish'])->middleware('program:aytam,forms.publish');
Route::post('/forms/{form}/regenerate-link', [RegistrationFormController::class, 'regenerateLink'])->middleware('program:aytam,forms.publish');
Route::delete('/forms/{form}', [RegistrationFormController::class, 'destroy'])->middleware('program:aytam,forms.update');

// ---- registration review ----
Route::get('/registrations', [RegistrationReviewController::class, 'index'])->middleware('program:aytam,aytam.review');
Route::get('/registrations/{registration}', [RegistrationReviewController::class, 'show'])->middleware('program:aytam,aytam.review');
Route::post('/registrations/{registration}/approve', [RegistrationReviewController::class, 'approve'])->middleware('program:aytam,aytam.review');
Route::post('/registrations/{registration}/send-back', [RegistrationReviewController::class, 'sendBack'])->middleware('program:aytam,aytam.review');
