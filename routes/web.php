<?php

use App\Http\Controllers\Api\FoundationController;
use App\Models\Foundation;
use Illuminate\Support\Facades\Route;

// A foundation's logo, for its own signed-in people only (the sign-in page belongs to the platform and shows none).
// Storage stays private; PHP serves it.
Route::get('/assets/logo', [FoundationController::class, 'logo'])->middleware('tenancy:tenant');

// The browser client. One HTML page; screens are routed client-side with #/hash, so it works the same from a
// domain root or a subfolder. (Installable-app features - service worker, manifest - are a later phase.)
Route::get('/{any?}', fn () => response()->view('shell', ['foundation' => Foundation::current()])->header('Cache-Control', 'no-cache'))
    ->where('any', '^(?!api(/|$)|install(/|$)|upgrade(/|$)|up$).*$');
