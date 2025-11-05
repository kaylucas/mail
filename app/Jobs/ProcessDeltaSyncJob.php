<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\EmailSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessDeltaSyncJob implements ShouldQueue
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
    public int $timeout = 300;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     *
     * @var int
     */
    public int $maxExceptions = 3;

    /**
     * The user to perform delta sync for.
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
    public function handle(EmailSyncService $emailSyncService): void
    {
        try {
            Log::info('ProcessDeltaSyncJob started', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job?->uuid(),
                'attempt' => $this->attempts(),
                'has_delta_token' => $this->user->hasDeltaToken(),
                'last_sync_at' => $this->user->last_email_sync_at
            ]);

            // Check if user has delta token
            if (!$this->user->hasDeltaToken()) {
                Log::warning('ProcessDeltaSyncJob skipped - no delta token', [
                    'user_id' => $this->user->id,
                    'message' => 'User must run initial sync first'
                ]);
                return;
            }

            // Check if user has Office365Connection
            if (!$this->user->office365Connection) {
                Log::warning('ProcessDeltaSyncJob skipped - no connection', [
                    'user_id' => $this->user->id
                ]);
                return;
            }

            // Check if connection is active
            if (!$this->user->office365Connection->is_active) {
                Log::warning('ProcessDeltaSyncJob skipped - connection inactive', [
                    'user_id' => $this->user->id,
                    'connection_id' => $this->user->office365Connection->id
                ]);
                return;
            }

            // Perform delta sync
            $result = $emailSyncService->processDeltaSync($this->user);

            Log::info('ProcessDeltaSyncJob completed', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job?->uuid(),
                'messages_created' => $result['messages_created'],
                'messages_updated' => $result['messages_updated'],
                'messages_deleted' => $result['messages_deleted'],
                'total_changes' => $result['messages_created'] + $result['messages_updated'] + $result['messages_deleted']
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessDeltaSyncJob failed', [
                'user_id' => $this->user->id,
                'job_uuid' => $this->job?->uuid(),
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
        Log::critical('ProcessDeltaSyncJob failed permanently', [
            'user_id' => $this->user->id,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);

        // Future: Send notification to user about sync failure
        // Future: Consider marking connection as needs attention
    }
}
