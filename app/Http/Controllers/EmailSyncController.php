<?php

namespace App\Http\Controllers;

use App\Jobs\InitialEmailSyncJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class EmailSyncController extends Controller
{
    /**
     * Trigger initial email sync for authenticated user
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function initialSync(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();
            $user->load('office365Connection');

            Log::info('Initial sync requested', ['user_id' => $user->id]);

            // Check if connection exists and is active
            if (!$user->office365Connection || !$user->office365Connection->is_active) {
                return response()->json([
                    'message' => 'No active Office365 connection found. Please authenticate first.'
                ], 404);
            }

            // Check if user already has delta token
            if ($user->hasDeltaToken()) {
                return response()->json([
                    'message' => 'Initial sync already completed. Use delta sync endpoint for updates.'
                ], 409);
            }

            // Check if user already has active subscription
            if ($user->activeEmailSubscription) {
                return response()->json([
                    'message' => 'Email sync already configured. Subscription is active.'
                ], 409);
            }

            // Dispatch job and get job ID
            $jobId = app(\Illuminate\Contracts\Bus\Dispatcher::class)->dispatch(
                new InitialEmailSyncJob($user)
            );

            Log::info('Initial sync job dispatched', [
                'user_id' => $user->id,
                'job_id' => $jobId
            ]);

            return response()->json([
                'message' => 'Initial email sync started',
                'job_id' => $jobId,
                'status_url' => route('emails.sync.status', ['jobId' => $jobId])
            ], 202);
        } catch (\Exception $e) {
            Log::error('Failed to dispatch initial sync job', [
                'user_id' => $user->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'message' => 'Failed to start email sync',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get status of email sync job
     *
     * @param Request $request
     * @param string $jobId
     * @return JsonResponse
     */
    public function status(Request $request, string $jobId): JsonResponse
    {
        try {
            $user = auth()->user();

            Log::info('Job status check requested', [
                'user_id' => $user->id,
                'job_id' => $jobId
            ]);

            // Validate job ID format
            if (!is_numeric($jobId) && !preg_match('/^[a-f0-9\-]{36}$/i', $jobId)) {
                return response()->json([
                    'message' => 'Invalid job ID format'
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
                        'reserved_at' => $job->reserved_at
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

                $data['error'] = $errorMessage;
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
                if (!$data['has_delta_token'] && !$data['has_subscription']) {
                    return response()->json([
                        'message' => 'Job not found'
                    ], 404);
                }
            }

            Log::info('Job status retrieved', [
                'user_id' => $user->id,
                'job_id' => $jobId,
                'status' => $status
            ]);

            return response()->json([
                'job_id' => $jobId,
                'status' => $status,
                'message' => $message,
                'data' => $data,
                'checked_at' => now()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to check job status', [
                'user_id' => $user->id ?? null,
                'job_id' => $jobId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'message' => 'Failed to check job status',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
