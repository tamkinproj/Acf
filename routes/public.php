<?php

use App\Http\Controllers\PublicRegistrationController;
use Illuminate\Support\Facades\Route;

/*
| Public registration links, mounted at /apply. Anyone with a link may open the form and submit it - nothing here needs an
| account - so every route is rate-limited per address, resolves its foundation from the link alone, and exposes nothing
| beyond what that one form asks for.
*/
Route::middleware('throttle:public-form')->group(function () {
    Route::get('/status/{accessToken}', [PublicRegistrationController::class, 'status'])->where('accessToken', '[A-Za-z0-9]{48}');
    Route::get('/{token}', [PublicRegistrationController::class, 'show'])->where('token', '[a-z0-9]{40}');
    Route::get('/{token}/done', [PublicRegistrationController::class, 'done'])->where('token', '[a-z0-9]{40}');
});
Route::middleware('throttle:public-submit')->group(function () {
    Route::post('/status/{accessToken}', [PublicRegistrationController::class, 'resubmit'])->where('accessToken', '[A-Za-z0-9]{48}');
    Route::post('/{token}', [PublicRegistrationController::class, 'submit'])->where('token', '[a-z0-9]{40}');
});
