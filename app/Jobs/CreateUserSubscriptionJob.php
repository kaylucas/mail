<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\GraphSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CreateUserSubscriptionJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3600; // 1 hour

    /**
     * The user to create subscription for.
     */
    protected User $user;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;
    }

    /**
     * Get the unique ID for the job.
     */
    public function uniqueId(): string
    {
        return "create-subscription-{$this->user->id}";
    }

    /**
     * Execute the job.
     */
    public function handle(GraphSubscriptionService $subscriptionService): void
    {
        try {
            Log::info('CreateUserSubscriptionJob started', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job->uuid(),
                'attempt' => $this->attempts(),
            ]);

            // Check if user already has active subscription (idempotent)
            if ($this->user->activeEmailSubscription) {
                Log::warning('User already has active email subscription, skipping', [
                    'user_id' => $this->user->id,
                    'subscription_id' => $this->user->activeEmailSubscription->subscription_id,
                ]);

                return;
            }

            // Prepare subscription options
            $options = [
                'resource' => 'me/messages',
                'changeTypes' => ['created', 'updated', 'deleted'],
            ];

            // Create subscription
            $subscription = $subscriptionService->createSubscription($this->user, $options);

            Log::info('CreateUserSubscriptionJob completed', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job->uuid(),
                'subscription_id' => $subscription->subscription_id,
                'expires_at' => $subscription->expires_at,
            ]);
        } catch (\Exception $e) {
            Log::error('CreateUserSubscriptionJob failed', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job->uuid(),
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('CreateUserSubscriptionJob failed permanently', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Note: User can manually create subscription via API endpoint later
        // Future: Send notification to user about subscription failure
    }
}
