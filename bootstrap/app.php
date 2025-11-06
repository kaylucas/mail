<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Load API routes BEFORE web routes to prevent catch-all from intercepting API calls
        api: __DIR__.'/../routes/api.php',
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        // Trust proxies (ngrok, load balancers, etc.)
        // This allows Laravel to properly handle X-Forwarded-* headers
        $middleware->trustProxies(at: '*');
    })
    ->withSchedule(function ($schedule): void {
        // Run delta sync every 30 minutes for all users with active connections
        $schedule->command('email:delta-sync --all')
            ->everyThirtyMinutes()
            ->withoutOverlapping()
            ->runInBackground();

        // Renew subscriptions expiring within 12 hours, run every hour
        $schedule->command('subscriptions:renew --hours=12')
            ->hourly()
            ->withoutOverlapping()
            ->runInBackground();

        // Clean up old processed webhook notifications (older than 30 days)
        $schedule->command('model:prune', ['--model' => 'App\\Models\\WebhookNotification'])
            ->daily()
            ->at('02:00');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
