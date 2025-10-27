<?php

use App\Http\Controllers\MicrosoftAuthController;
use Illuminate\Support\Facades\Route;

// Serve Vue SPA
Route::get('/', function () {
    return view('app');
});

// Microsoft OAuth Routes
Route::get('/auth/microsoft', [MicrosoftAuthController::class, 'redirect']);
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback']);

// Catch-all route for Vue Router (must be last)
Route::get('/{any}', function () {
    return view('app');
})->where('any', '.*');
