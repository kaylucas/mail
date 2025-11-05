<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EmailSyncService;
use App\Services\GraphSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class InitialEmailSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public int $timeout = 600;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public int $maxExceptions = 3;

    /**
     * The user to sync emails for.
     *
     * @var User
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
     * Execute the job.
     */
    public function handle(EmailSyncService $emailSyncService, GraphSubscriptionService $subscriptionService): void
    {
        try {
            Log::info('InitialEmailSyncJob started', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job->uuid(),
                'attempt' => $this->attempts()
            ]);

            // Check if user has Office365Connection
            if (!$this->user->office365Connection) {
                throw new \Exception("User {$this->user->id} has no Office365 connection");
            }

            // Perform initial sync
            $result = $emailSyncService->initialSync($this->user);

            $messagesSynced = $result['messages_synced'];
            $foldersSynced = $result['folders_synced'];
            $deltaToken = $result['delta_token'];

            Log::info('Initial sync completed in job', [
                'user_id' => $this->user->id,
                'messages_synced' => $messagesSynced,
                'folders_synced' => $foldersSynced,
                'has_delta_token' => !empty($deltaToken)
            ]);

            // Dispatch subscription creation job
            CreateUserSubscriptionJob::dispatch($this->user);

            Log::info('InitialEmailSyncJob completed', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job->uuid(),
                'messages_synced' => $messagesSynced,
                'folders_synced' => $foldersSynced
            ]);
        } catch (\Exception $e) {
            Log::error('InitialEmailSyncJob failed', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job->uuid(),
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
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
        Log::critical('InitialEmailSyncJob failed permanently', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Future: Send notification to user
        // Future: Clear partial sync data if needed
    }
}
