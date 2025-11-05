<?php

namespace App\Console\Commands;

use App\Jobs\ProcessDeltaSyncJob;
use App\Models\User;
use Illuminate\Console\Command;

class RunDeltaSyncCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:delta-sync
                            {--user= : Specific user ID to sync}
                            {--all : Sync all users with active connections}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run delta sync for users with active Office365 connections';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Starting delta sync...');

        // Get users to sync
        if ($this->option('user')) {
            $users = User::where('id', $this->option('user'))
                ->whereNotNull('email_delta_token')
                ->whereHas('office365Connection', function ($query) {
                    $query->where('is_active', true);
                })
                ->get();

            if ($users->isEmpty()) {
                $this->error("User not found or doesn't have active connection with delta token");
                return self::FAILURE;
            }
        } elseif ($this->option('all')) {
            $users = User::whereNotNull('email_delta_token')
                ->whereHas('office365Connection', function ($query) {
                    $query->where('is_active', true);
                })
                ->get();
        } else {
            $this->error('Please specify --user=ID or --all');
            return self::FAILURE;
        }

        if ($users->isEmpty()) {
            $this->warn('No users found for delta sync');
            return self::SUCCESS;
        }

        $this->info("Found {$users->count()} user(s) to sync");

        // Dispatch jobs
        $dispatched = 0;
        foreach ($users as $user) {
            ProcessDeltaSyncJob::dispatch($user);
            $this->line("→ Dispatched delta sync for user {$user->id} ({$user->email})");
            $dispatched++;
        }

        $this->info("Successfully dispatched {$dispatched} delta sync job(s)");

        return self::SUCCESS;
    }
}
