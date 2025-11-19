<?php

namespace App\Jobs;

use App\Models\Email;
use App\Models\WebhookNotification;
use App\Services\EmailSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessWebhookNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 120;

    /**
     * The maximum number of unhandled exceptions to allow before failing.
     */
    public int $maxExceptions = 3;

    /**
     * The webhook notification to process.
     */
    protected WebhookNotification $notification;

    /**
     * Create a new job instance.
     */
    public function __construct(WebhookNotification $notification)
    {
        $this->notification = $notification;
        // Jobs will use the default queue
    }

    /**
     * Execute the job.
     */
    public function handle(EmailSyncService $emailSyncService): void
    {
        try {
            Log::info('ProcessWebhookNotificationJob started', [
                'notification_id' => $this->notification->id,
                'subscription_id' => $this->notification->subscription_id,
                'change_type' => $this->notification->change_type,
                'job_uuid' => $this->job?->uuid(),
                'attempt' => $this->attempts(),
            ]);

            // Get subscription and user
            $subscription = $this->notification->subscription;
            if (! $subscription) {
                Log::warning('Notification subscription not found', [
                    'notification_id' => $this->notification->id,
                    'subscription_id' => $this->notification->subscription_id,
                ]);
                $this->notification->markAsProcessed();

                return;
            }

            $user = $subscription->user;
            if (! $user) {
                Log::warning('Subscription user not found', [
                    'notification_id' => $this->notification->id,
                    'subscription_id' => $this->notification->subscription_id,
                    'user_id' => $subscription->user_id,
                ]);
                $this->notification->markAsProcessed();

                return;
            }

            // Extract message ID from resource
            // Resource format: "Users/{user_id}/Messages/{message_id}"
            $messageId = $this->extractMessageId($this->notification->resource);
            if (! $messageId) {
                Log::error('Failed to extract message ID from resource', [
                    'notification_id' => $this->notification->id,
                    'resource' => $this->notification->resource,
                ]);
                $this->notification->markAsProcessed();

                return;
            }

            // Handle different change types
            switch ($this->notification->change_type) {
                case 'created':
                case 'updated':
                    Log::info('Syncing message from webhook notification', [
                        'notification_id' => $this->notification->id,
                        'change_type' => $this->notification->change_type,
                        'message_id' => $messageId,
                        'user_id' => $user->id,
                    ]);

                    // Fetch and store the message
                    // IMPORTANT: Pass true for isWebhookSync to trigger email rules processing
                    $email = $emailSyncService->syncSingleMessage($user, $messageId, true);

                    if ($email) {
                        Log::info('Message synced successfully from webhook', [
                            'notification_id' => $this->notification->id,
                            'email_id' => $email->id,
                            'subject' => $email->subject,
                        ]);
                    } else {
                        Log::warning('Message not found when syncing from webhook', [
                            'notification_id' => $this->notification->id,
                            'message_id' => $messageId,
                        ]);
                    }
                    break;

                case 'deleted':
                    Log::info('Deleting message from webhook notification', [
                        'notification_id' => $this->notification->id,
                        'message_id' => $messageId,
                        'user_id' => $user->id,
                    ]);

                    // Delete the email from database
                    $deleted = Email::where('user_id', $user->id)
                        ->where('message_id', $messageId)
                        ->delete();

                    if ($deleted) {
                        Log::info('Message deleted successfully from webhook', [
                            'notification_id' => $this->notification->id,
                            'message_id' => $messageId,
                            'deleted_count' => $deleted,
                        ]);
                    } else {
                        Log::info('Message not found for deletion', [
                            'notification_id' => $this->notification->id,
                            'message_id' => $messageId,
                        ]);
                    }
                    break;

                default:
                    Log::warning('Unknown change type in webhook notification', [
                        'notification_id' => $this->notification->id,
                        'change_type' => $this->notification->change_type,
                    ]);
                    break;
            }

            // Mark notification as processed
            $this->notification->markAsProcessed();

            Log::info('ProcessWebhookNotificationJob completed', [
                'notification_id' => $this->notification->id,
                'job_uuid' => $this->job?->uuid(),
                'change_type' => $this->notification->change_type,
            ]);
        } catch (\Exception $e) {
            Log::error('ProcessWebhookNotificationJob failed', [
                'notification_id' => $this->notification->id,
                'job_uuid' => $this->job?->uuid(),
                'attempt' => $this->attempts(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw to trigger retry
            throw $e;
        }
    }

    /**
     * Extract message ID from Graph API resource string.
     *
     * Resource format examples:
     * - "Users/{user_id}/Messages/{message_id}"
     * - "Users/user@example.com/Messages/AAMkAD..."
     */
    private function extractMessageId(?string $resource): ?string
    {
        if (! $resource) {
            return null;
        }

        // Match pattern: Users/{anything}/Messages/{messageId}
        if (preg_match('/Users\/[^\/]+\/Messages\/([^\/]+)/i', $resource, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessWebhookNotificationJob failed permanently', [
            'notification_id' => $this->notification->id,
            'subscription_id' => $this->notification->subscription_id,
            'change_type' => $this->notification->change_type,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        // Mark as failed for manual review
        try {
            $this->notification->update([
                'processed_at' => now(),
                'error_message' => $exception->getMessage(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update notification status after job failure', [
                'notification_id' => $this->notification->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
