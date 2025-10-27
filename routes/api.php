<?php

use App\Http\Controllers\MicrosoftAuthController;
use App\Http\Controllers\Office365ConnectionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Protected API Routes
Route::middleware('auth:sanctum')->group(function () {
    // Authentication Routes
    Route::post('/logout', [MicrosoftAuthController::class, 'logout']);
    Route::get('/user', [MicrosoftAuthController::class, 'user']);

    // Office365 Connection Management Routes
    Route::prefix('office365')->group(function () {
        Route::post('/connections', [Office365ConnectionController::class, 'store']);
        Route::get('/connections', [Office365ConnectionController::class, 'show']);
        Route::delete('/connections', [Office365ConnectionController::class, 'destroy']);
        // @deprecated - OAuth callback is now handled via web route /auth/microsoft/callback
        Route::get('/auth/url', [Office365ConnectionController::class, 'getAuthUrl']);
    });
});
