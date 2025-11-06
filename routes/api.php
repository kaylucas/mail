<?php

use App\Http\Controllers\EmailController;
use App\Http\Controllers\EmailSyncController;
use App\Http\Controllers\MicrosoftAuthController;
use App\Http\Controllers\Office365ConnectionController;
use App\Http\Controllers\SubscriptionController;
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

    // Subscription Management Routes
    Route::prefix('subscriptions')->group(function () {
        Route::post('/', [SubscriptionController::class, 'store'])->name('subscriptions.store');
        Route::get('/current', [SubscriptionController::class, 'show'])->name('subscriptions.show');
        Route::post('/renew', [SubscriptionController::class, 'renew'])->name('subscriptions.renew');
        Route::delete('/', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');
    });

    // Email Sync Routes
    Route::prefix('emails/sync')->group(function () {
        Route::post('/initial', [EmailSyncController::class, 'initialSync'])
            ->name('emails.sync.initial')
            ->middleware('throttle:5,60');  // 5 sync requests per hour per user
        // Rate limited to 20 requests per minute to prevent polling abuse
        Route::get('/status', [EmailSyncController::class, 'currentStatus'])
            ->name('emails.sync.current_status')
            ->middleware('throttle:20,1');
        Route::get('/status/{jobId}', [EmailSyncController::class, 'status'])->name('emails.sync.status');
    });

    // Email Management Routes
    Route::prefix('emails')->group(function () {
        Route::get('/', [EmailController::class, 'index'])->name('emails.index');
        Route::get('/stats', [EmailController::class, 'stats'])->name('emails.stats');
        Route::get('/folders', [EmailController::class, 'folders'])->name('emails.folders');
        Route::get('/{id}', [EmailController::class, 'show'])->name('emails.show');
        Route::patch('/{id}/read', [EmailController::class, 'updateReadStatus'])->name('emails.updateReadStatus');
    });
});
