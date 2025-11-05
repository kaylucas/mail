<?php

namespace App\Console\Commands;

use App\Models\GraphSubscription;
use App\Services\GraphSubscriptionService;
use Illuminate\Console\Command;

class RenewSubscriptionsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'subscriptions:renew
                            {--hours=12 : Renew subscriptions expiring within this many hours}
                            {--force : Force renewal of all active subscriptions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Renew Graph API subscriptions that are about to expire';

    /**
     * Execute the console command.
     */
    public function handle(GraphSubscriptionService $subscriptionService): int
    {
        $this->info('Starting subscription renewal...');

        $hours = (int) $this->option('hours');
        $force = $this->option('force');

        // Get subscriptions to renew
        if ($force) {
            $subscriptions = GraphSubscription::where('status', 'active')->get();
            $this->warn("Force mode: Renewing ALL {$subscriptions->count()} active subscriptions");
        } else {
            $expiresAt = now()->addHours($hours);
            $subscriptions = GraphSubscription::where('status', 'active')
                ->where('expires_at', '<=', $expiresAt)
                ->get();

            $this->info("Found {$subscriptions->count()} subscription(s) expiring within {$hours} hours");
        }

        if ($subscriptions->isEmpty()) {
            $this->info('No subscriptions need renewal');
            return self::SUCCESS;
        }

        // Renew each subscription
        $renewed = 0;
        $failed = 0;

        foreach ($subscriptions as $subscription) {
            $user = $subscription->user;

            if (!$user) {
                $this->error("→ Subscription {$subscription->subscription_id}: User not found");
                $failed++;
                continue;
            }

            if (!$user->office365Connection || !$user->office365Connection->is_active) {
                $this->error("→ User {$user->id}: No active Office365 connection");
                $failed++;
                continue;
            }

            $this->line("→ Renewing subscription {$subscription->subscription_id} for user {$user->id} ({$user->email})");

            try {
                $renewedSubscription = $subscriptionService->renewSubscription($subscription);

                $this->info("   ✓ Renewed successfully. New expiry: {$renewedSubscription->expires_at->format('Y-m-d H:i:s')}");
                $renewed++;
            } catch (\Exception $e) {
                $this->error("   ✗ Failed to renew: {$e->getMessage()}");
                $failed++;
            }
        }

        $this->newLine();
        $this->info("Renewal completed:");
        $this->line("  Renewed: {$renewed}");
        $this->line("  Failed:  {$failed}");
        $this->line("  Total:   {$subscriptions->count()}");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
