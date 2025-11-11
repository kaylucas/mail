<?php

namespace App\Console\Commands;

use App\Jobs\CreateUserSubscriptionJob;
use App\Models\User;
use App\Services\GraphSubscriptionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EnsureUserSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'subscriptions:ensure
                            {--user= : Only check specific user by ID}
                            {--dry-run : Show what would be created without actually creating}
                            {--force : Recreate subscriptions even if active ones exist}';

    /**
     * The console command description.
     */
    protected $description = 'Ensure all users with Office365 connections have active webhook subscriptions';

    /**
     * Execute the console command.
     */
    public function handle(GraphSubscriptionService $subscriptionService): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $userId = $this->option('user');

        $this->info('Checking subscriptions for ' . ($userId ? "user {$userId}" : 'all users') . '...');
        $this->newLine();

        Log::info('EnsureUserSubscriptionsCommand started', [
            'dry_run' => $dryRun,
            'force' => $force,
            'user_id' => $userId,
        ]);

        // Track statistics
        $stats = [
            'total' => 0,
            'already_subscribed' => 0,
            'created' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];

        // Query users with Office365 connections
        $query = User::with(['office365Connection', 'activeEmailSubscription'])
            ->whereHas('office365Connection', function ($query) {
                $query->where('is_active', true);
            });

        // Filter by specific user if provided
        if ($userId) {
            $query->where('id', $userId);
        }

        $users = $query->get();

        $stats['total'] = $users->count();

        if ($users->isEmpty()) {
            $this->warn('No users found with active Office365 connections.');

            return self::SUCCESS;
        }

        // Process each user
        foreach ($users as $user) {
            try {
                $this->processUser($user, $subscriptionService, $dryRun, $force, $stats);
            } catch (\Exception $e) {
                $stats['errors']++;
                $this->error("User {$user->id} ({$user->email}): Error - {$e->getMessage()}");

                Log::error('EnsureUserSubscriptionsCommand: User processing failed', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
            }
        }

        // Display summary
        $this->newLine();
        $this->info('Summary:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Total users', $stats['total']],
                ['Already subscribed', $stats['already_subscribed']],
                ['Jobs dispatched', $stats['created']],
                ['Skipped (no connection)', $stats['skipped']],
                ['Errors', $stats['errors']],
            ]
        );

        Log::info('EnsureUserSubscriptionsCommand completed', $stats);

        if ($dryRun) {
            $this->info('Dry run completed. No changes made.');
        } else {
            $this->info('Done!');
        }

        // Return failure exit code if any errors
        return $stats['errors'] > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Process a single user.
     */
    private function processUser(
        User $user,
        GraphSubscriptionService $subscriptionService,
        bool $dryRun,
        bool $force,
        array &$stats
    ): void {
        $hasSubscription = $user->activeEmailSubscription !== null;

        // User already has subscription
        if ($hasSubscription && ! $force) {
            $stats['already_subscribed']++;
            $this->line("<info>User {$user->id} ({$user->email}):</info> ✓ Has active subscription");

            return;
        }

        // Force recreate subscription
        if ($hasSubscription && $force) {
            $this->line("<info>User {$user->id} ({$user->email}):</info> Force recreating subscription...");

            if (! $dryRun) {
                // Delete existing subscription
                try {
                    $subscriptionService->deleteSubscription($user->activeEmailSubscription);
                    $this->line("  <comment>→ Deleted existing subscription</comment>");
                } catch (\Exception $e) {
                    $this->warn("  <comment>→ Failed to delete existing subscription: {$e->getMessage()}</comment>");
                }
            }
        }

        // User needs subscription
        if (! $hasSubscription || $force) {
            $stats['created']++;
            $this->line("<info>User {$user->id} ({$user->email}):</info> ✗ No subscription, creating...");

            if (! $dryRun) {
                // Dispatch job to create subscription
                CreateUserSubscriptionJob::dispatch($user);
                $this->line('  <comment>→ Job dispatched: CreateUserSubscriptionJob</comment>');

                Log::info('EnsureUserSubscriptionsCommand: Job dispatched', [
                    'user_id' => $user->id,
                    'force' => $force,
                ]);
            } else {
                $this->line('  <comment>→ Dry run: Job would be dispatched</comment>');
            }
        }
    }
}
