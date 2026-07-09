<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;

require __DIR__.'/api/v1.php';

/*
|--------------------------------------------------------------------------
| Broadcasting Auth (API)
|--------------------------------------------------------------------------
|
| The default /broadcasting/auth route lives under the `web` middleware
| group, which enforces CSRF protection and session-based auth. Mobile
| clients authenticate with Sanctum Bearer tokens instead, so we
| register a token-friendly version under the API middleware.
|
*/

Route::post('/broadcasting/auth', function (Request $request) {
    return Broadcast::auth($request);
})->middleware('auth:sanctum');
