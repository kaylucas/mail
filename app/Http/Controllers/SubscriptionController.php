<?php

namespace App\Http\Controllers;

use App\Services\GraphSubscriptionService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    public function __construct(
        private GraphSubscriptionService $subscriptionService
    ) {}

    /**
     * Create a new subscription for the authenticated user.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            // Validate request
            $validated = $request->validate([
                'resource' => ['string', 'regex:/^me\/(messages|mailFolders\(\'.+\'\)\/messages)$/'],
                'change_types' => 'array',
                'change_types.*' => 'in:created,updated,deleted',
                'expiration_minutes' => 'integer|min:45|max:10080',
            ]);

            $user = auth()->user();

            // Check if user already has an active subscription
            $existingSubscription = $user->activeEmailSubscription;
            if ($existingSubscription) {
                return response()->json([
                    'message' => 'Active subscription already exists. Delete it first or wait for expiration.',
                    'subscription' => [
                        'id' => $existingSubscription->id,
                        'subscription_id' => $existingSubscription->subscription_id,
                        'expires_at' => $existingSubscription->expires_at,
                    ],
                ], 409);
            }

            // Prepare options
            $options = [];
            if (isset($validated['resource'])) {
                $options['resource'] = $validated['resource'];
            }
            if (isset($validated['change_types'])) {
                $options['changeTypes'] = $validated['change_types'];
            }
            if (isset($validated['expiration_minutes'])) {
                $options['expirationMinutes'] = $validated['expiration_minutes'];
            }

            // Create subscription
            $subscription = $this->subscriptionService->createSubscription($user, $options);

            Log::info('Subscription created via API', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->subscription_id,
            ]);

            return response()->json([
                'message' => 'Subscription created successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'subscription_id' => $subscription->subscription_id,
                    'resource' => $subscription->resource,
                    'change_types' => $subscription->change_types,
                    'expires_at' => $subscription->expires_at,
                    'status' => $subscription->status,
                ],
            ], 201);
        } catch (Exception $e) {
            $user = auth()->user();

            // Check for specific error conditions
            if (str_contains($e->getMessage(), 'No active Office365 connection')) {
                Log::error('Subscription creation failed - no Office365 connection', [
                    'user_id' => $user->id,
                    'message' => $e->getMessage(),
                ]);

                return response()->json([
                    'message' => 'No Office365 connection found. Please authenticate first.',
                    'error' => $e->getMessage(),
                ], 404);
            }

            Log::error('Failed to create subscription', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to create subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get current subscription status for authenticated user.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            // Find active subscription
            $subscription = $user->activeEmailSubscription;

            if (!$subscription) {
                return response()->json([
                    'message' => 'No active subscription found',
                ], 404);
            }

            // Load relationships
            $subscription->load('office365Connection');
            $connection = $subscription->office365Connection;

            // Calculate time until expiration
            $timeUntilExpiration = $subscription->getTimeUntilExpiration();
            $timeUntil = $timeUntilExpiration ? $timeUntilExpiration->forHumans() : null;
            $isExpiringSoon = $subscription->isExpiringSoon(24);

            Log::info('Subscription status retrieved', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->subscription_id,
            ]);

            return response()->json([
                'subscription' => [
                    'id' => $subscription->id,
                    'subscription_id' => $subscription->subscription_id,
                    'resource' => $subscription->resource,
                    'change_types' => $subscription->change_types,
                    'expires_at' => $subscription->expires_at,
                    'last_renewed_at' => $subscription->last_renewed_at,
                    'status' => $subscription->status,
                    'is_expiring_soon' => $isExpiringSoon,
                    'time_until_expiration' => $timeUntil,
                ],
                'connection' => [
                    'is_active' => $connection->is_active,
                    'is_token_expired' => $connection->isTokenExpired(),
                ],
            ], 200);
        } catch (Exception $e) {
            Log::error('Failed to retrieve subscription status', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to retrieve subscription status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Manually renew the user's subscription.
     */
    public function renew(Request $request): JsonResponse
    {
        $subscription = null;
        try {
            $user = auth()->user();

            // Find active subscription
            $subscription = $user->activeEmailSubscription;

            if (!$subscription) {
                return response()->json([
                    'message' => 'No active subscription found to renew',
                ], 404);
            }

            // Check if subscription needs renewal
            if (!$subscription->isExpiringSoon(24)) {
                return response()->json([
                    'message' => "Subscription does not need renewal yet. Expires at: {$subscription->expires_at->toIso8601String()}",
                    'subscription' => [
                        'expires_at' => $subscription->expires_at,
                        'time_until_expiration' => $subscription->getTimeUntilExpiration()->forHumans(),
                    ],
                ], 400);
            }

            // Renew subscription
            $subscription = $this->subscriptionService->renewSubscription($subscription);

            Log::info('Subscription renewed via API', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->subscription_id,
                'new_expiration' => $subscription->expires_at,
            ]);

            return response()->json([
                'message' => 'Subscription renewed successfully',
                'subscription' => [
                    'id' => $subscription->id,
                    'subscription_id' => $subscription->subscription_id,
                    'expires_at' => $subscription->expires_at,
                    'last_renewed_at' => $subscription->last_renewed_at,
                    'status' => $subscription->status,
                ],
            ], 200);
        } catch (Exception $e) {
            $subscriptionId = $subscription ? $subscription->subscription_id : null;
            Log::error('Failed to renew subscription', [
                'user_id' => auth()->id(),
                'subscription_id' => $subscriptionId,
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to renew subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete the user's subscription.
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            // Find any subscription (active or expired)
            $subscription = $user->graphSubscriptions()->latest()->first();

            if (!$subscription) {
                return response()->json([
                    'message' => 'No subscription found',
                ], 404);
            }

            // Delete subscription
            $this->subscriptionService->deleteSubscription($subscription);

            Log::info('Subscription deleted via API', [
                'user_id' => $user->id,
                'subscription_id' => $subscription->subscription_id,
            ]);

            return response()->json(null, 204);
        } catch (Exception $e) {
            Log::error('Failed to delete subscription', [
                'user_id' => auth()->id(),
                'message' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Failed to delete subscription',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
