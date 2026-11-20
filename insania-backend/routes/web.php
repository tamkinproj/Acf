<?php

use Illuminate\Support\Facades\Route;

// The address of the server folder itself. Staff use the app at /app/; this is only a friendly landing for people who open it.
Route::get('/', fn () => response('<!doctype html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Insania</title><body style="font:16px system-ui;margin:3rem 1.2rem;color:#17300d"><h1>Insania server is running</h1><p>This is the system behind the staff app. Open <a href="/app/">the staff app</a>.</p></body>', 200)->header('Content-Type', 'text/html; charset=UTF-8'));
