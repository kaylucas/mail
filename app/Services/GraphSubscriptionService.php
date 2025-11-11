<?php

namespace App\Services;

use App\Models\GraphSubscription;
use App\Models\User;
use Exception;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class GraphSubscriptionService
{
    /**
     * Create a new GraphSubscriptionService instance.
     */
    public function __construct(
        private Office365Service $office365Service
    ) {}

    /**
     * Validate and sanitize expiration minutes value.
     * 
     * @param mixed $expirationMinutes The value to validate (could be string, int, null)
     * @return int Validated expiration minutes between 45 and 10,080
     * @throws \InvalidArgumentException If value cannot be converted to valid integer
     */
    private function validateExpirationMinutes(mixed $expirationMinutes): int
    {
        // Handle null or empty values
        if ($expirationMinutes === null || $expirationMinutes === '') {
            Log::warning('Expiration minutes is null or empty, using default', [
                'provided_value' => $expirationMinutes,
                'default' => 10070,
            ]);
            return 10070; // Default to maximum allowed (Microsoft's actual limit)
        }

        // Check if value is numeric
        if (!is_numeric($expirationMinutes)) {
            throw new \InvalidArgumentException(
                "Expiration minutes must be numeric, received: " . gettype($expirationMinutes) . " value: {$expirationMinutes}"
            );
        }

        // Cast to integer
        $minutes = (int) $expirationMinutes;

        // Microsoft Graph API limits: minimum 45 minutes, maximum 10,070 minutes (actual limit)
        // Note: Documentation says 10,080 (7 days), but API rejects values above 10,070
        if ($minutes < 45) {
            Log::warning('Expiration minutes below minimum, adjusting to 45', [
                'provided' => $minutes,
                'adjusted' => 45,
            ]);
            return 45;
        }

        if ($minutes > 10070) {
            Log::warning('Expiration minutes exceeds maximum, capping at 10,070', [
                'provided' => $minutes,
                'adjusted' => 10070,
            ]);
            return 10070;
        }

        return $minutes;
    }

    /**
     * Create a new Microsoft Graph subscription and store in database.
     */
    public function createSubscription(User $user, array $options = []): GraphSubscription
    {
        try {
            // Get user's Office365Connection
            $connection = $user->office365Connection;
            if (! $connection || ! $connection->is_active) {
                throw new Exception('No active Office365 connection found for user');
            }

            // Prepare subscription parameters with defaults
            $resource = $options['resource'] ?? 'me/messages';
            $changeTypes = $options['changeTypes'] ?? ['created', 'updated', 'deleted'];
            
            // Get expiration minutes from options or config with proper type handling
            $rawExpirationMinutes = $options['expirationMinutes'] 
                ?? config('services.microsoft_graph.subscription_expiration_minutes', 10080);
            
            // Validate and sanitize expiration minutes
            $expirationMinutes = $this->validateExpirationMinutes($rawExpirationMinutes);

            // Generate secure clientState
            $clientState = Str::random(32);

            // Build notification URL with validation
            $webhookBaseUrl = config('services.microsoft_graph.webhook_base_url');
            $notificationUrlPath = config('services.microsoft_graph.notification_url_path');

            if (empty($webhookBaseUrl) || ! is_string($webhookBaseUrl)) {
                throw new Exception('Webhook base URL is not configured or invalid. Set services.microsoft_graph.webhook_base_url in config.');
            }

            if (empty($notificationUrlPath) || ! is_string($notificationUrlPath)) {
                throw new Exception('Notification URL path is not configured or invalid. Set services.microsoft_graph.notification_url_path in config.');
            }

            $notificationUrl = rtrim($webhookBaseUrl, '/').'/'.ltrim($notificationUrlPath, '/');

            // Calculate expiration - now using validated integer
            $expiresAt = now()->addMinutes($expirationMinutes);

            // Prepare params for Office365Service
            $params = [
                'resource' => $resource,
                'changeType' => $changeTypes,
                'notificationUrl' => $notificationUrl,
                'expirationDateTime' => $expiresAt,
                'clientState' => $clientState,
            ];

            Log::info('Creating Microsoft Graph subscription', [
                'user_id' => $user->id,
                'resource' => $resource,
                'change_types' => $changeTypes,
                'expiration_minutes' => $expirationMinutes,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            // Create subscription in Microsoft Graph
            $response = $this->office365Service->createGraphSubscription($connection, $params);

            // Extract subscription ID from response
            $subscriptionId = $response['id'];

            // Create GraphSubscription record in database
            $subscription = GraphSubscription::create([
                'user_id' => $user->id,
                'office365_connection_id' => $connection->id,
                'subscription_id' => $subscriptionId,
                'resource' => $resource,
                'change_types' => $changeTypes,
                'notification_url' => $notificationUrl,
                'client_state' => $clientState,
                'expires_at' => $expiresAt,
                'status' => 'active',
            ]);

            Log::info('Successfully created Microsoft Graph subscription', [
                'user_id' => $user->id,
                'subscription_id' => $subscriptionId,
                'resource' => $resource,
                'expiration_minutes' => $expirationMinutes,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);

            return $subscription;
        } catch (Exception $e) {
            Log::error('Failed to create Microsoft Graph subscription', [
                'user_id' => $user->id,
                'resource' => $options['resource'] ?? 'me/messages',
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Renew an existing subscription before it expires.
     */
    public function renewSubscription(GraphSubscription $subscription): GraphSubscription
    {
        try {
            // Load relationships
            $subscription->load(['user', 'office365Connection']);

            // Verify subscription is renewable
            if ($subscription->status === 'failed') {
                throw new Exception('Cannot renew failed subscription. Please delete and create a new one.');
            }

            // Get Office365Connection
            $connection = $subscription->office365Connection;
            if (! $connection) {
                throw new Exception('Office365 connection not found for subscription');
            }

            // Get expiration minutes from config with proper type handling
            $rawExpirationMinutes = config('services.microsoft_graph.subscription_expiration_minutes', 10080);
            
            // Validate and sanitize expiration minutes
            $expirationMinutes = $this->validateExpirationMinutes($rawExpirationMinutes);

            // Calculate new expiration using validated integer
            $newExpiresAt = now()->addMinutes($expirationMinutes);

            Log::info('Renewing Microsoft Graph subscription', [
                'subscription_id' => $subscription->subscription_id,
                'user_id' => $subscription->user_id,
                'expiration_minutes' => $expirationMinutes,
                'old_expiration' => $subscription->expires_at->toIso8601String(),
                'new_expiration' => $newExpiresAt->toIso8601String(),
            ]);

            // Call Office365Service to renew subscription
            $this->office365Service->renewGraphSubscription(
                $connection,
                $subscription->subscription_id,
                $newExpiresAt->toIso8601String()
            );

            // Update database record
            $subscription->markAsRenewed($newExpiresAt);

            Log::info('Successfully renewed Microsoft Graph subscription', [
                'subscription_id' => $subscription->subscription_id,
                'user_id' => $subscription->user_id,
                'expiration_minutes' => $expirationMinutes,
                'new_expiration' => $newExpiresAt->toIso8601String(),
            ]);

            return $subscription->fresh();
        } catch (Exception $e) {
            // Mark subscription as failed
            $subscription->markAsFailed($e->getMessage());

            Log::error('Failed to renew Microsoft Graph subscription', [
                'subscription_id' => $subscription->subscription_id,
                'user_id' => $subscription->user_id,
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a subscription from Microsoft Graph and update database.
     */
    public function deleteSubscription(GraphSubscription $subscription): bool
    {
        try {
            // Load relationships
            $subscription->load(['user', 'office365Connection']);

            $connection = $subscription->office365Connection;

            // If connection exists, delete from Microsoft Graph
            if ($connection) {
                try {
                    $this->office365Service->deleteGraphSubscription(
                        $connection,
                        $subscription->subscription_id
                    );
                } catch (Exception $e) {
                    // Log error but continue to mark as expired in database
                    Log::warning('Failed to delete subscription from Microsoft Graph, marking as expired locally', [
                        'subscription_id' => $subscription->subscription_id,
                        'user_id' => $subscription->user_id,
                        'message' => $e->getMessage(),
                    ]);
                }
            } else {
                Log::warning('Office365 connection not found, marking subscription as expired locally', [
                    'subscription_id' => $subscription->subscription_id,
                    'user_id' => $subscription->user_id,
                ]);
            }

            // Update database - mark as expired
            $subscription->markAsExpired();

            Log::info('Successfully deleted Microsoft Graph subscription', [
                'subscription_id' => $subscription->subscription_id,
                'user_id' => $subscription->user_id,
                'resource' => $subscription->resource,
            ]);

            return true;
        } catch (Exception $e) {
            Log::error('Exception while deleting Microsoft Graph subscription', [
                'subscription_id' => $subscription->subscription_id,
                'user_id' => $subscription->user_id,
                'message' => $e->getMessage(),
            ]);

            // Still mark as expired in database for defensive approach
            $subscription->markAsExpired();

            return true;
        }
    }

    /**
     * Validate clientState from webhook notification matches stored value.
     */
    public function validateClientState(string $clientState, string $subscriptionId): bool
    {
        $subscription = GraphSubscription::where('subscription_id', $subscriptionId)->first();

        if (! $subscription) {
            Log::warning('Subscription not found for clientState validation', [
                'subscription_id' => $subscriptionId,
            ]);

            return false;
        }

        $isValid = $subscription->client_state === $clientState;

        if ($isValid) {
            Log::info('ClientState validation successful', [
                'subscription_id' => $subscriptionId,
            ]);
        } else {
            Log::error('ClientState validation failed', [
                'subscription_id' => $subscriptionId,
                'expected' => $subscription->client_state,
                'received' => $clientState,
            ]);
        }

        return $isValid;
    }

    /**
     * Fetch current subscription status from Microsoft Graph API.
     */
    public function getSubscriptionStatus(string $subscriptionId): array
    {
        $subscription = GraphSubscription::where('subscription_id', $subscriptionId)->first();

        if (! $subscription) {
            throw new Exception("Subscription not found: {$subscriptionId}");
        }

        $subscription->load('office365Connection');
        $connection = $subscription->office365Connection;

        if (! $connection) {
            throw new Exception("Office365 connection not found for subscription: {$subscriptionId}");
        }

        try {
            // Fetch from Microsoft Graph API
            $remoteStatus = $this->office365Service->getGraphSubscription($connection, $subscriptionId);

            // Calculate expiration info
            $isExpired = $subscription->isExpired();
            $expiresInHours = $isExpired ? 0 : now()->diffInHours($subscription->expires_at);

            return [
                'subscription' => $subscription,
                'remote_status' => $remoteStatus,
                'is_expired' => $isExpired,
                'expires_in_hours' => $expiresInHours,
            ];
        } catch (Exception $e) {
            // If 404, subscription was deleted remotely
            if ($e->getCode() === 404) {
                Log::warning('Subscription deleted remotely, marking as expired', [
                    'subscription_id' => $subscriptionId,
                ]);
                $subscription->markAsExpired();
            }

            throw $e;
        }
    }
}
