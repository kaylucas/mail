<?php

use App\Http\Controllers\MicrosoftAuthController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Serve Vue SPA
Route::get('/', function () {
    return view('app');
});

// Microsoft OAuth Routes
Route::get('/auth/microsoft', [MicrosoftAuthController::class, 'redirect']);
Route::get('/auth/microsoft/callback', [MicrosoftAuthController::class, 'callback']);
Route::get('/auth/session', [MicrosoftAuthController::class, 'establishSession']);

// Microsoft Graph Webhook Routes (public, no auth)
// These endpoints must be publicly accessible for Microsoft to send notifications
// GET: validation request, POST: change notifications
Route::match(['get', 'post'], '/webhooks/microsoft/notifications', [WebhookController::class, 'handleNotification'])
    ->middleware(\App\Http\Middleware\ValidateWebhookSignature::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.microsoft.notifications');

Route::match(['get', 'post'], '/webhooks/microsoft/lifecycle', [WebhookController::class, 'handleLifecycleNotification'])
    ->middleware(\App\Http\Middleware\ValidateWebhookSignature::class)
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class])
    ->name('webhooks.microsoft.lifecycle');

// Catch-all route for Vue Router (must be last)
// Exclude API, webhooks, storage, and other Laravel reserved paths
Route::get('/{any}', function () {
    return view('app');
})->where('any', '(?!api|webhooks|storage|sanctum|up).*');
