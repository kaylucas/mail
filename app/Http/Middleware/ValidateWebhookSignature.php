<?php

namespace App\Http\Middleware;

use App\Models\GraphSubscription;
use Closure;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateWebhookSignature
{
    /**
     * Handle an incoming request.
     *
     * This middleware validates the clientState in Microsoft Graph webhook notifications
     * to prevent spoofing attacks. It's a defense-in-depth measure; the controller also
     * performs validation.
     *
     * Note: Microsoft recommends returning 202 Accepted even for invalid requests
     * to prevent retry storms. We log validation failures for monitoring.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        try {
            // Skip validation for GET requests (validation endpoint uses query params)
            if ($request->isMethod('GET')) {
                Log::debug('Webhook middleware: Skipping validation for GET request', [
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);

                return $next($request);
            }

            // Skip validation if path contains 'validation' (validation endpoint doesn't have clientState)
            if (str_contains($request->path(), 'validation')) {
                Log::debug('Webhook middleware: Skipping validation for validation endpoint', [
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);

                return $next($request);
            }

            // Skip validation for requests with validationToken query parameter
            // Microsoft sends these during subscription creation/renewal
            if ($request->has('validationToken')) {
                Log::debug('Webhook middleware: Skipping validation for token validation request', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'has_token' => true,
                    'ip' => $request->ip(),
                ]);

                return $next($request);
            }

            // Log all incoming webhook requests for monitoring
            Log::info('Webhook middleware: Incoming webhook request', [
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'content_type' => $request->header('Content-Type'),
                'user_agent' => $request->userAgent(),
            ]);

            // Parse JSON body for POST requests to notification/lifecycle endpoints
            $data = $request->json()->all();

            // Check if payload has the expected structure
            if (! isset($data['value']) || ! is_array($data['value']) || empty($data['value'])) {
                Log::warning('Webhook middleware: Invalid payload structure', [
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                    'has_value' => isset($data['value']),
                    'is_array' => isset($data['value']) && is_array($data['value']),
                ]);

                // Continue anyway - controller will handle invalid payloads
                return $next($request);
            }

            // Validate each notification in the batch
            foreach ($data['value'] as $index => $notification) {
                $subscriptionId = $notification['subscriptionId'] ?? null;
                $clientState = $notification['clientState'] ?? null;

                if (! $subscriptionId || ! $clientState) {
                    Log::warning('Webhook middleware: Notification missing required fields', [
                        'index' => $index,
                        'subscription_id' => $subscriptionId,
                        'has_client_state' => ! empty($clientState),
                        'ip' => $request->ip(),
                    ]);

                    continue;
                }

                // Find subscription
                $subscription = GraphSubscription::where('subscription_id', $subscriptionId)->first();

                if (! $subscription) {
                    Log::warning('Webhook middleware: Subscription not found', [
                        'subscription_id' => $subscriptionId,
                        'ip' => $request->ip(),
                    ]);

                    continue;
                }

                // Validate clientState (strict comparison)
                if ($subscription->client_state !== $clientState) {
                    Log::error('Webhook middleware: SECURITY ALERT - clientState mismatch', [
                        'subscription_id' => $subscriptionId,
                        'expected_state' => substr($subscription->client_state ?? '', 0, 10).'...',
                        'received_state' => substr($clientState, 0, 10).'...',
                        'ip' => $request->ip(),
                        'user_agent' => $request->userAgent(),
                        'user_id' => $subscription->user_id,
                    ]);

                    // Continue anyway - controller will handle validation
                    // Microsoft recommends not blocking to avoid retry issues
                    continue;
                }

                Log::debug('Webhook middleware: clientState validation passed', [
                    'subscription_id' => $subscriptionId,
                    'user_id' => $subscription->user_id,
                ]);
            }

            // Continue to controller
            return $next($request);

        } catch (Exception $e) {
            // Log exception but don't block the request
            // Controller will handle the request and log additional errors if needed
            Log::error('Webhook middleware: Exception during validation', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            // Continue to controller despite exception
            return $next($request);
        }
    }
}
