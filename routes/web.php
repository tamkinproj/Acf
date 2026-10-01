<?php

use App\Http\Controllers\Api\FoundationController;
use App\Models\Foundation;
use Illuminate\Support\Facades\Route;

// Public branding image (login page, offline shell). Storage stays private; PHP serves it.
Route::get('/assets/logo', [FoundationController::class, 'logo']);

// The browser client (offline-first PWA) is the next build phase. Until it lands, every non-API page
// resolves to this shell, which proves the install gate and sessions work end to end.
Route::get('/{any?}', fn () => view('shell', ['foundation' => Foundation::current()]))
    ->where('any', '^(?!api|install|up).*$');
