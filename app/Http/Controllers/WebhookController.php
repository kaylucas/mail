<?php

namespace App\Http\Controllers;

use App\Models\GraphSubscription;
use App\Models\WebhookNotification;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle Microsoft Graph subscription validation endpoint.
     *
     * @deprecated Use handleNotification() instead - it handles both GET (validation) and POST (notifications)
     *
     * Microsoft sends a validation request during subscription creation.
     * We must respond within 10 seconds with the validationToken value
     * in plain text format (not JSON).
     */
    public function handleValidation(Request $request)
    {
        try {
            // Extract validationToken from query parameters
            $validationToken = $request->query('validationToken');

            Log::info('Webhook validation request received', [
                'validation_token_length' => $validationToken ? strlen($validationToken) : 0,
                'request_ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);

            // Validate that token is present
            if (!$validationToken) {
                Log::error('Webhook validation failed - missing validationToken', [
                    'query_params' => $request->query(),
                    'request_ip' => $request->ip(),
                ]);

                return response()->json(['error' => 'Missing validationToken parameter'], 400);
            }

            Log::info('Webhook validation successful', [
                'validation_token' => substr($validationToken, 0, 20) . '...',
                'request_ip' => $request->ip(),
            ]);

            // CRITICAL: Must return text/plain response with the token value
            // Microsoft will reject JSON responses or responses with quotes
            return response($validationToken, 200)
                ->header('Content-Type', 'text/plain');

        } catch (Exception $e) {
            Log::error('Webhook validation exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_ip' => $request->ip(),
            ]);

            return response()->json(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Handle change notifications from Microsoft Graph.
     *
     * Handles both GET (validation) and POST (notifications):
     * - GET: Microsoft sends validationToken query param during subscription creation
     * - POST: Microsoft sends JSON payload with array of change notifications
     *
     * Each notification includes subscriptionId, clientState, changeType, resource, and resourceData.
     * We validate the clientState and store the notification for asynchronous processing.
     */
    public function handleNotification(Request $request)
    {
        try {
            // Handle validation request (GET with validationToken query parameter)
            if ($request->isMethod('get') && $request->has('validationToken')) {
                $validationToken = $request->query('validationToken');

                Log::info('Webhook validation request received', [
                    'validation_token_length' => strlen($validationToken),
                    'request_ip' => $request->ip(),
                    'timestamp' => now()->toIso8601String(),
                    'url' => $request->fullUrl(),
                ]);

                if (!$validationToken) {
                    Log::error('Webhook validation failed - missing validationToken');
                    return response()->json(['error' => 'Missing validationToken parameter'], 400);
                }

                Log::info('Webhook validation successful', [
                    'validation_token' => substr($validationToken, 0, 20) . '...',
                    'request_ip' => $request->ip(),
                ]);

                // CRITICAL: Must return text/plain response with the token value
                return response($validationToken, 200)
                    ->header('Content-Type', 'text/plain');
            }

            // Parse JSON body for POST requests
            $data = $request->json()->all();

            Log::info('Webhook notification received', [
                'notification_count' => isset($data['value']) ? count($data['value']) : 0,
                'request_ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
                'content_type' => $request->header('Content-Type'),
            ]);

            // Validate payload structure
            if (!isset($data['value']) || !is_array($data['value'])) {
                Log::error('Webhook notification failed - invalid payload structure', [
                    'data' => $data,
                    'request_ip' => $request->ip(),
                ]);

                // Return 202 anyway to prevent Microsoft from retrying
                return response()->json(['status' => 'accepted'], 202);
            }

            $processedCount = 0;
            $failedCount = 0;
            $subscriptionIds = [];

            // Process each notification in the batch
            foreach ($data['value'] as $notification) {
                try {
                    // Extract required fields
                    $subscriptionId = $notification['subscriptionId'] ?? null;
                    $clientState = $notification['clientState'] ?? null;
                    $changeType = $notification['changeType'] ?? null;
                    $resource = $notification['resource'] ?? null;
                    $resourceData = $notification['resourceData'] ?? null;
                    $tenantId = $notification['tenantId'] ?? null;

                    $subscriptionIds[] = $subscriptionId;

                    Log::info('Processing notification', [
                        'subscription_id' => $subscriptionId,
                        'change_type' => $changeType,
                        'resource' => $resource,
                        'has_client_state' => !empty($clientState),
                    ]);

                    // Validate required fields
                    if (!$subscriptionId || !$clientState) {
                        Log::warning('Notification missing required fields', [
                            'subscription_id' => $subscriptionId,
                            'has_client_state' => !empty($clientState),
                            'notification' => $notification,
                        ]);
                        $failedCount++;
                        continue;
                    }

                    // Find subscription and validate clientState
                    $subscription = GraphSubscription::where('subscription_id', $subscriptionId)->first();

                    if (!$subscription) {
                        Log::warning('Notification for unknown subscription', [
                            'subscription_id' => $subscriptionId,
                            'request_ip' => $request->ip(),
                        ]);
                        $failedCount++;
                        continue;
                    }

                    // Validate clientState to prevent spoofing
                    if ($subscription->client_state !== $clientState) {
                        Log::error('SECURITY WARNING: clientState validation failed', [
                            'subscription_id' => $subscriptionId,
                            'expected_client_state' => substr($subscription->client_state ?? '', 0, 10) . '...',
                            'received_client_state' => substr($clientState, 0, 10) . '...',
                            'request_ip' => $request->ip(),
                            'user_id' => $subscription->user_id,
                        ]);
                        $failedCount++;
                        continue;
                    }

                    Log::info('clientState validation successful', [
                        'subscription_id' => $subscriptionId,
                        'user_id' => $subscription->user_id,
                    ]);

                    // Store notification for asynchronous processing
                    WebhookNotification::create([
                        'subscription_id' => $subscriptionId,
                        'client_state' => $clientState,
                        'change_type' => $changeType,
                        'resource' => $resource,
                        'resource_data' => $resourceData,
                        'tenant_id' => $tenantId,
                    ]);

                    Log::info('Notification stored successfully', [
                        'subscription_id' => $subscriptionId,
                        'change_type' => $changeType,
                    ]);

                    // TODO: Dispatch job for asynchronous processing (future phase)
                    // dispatch(new ProcessWebhookNotificationJob($notification));

                    $processedCount++;

                } catch (Exception $e) {
                    Log::error('Failed to process individual notification', [
                        'subscription_id' => $notification['subscriptionId'] ?? null,
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $failedCount++;
                }
            }

            Log::info('Webhook notification batch completed', [
                'total_notifications' => count($data['value']),
                'processed_count' => $processedCount,
                'failed_count' => $failedCount,
                'subscription_ids' => array_unique($subscriptionIds),
            ]);

            // Return 202 Accepted immediately
            // Microsoft expects quick response; actual processing happens asynchronously
            return response()->json(['status' => 'accepted'], 202);

        } catch (Exception $e) {
            Log::error('Webhook notification exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_ip' => $request->ip(),
                'request_body' => $request->getContent() ? substr($request->getContent(), 0, 500) : null,
            ]);

            // Return 202 anyway to prevent Microsoft from retrying
            // We've logged the error for debugging
            return response()->json(['status' => 'accepted'], 202);
        }
    }

    /**
     * Handle subscription lifecycle notifications from Microsoft Graph.
     *
     * Microsoft sends lifecycle events such as:
     * - reauthorizationRequired: Subscription needs to be renewed
     * - missed: Notifications were missed, delta sync needed
     * - subscriptionRemoved: Subscription was removed by Microsoft
     *
     * These notifications help us maintain subscription health.
     */
    public function handleLifecycleNotification(Request $request)
    {
        try {
            // Parse JSON body
            $data = $request->json()->all();

            Log::info('Webhook lifecycle notification received', [
                'notification_count' => isset($data['value']) ? count($data['value']) : 0,
                'request_ip' => $request->ip(),
                'timestamp' => now()->toIso8601String(),
            ]);

            // Validate payload structure
            if (!isset($data['value']) || !is_array($data['value'])) {
                Log::error('Lifecycle notification failed - invalid payload structure', [
                    'data' => $data,
                    'request_ip' => $request->ip(),
                ]);

                return response()->json(['status' => 'accepted'], 202);
            }

            // Process each lifecycle notification
            foreach ($data['value'] as $notification) {
                try {
                    // Extract required fields
                    $subscriptionId = $notification['subscriptionId'] ?? null;
                    $clientState = $notification['clientState'] ?? null;
                    $lifecycleEvent = $notification['lifecycleEvent'] ?? null;
                    $tenantId = $notification['tenantId'] ?? null;

                    Log::info('Processing lifecycle notification', [
                        'subscription_id' => $subscriptionId,
                        'lifecycle_event' => $lifecycleEvent,
                        'tenant_id' => $tenantId,
                    ]);

                    // Validate required fields
                    if (!$subscriptionId || !$lifecycleEvent) {
                        Log::warning('Lifecycle notification missing required fields', [
                            'subscription_id' => $subscriptionId,
                            'lifecycle_event' => $lifecycleEvent,
                        ]);
                        continue;
                    }

                    // Find subscription and validate clientState
                    $subscription = GraphSubscription::where('subscription_id', $subscriptionId)->first();

                    if (!$subscription) {
                        Log::warning('Lifecycle notification for unknown subscription', [
                            'subscription_id' => $subscriptionId,
                            'lifecycle_event' => $lifecycleEvent,
                        ]);
                        continue;
                    }

                    // Validate clientState if provided
                    if ($clientState && $subscription->client_state !== $clientState) {
                        Log::error('SECURITY WARNING: clientState validation failed in lifecycle notification', [
                            'subscription_id' => $subscriptionId,
                            'lifecycle_event' => $lifecycleEvent,
                            'expected_client_state' => substr($subscription->client_state ?? '', 0, 10) . '...',
                            'received_client_state' => substr($clientState, 0, 10) . '...',
                            'request_ip' => $request->ip(),
                        ]);
                        continue;
                    }

                    // Handle different lifecycle events
                    switch ($lifecycleEvent) {
                        case 'reauthorizationRequired':
                            Log::warning('Subscription reauthorization required', [
                                'subscription_id' => $subscriptionId,
                                'user_id' => $subscription->user_id,
                                'expires_at' => $subscription->expires_at,
                                'action_needed' => 'Scheduled job will renew subscription',
                            ]);
                            // Actual renewal will be handled by scheduled job (future phase)
                            break;

                        case 'missed':
                            Log::warning('Notifications were missed', [
                                'subscription_id' => $subscriptionId,
                                'user_id' => $subscription->user_id,
                                'action_needed' => 'Delta sync required to catch up on missed changes',
                            ]);
                            // Delta sync will be implemented in future phase
                            break;

                        case 'subscriptionRemoved':
                            Log::warning('Subscription was removed by Microsoft', [
                                'subscription_id' => $subscriptionId,
                                'user_id' => $subscription->user_id,
                                'action_needed' => 'May need to recreate subscription',
                            ]);
                            // Consider marking subscription as inactive
                            break;

                        default:
                            Log::info('Unknown lifecycle event received', [
                                'subscription_id' => $subscriptionId,
                                'lifecycle_event' => $lifecycleEvent,
                            ]);
                            break;
                    }

                } catch (Exception $e) {
                    Log::error('Failed to process lifecycle notification', [
                        'subscription_id' => $notification['subscriptionId'] ?? null,
                        'message' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            Log::info('Lifecycle notification batch completed', [
                'total_notifications' => count($data['value']),
            ]);

            // Return 202 Accepted
            return response()->json(['status' => 'accepted'], 202);

        } catch (Exception $e) {
            Log::error('Lifecycle notification exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_ip' => $request->ip(),
                'request_body' => $request->getContent() ? substr($request->getContent(), 0, 500) : null,
            ]);

            // Return 202 anyway to prevent Microsoft from retrying
            return response()->json(['status' => 'accepted'], 202);
        }
    }
}
