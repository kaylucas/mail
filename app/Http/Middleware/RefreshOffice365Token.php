<?php

namespace App\Http\Middleware;

use App\Services\Office365Service;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RefreshOffice365Token
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private Office365Service $office365Service
    ) {}

    /**
     * Handle an incoming request.
     *
     * Automatically refresh Office365 access token if it will expire in the next 5 minutes.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Skip if no authenticated user or no Office365 connection
        if (! $user || ! $user->office365Connection) {
            return $next($request);
        }

        $connection = $user->office365Connection;

        // Check if token will expire in next 5 minutes (300 seconds buffer)
        if ($connection->token_expires_at &&
            $connection->token_expires_at->subMinutes(5)->isPast()) {

            try {
                Log::info('Proactively refreshing Office365 token in middleware', [
                    'user_id' => $user->id,
                    'current_expires_at' => $connection->token_expires_at,
                    'expires_in_seconds' => $connection->token_expires_at->diffInSeconds(now()),
                ]);

                // Refresh the access token using refresh token
                $tokenData = $this->office365Service->refreshAccessToken($connection);

                // Update connection with new tokens
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => $tokenData['expires_at'],
                ]);

                Log::info('Token refreshed successfully in middleware', [
                    'user_id' => $user->id,
                    'new_expires_at' => $tokenData['expires_at'],
                    'new_expires_in_seconds' => $tokenData['expires_at']->diffInSeconds(now()),
                ]);
            } catch (\Exception $e) {
                // Log error but don't block the request
                // The API call may fail naturally and provide better error context
                Log::error('Failed to refresh Office365 token in middleware', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                    'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
                ]);

                // If refresh token is expired/invalid, mark connection as inactive
                if (str_contains($e->getMessage(), 'invalid_grant') ||
                    str_contains($e->getMessage(), 'expired')) {
                    $connection->update(['is_active' => false]);

                    Log::warning('Marked Office365 connection as inactive due to invalid refresh token', [
                        'user_id' => $user->id,
                        'connection_id' => $connection->id,
                    ]);
                }
            }
        }

        return $next($request);
    }
}
