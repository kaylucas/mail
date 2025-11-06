<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EmailSyncService;
use App\Services\GraphSubscriptionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class InitialEmailSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 600;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The number of seconds after which the job's unique lock will be released.
     */
    public int $uniqueFor = 3600;

    /**
     * The user to sync emails for.
     */
    protected User $user;

    /**
     * Optional OData filter for date range filtering.
     */
    protected ?string $filter = null;

    /**
     * Create a new job instance.
     */
    public function __construct(User $user, ?string $filter = null)
    {
        $this->user = $user;
        $this->filter = $filter;
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
                'attempt' => $this->attempts(),
                'filter' => $this->filter,
            ]);

            // Check if user has Office365Connection
            if (! $this->user->office365Connection) {
                throw new \Exception("User {$this->user->id} has no Office365 connection");
            }

            // Perform initial sync (with optional filter)
            $result = $emailSyncService->initialSync($this->user, $this->filter);

            $messagesSynced = $result['messages_synced'];
            $foldersSynced = $result['folders_synced'];
            $deltaToken = $result['delta_token'];

            Log::info('Initial sync completed in job', [
                'user_id' => $this->user->id,
                'messages_synced' => $messagesSynced,
                'folders_synced' => $foldersSynced,
                'has_delta_token' => ! empty($deltaToken),
            ]);

            // Clear current_sync_job_id and update sync completion time
            $this->user->update([
                'current_sync_job_id' => null,
                'sync_started_at' => null,
            ]);

            // Clean up progress cache after completion
            Cache::forget("sync_progress_{$this->user->id}");

            // Dispatch subscription creation job
            CreateUserSubscriptionJob::dispatch($this->user);

            Log::info('InitialEmailSyncJob completed', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job->uuid(),
                'messages_synced' => $messagesSynced,
                'folders_synced' => $foldersSynced,
            ]);
        } catch (\Exception $e) {
            Log::error('InitialEmailSyncJob failed', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job->uuid(),
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Clear job tracking if this is the last attempt
            if ($this->attempts() >= $this->tries) {
                $this->user->update([
                    'current_sync_job_id' => null,
                    'sync_started_at' => null,
                ]);
                Cache::forget("sync_progress_{$this->user->id}");
            }

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Get the unique ID for the job.
     * Prevents duplicate sync jobs for the same user.
     */
    public function uniqueId(): string
    {
        return "initial-email-sync-{$this->user->id}";
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('InitialEmailSyncJob failed permanently', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Future: Send notification to user
        // Future: Clear partial sync data if needed
    }
}
