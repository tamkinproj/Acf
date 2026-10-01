<?php

use App\Http\Controllers\Api\OrganizationController;
use App\Http\Controllers\Api\ProgramController;
use App\Http\Controllers\Api\ProgramOrganizationController;
use App\Http\Controllers\Api\ProgramTeamController;
use Illuminate\Support\Facades\Route;

// ---- organizations (foundation-level master) ----
Route::get('/organizations', [OrganizationController::class, 'index'])->middleware('permission:organizations.view');
Route::get('/organizations/{organization}', [OrganizationController::class, 'show'])->middleware('permission:organizations.view');
Route::post('/organizations', [OrganizationController::class, 'store'])->middleware('permission:organizations.create');
Route::patch('/organizations/{organization}', [OrganizationController::class, 'update'])->middleware('permission:organizations.update');
Route::delete('/organizations/{organization}', [OrganizationController::class, 'destroy'])->middleware('permission:organizations.update');
Route::post('/organizations/{organization}/contacts', [OrganizationController::class, 'storeContact'])->middleware('permission:organizations.update');
Route::patch('/organizations/{organization}/contacts/{contact}', [OrganizationController::class, 'updateContact'])->middleware('permission:organizations.update');
Route::delete('/organizations/{organization}/contacts/{contact}', [OrganizationController::class, 'destroyContact'])->middleware('permission:organizations.update');

// ---- programs (a program is visible to people with programs.view, and to its own team) ----
Route::get('/program-types', [ProgramController::class, 'types']);
Route::get('/programs', [ProgramController::class, 'index']);
Route::post('/programs', [ProgramController::class, 'store'])->middleware('permission:programs.create');
Route::get('/programs/{program}', [ProgramController::class, 'show']);
Route::patch('/programs/{program}', [ProgramController::class, 'update'])->middleware('permission:programs.update');
Route::post('/programs/{program}/status', [ProgramController::class, 'status'])->middleware('permission:programs.activate');

Route::get('/programs/{program}/team', [ProgramTeamController::class, 'index']);
Route::put('/programs/{program}/team/{user}', [ProgramTeamController::class, 'upsert'])->middleware('permission:programs.update');
Route::delete('/programs/{program}/team/{user}', [ProgramTeamController::class, 'destroy'])->middleware('permission:programs.update');

Route::get('/programs/{program}/organizations', [ProgramOrganizationController::class, 'index']);
Route::post('/programs/{program}/organizations', [ProgramOrganizationController::class, 'store']);
Route::patch('/programs/{program}/organizations/{link}', [ProgramOrganizationController::class, 'update']);
Route::delete('/programs/{program}/organizations/{link}', [ProgramOrganizationController::class, 'destroy']);

// ---- program modules ----
require __DIR__.'/aytam.php';
