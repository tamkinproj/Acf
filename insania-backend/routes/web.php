<?php

use Illuminate\Support\Facades\Route;

// The server folder shows nothing to a browser: no page here, so it answers like any address that does not exist.
Route::get('/', fn () => abort(404));
