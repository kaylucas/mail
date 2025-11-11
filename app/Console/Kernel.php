<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        $schedule->command('email:check-reminders')
            ->dailyAt(config('email_rules.reminder_check_time', '09:00'))
            ->withoutOverlapping()
            ->onOneServer();

        // Renew webhook subscriptions expiring within 24 hours
        // Runs daily to ensure subscriptions never expire
        $schedule->command('subscriptions:renew --hours=24')
            ->daily()
            ->withoutOverlapping()
            ->onOneServer();

        // Ensure all users have webhook subscriptions
        // Runs weekly as a safety net for failed automatic creation
        $schedule->command('subscriptions:ensure')
            ->weekly()
            ->sundays()
            ->at('02:00')
            ->withoutOverlapping()
            ->onOneServer();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
