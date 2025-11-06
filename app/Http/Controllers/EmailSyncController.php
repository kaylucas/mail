<?php

namespace App\Http\Controllers;

use App\Jobs\InitialEmailSyncJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailSyncController extends Controller
{
    /**
     * Validate job ID format (UUID or numeric)
     */
    private function isValidJobId(string $jobId): bool
    {
        // UUID format: xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx
        // Or numeric ID
        return preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $jobId) === 1
            || ctype_digit($jobId);
    }

    /**
     * Trigger initial email sync for authenticated user
     */
    public function initialSync(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $user->load('office365Connection');

            Log::info('Initial sync requested', ['user_id' => $user->id]);

            // Check if connection exists and is active
            if (! $user->office365Connection || ! $user->office365Connection->is_active) {
                return response()->json([
                    'message' => 'No active Office365 connection found. Please authenticate first.',
                ], 404);
            }

            // Check if user already has delta token
            if ($user->hasDeltaToken()) {
                return response()->json([
                    'message' => 'Initial sync already completed. Use delta sync endpoint for updates.',
                ], 409);
            }

            // Check if user already has active subscription
            if ($user->activeEmailSubscription) {
                return response()->json([
                    'message' => 'Email sync already configured. Subscription is active.',
                ], 409);
            }

            // Dispatch job and get job ID
            $job = new InitialEmailSyncJob($user);
            $jobId = app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch($job);

            // Store job ID and sync started timestamp on user
            $user->update([
                'current_sync_job_id' => $jobId,
                'sync_started_at' => now(),
            ]);

            Log::info('Initial sync job dispatched', [
                'user_id' => $user->id,
                'job_id' => $jobId,
                'sync_started_at' => $user->sync_started_at,
            ]);

            return response()->json([
                'message' => 'Initial email sync started',
                'job_id' => $jobId,
                'status_url' => route('emails.sync.status', ['jobId' => $jobId]),
            ], 202);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch initial sync job', [
                'user_id' => $user->id ?? null,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to start email sync',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Get current sync status for authenticated user without requiring job ID.
     * This is the preferred endpoint for frontends to poll.
     */
    public function currentStatus(): JsonResponse
    {
        try {
            $user = auth()->user();

            // Check if user has delta token (sync completed at least once)
            if ($user->hasDeltaToken()) {
                return response()->json([
                    'status' => 'completed',
                    'message' => 'Email sync completed',
                    'synced_count' => $user->emails()->count(),
                    'last_sync_at' => $user->last_email_sync_at,
                    'has_emails' => $user->emails()->exists(),
                ]);
            }

            // Check if there's a current sync job
            $jobId = $user->current_sync_job_id;

            if (! $jobId) {
                return response()->json([
                    'status' => 'idle',
                    'message' => 'No sync in progress',
                    'has_emails' => $user->emails()->exists(),
                ]);
            }

            // Validate job ID format before querying
            if (! $this->isValidJobId($jobId)) {
                return response()->json([
                    'status' => 'unknown',
                    'message' => 'Invalid job ID format',
                    'has_emails' => $user->emails()->exists(),
                ]);
            }

            // Check if job is still in queue/processing (exact match only)
            $job = DB::table('jobs')
                ->where('id', $jobId)
                ->first();

            if ($job) {
                // Job is still queued or processing
                $progress = Cache::get("sync_progress_{$user->id}");

                return response()->json([
                    'status' => 'syncing',
                    'message' => 'Email sync in progress',
                    'job_id' => $jobId,
                    'started_at' => $user->sync_started_at,
                    'progress' => $progress ?? [
                        'folders_synced' => 0,
                        'messages_synced' => 0,
                        'current_page' => 0,
                    ],
                ]);
            }

            // Check if job failed (exact match only)
            $failedJob = DB::table('failed_jobs')
                ->where('uuid', $jobId)
                ->orWhere('id', $jobId)
                ->first();

            if ($failedJob) {
                // Parse exception to get error message
                $exceptionLines = explode("\n", $failedJob->exception);
                $errorMessage = $exceptionLines[0] ?? 'Unknown error';

                return response()->json([
                    'status' => 'failed',
                    'message' => 'Email sync failed',
                    'error' => $this->sanitizeErrorMessage($errorMessage),
                    'failed_at' => $failedJob->failed_at,
                ], 500);
            }

            // Job completed (not in jobs or failed_jobs)
            // Check if we have emails
            $emailCount = $user->emails()->count();

            if ($emailCount > 0) {
                return response()->json([
                    'status' => 'completed',
                    'message' => 'Email sync completed',
                    'synced_count' => $emailCount,
                    'completed_at' => $user->last_email_sync_at,
                    'has_emails' => true,
                ]);
            }

            // Job completed but no emails (shouldn't happen)
            return response()->json([
                'status' => 'completed',
                'message' => 'Sync completed with no emails',
                'synced_count' => 0,
                'has_emails' => false,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get sync status', [
                'user_id' => auth()->id(),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve sync status',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Get status of email sync job by job ID.
     * This is the legacy endpoint that requires a job ID.
     */
    public function status(Request $request, string $jobId): JsonResponse
    {
        try {
            $user = auth()->user();

            Log::info('Job status check requested', [
                'user_id' => $user->id,
                'job_id' => $jobId,
            ]);

            // Validate job ID format
            if (! is_numeric($jobId) && ! preg_match('/^[a-f0-9\-]{36}$/i', $jobId)) {
                return response()->json([
                    'message' => 'Invalid job ID format',
                ], 400);
            }

            // Query jobs table
            $job = DB::table('jobs')->where('id', $jobId)->first();

            // Query failed_jobs table (try both numeric ID and UUID)
            $failedJob = DB::table('failed_jobs')
                ->where('id', $jobId)
                ->orWhere('uuid', $jobId)
                ->first();

            $status = null;
            $message = null;
            $data = [];

            if ($job) {
                // Job exists in jobs table
                if ($job->reserved_at === null) {
                    $status = 'queued';
                    $message = 'Job is waiting to be processed';
                } else {
                    $status = 'processing';
                    $message = 'Job is currently running';
                    $data['progress'] = [
                        'attempts' => $job->attempts,
                        'reserved_at' => $job->reserved_at,
                    ];
                }
            } elseif ($failedJob) {
                // Job failed
                $status = 'failed';
                $message = 'Job failed';

                // Parse exception from payload
                $exception = $failedJob->exception;
                $exceptionLines = explode("\n", $exception);
                $errorMessage = $exceptionLines[0] ?? 'Unknown error';

                $data['error'] = $this->sanitizeErrorMessage($errorMessage);
                $data['failed_at'] = $failedJob->failed_at;
            } else {
                // Job not found in either table, assume completed
                $status = 'completed';
                $message = 'Job completed successfully';

                // Include additional data for completed jobs
                $data['has_delta_token'] = $user->hasDeltaToken();
                $data['last_sync_at'] = $user->last_email_sync_at;
                $data['has_subscription'] = $user->activeEmailSubscription !== null;
                $data['email_count'] = $user->emails()->count();
                $data['folder_count'] = $user->emailFolders()->count();

                // If user has no delta token and no subscription, job might not exist
                if (! $data['has_delta_token'] && ! $data['has_subscription']) {
                    return response()->json([
                        'message' => 'Job not found',
                    ], 404);
                }
            }

            Log::info('Job status retrieved', [
                'user_id' => $user->id,
                'job_id' => $jobId,
                'status' => $status,
            ]);

            return response()->json([
                'job_id' => $jobId,
                'status' => $status,
                'message' => $message,
                'data' => $data,
                'checked_at' => now(),
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to check job status', [
                'user_id' => $user->id ?? null,
                'job_id' => $jobId,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to check job status',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }
}
