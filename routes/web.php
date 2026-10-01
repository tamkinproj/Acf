<?php

use App\Http\Controllers\Api\FoundationController;
use App\Models\Foundation;
use Illuminate\Support\Facades\Route;

// Public branding image (login page, offline shell). Storage stays private; PHP serves it.
Route::get('/assets/logo', [FoundationController::class, 'logo']);

// The browser client. One HTML page; screens are routed client-side with #/hash, so it works the same from a
// domain root or a subfolder. (Installable-app features - service worker, manifest - are a later phase.)
Route::get('/{any?}', fn () => response()->view('shell', ['foundation' => Foundation::current()])->header('Cache-Control', 'no-cache'))
    ->where('any', '^(?!api(/|$)|install(/|$)|up$).*$');
