<?php

namespace App\Http\Controllers;

use App\Models\Office365Connection;
use App\Models\User;
use App\Services\Office365Service;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class MicrosoftAuthController extends Controller
{
    public function __construct(
        private Office365Service $service
    ) {}

    /**
     * Redirect to Microsoft OAuth authorization page.
     */
    public function redirect(Request $request)
    {
        try {
            // Get client credentials from config
            $clientId = config('services.office365.client_id');
            $clientSecret = config('services.office365.client_secret');
            $redirectUri = config('services.office365.redirect_uri');
            $tenantId = config('services.office365.tenant_id');
            $scopes = 'openid profile email offline_access User.Read Mail.Read';

            // Create temporary connection object for authorization URL generation
            $tempConnection = new Office365Connection([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'tenant_id' => $tenantId,
                'scopes' => $scopes,
            ]);

            // Generate and store state for CSRF protection
            // Store in cache instead of session to support cross-domain OAuth flow
            $state = Str::random(40);

            // Store state in cache with 10 minute TTL
            Cache::put("oauth_state_{$state}", [
                'created_at' => now(),
                'ip' => $request->ip(),
            ], now()->addMinutes(10));

            // Generate authorization URL
            $authUrl = $this->service->generateAuthorizationUrl($tempConnection, $state);

            return redirect($authUrl);
        } catch (Exception $e) {
            Log::error('Microsoft auth redirect failed', [
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

            return redirect("{$frontendUrl}/#/?error=auth_redirect_failed");
        }
    }

    /**
     * Handle Microsoft OAuth callback.
     */
    public function callback(Request $request)
    {
        try {
            Log::info('OAuth callback received', [
                'session_id' => $request->session()->getId(),
                'host' => $request->getHost(),
                'url' => $request->fullUrl(),
                'has_session_cookie' => $request->hasCookie(config('session.cookie')),
                'all_cookies' => array_keys($request->cookies->all()),
                'query_params' => $request->query(),
            ]);

            // Validate required parameters
            $code = $request->query('code');
            $state = $request->query('state');

            Log::info('OAuth parameters extracted', [
                'has_code' => ! empty($code),
                'code_length' => $code ? strlen($code) : 0,
                'state_from_query' => $state,
            ]);

            if (! $code || ! $state) {
                throw new Exception('Missing authorization code or state');
            }

            // Verify state for CSRF protection
            // Retrieve state from cache (stored during redirect)
            $cacheKey = "oauth_state_{$state}";
            $cachedData = Cache::get($cacheKey);

            Log::info('State validation check', [
                'state_from_query' => $state,
                'cache_key' => $cacheKey,
                'cache_data_exists' => $cachedData !== null,
                'cached_data' => $cachedData,
                'current_ip' => $request->ip(),
            ]);

            if (! $cachedData) {
                Log::error('State validation failed - not found in cache', [
                    'state' => $state,
                    'cache_key' => $cacheKey,
                    'possible_reasons' => [
                        'State expired (>10 minutes)',
                        'State already used',
                        'Invalid state value',
                        'Cache driver issue',
                    ],
                ]);
                throw new Exception('Invalid state parameter');
            }

            // Optional: Verify IP matches (can be disabled if users might switch networks)
            if (isset($cachedData['ip']) && $cachedData['ip'] !== $request->ip()) {
                Log::warning('State IP mismatch', [
                    'expected_ip' => $cachedData['ip'],
                    'current_ip' => $request->ip(),
                ]);
                // Not failing here as IP can change (mobile networks, VPN, etc.)
            }

            Log::info('State validation successful');

            // Delete state from cache to prevent replay attacks
            Cache::forget($cacheKey);

            // Get client credentials
            $clientId = config('services.office365.client_id');
            $clientSecret = config('services.office365.client_secret');
            $redirectUri = config('services.office365.redirect_uri');
            $tenantId = config('services.office365.tenant_id');
            $scopes = 'openid profile email offline_access User.Read Mail.Read';

            // Create temporary connection for token exchange
            $tempConnection = new Office365Connection([
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri' => $redirectUri,
                'tenant_id' => $tenantId,
                'scopes' => $scopes,
            ]);

            // Exchange code for tokens
            $tokens = $this->service->exchangeCodeForTokens($tempConnection, $code);

            // Fetch user profile from Microsoft Graph using the OAuth access token
            // IMPORTANT: Use access_token (for Graph API), not id_token (for authentication only)
            $profile = $this->service->getUserProfile($tokens['access_token']);

            // Extract user data
            $microsoftId = $profile['id'];
            $name = $profile['displayName'];
            $email = $profile['mail'] ?? $profile['userPrincipalName'];

            // Find user by Microsoft ID first (most reliable identifier)
            // Fall back to email if Microsoft ID not found (handles email changes)
            $user = User::where('microsoft_id', $microsoftId)->first();

            if (! $user) {
                // Not found by Microsoft ID, try by email
                $user = User::where('email', $email)->first();
            }

            if ($user) {
                // Update existing user - sync both microsoft_id and email
                // This handles cases where email or microsoft_id changed
                $user->update([
                    'name' => $name,
                    'email' => $email,
                    'microsoft_id' => $microsoftId,
                ]);

                Log::info('Updated existing user during OAuth callback', [
                    'user_id' => $user->id,
                    'microsoft_id' => $microsoftId,
                    'email' => $email,
                ]);
            } else {
                // Create new user - no existing match found
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'microsoft_id' => $microsoftId,
                ]);

                Log::info('Created new user during OAuth callback', [
                    'user_id' => $user->id,
                    'microsoft_id' => $microsoftId,
                    'email' => $email,
                ]);
            }

            // Create or update Office365 connection
            $connection = Office365Connection::updateOrCreate(
                ['user_id' => $user->id],
                [
                    'access_token' => $tokens['access_token'],
                    'refresh_token' => $tokens['refresh_token'],
                    'token_expires_at' => $tokens['expires_at'],
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'redirect_uri' => $redirectUri,
                    'tenant_id' => $tenantId,
                    'scopes' => $scopes,
                ]
            );

            // Generate Sanctum API token for the user
            $apiToken = $user->createToken('auth_token')->plainTextToken;

            Log::info('User authenticated successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);

            // Trigger automatic email sync for new users
            // Only sync if user has no delta token and no emails yet
            if (! $user->hasDeltaToken() && ! $user->emails()->exists()) {
                // Calculate 7 days ago in ISO 8601 format
                $sevenDaysAgo = now()->subDays(7)->toIso8601String();
                $filter = "receivedDateTime ge {$sevenDaysAgo}";

                // Log::info('Dispatching initial email sync for new user', [
                //     'user_id' => $user->id,
                //     'filter' => $filter,
                // ]);

                // Dispatch job and capture job UUID
                $job = \App\Jobs\InitialEmailSyncJob::dispatch($user, null); // No filter for full initial sync

                // Store job ID for status tracking
                $user->update([
                    'current_sync_job_id' => $job->id ?? null,
                    'sync_started_at' => now(),
                ]);

                Log::info('Sync job dispatched and tracked', [
                    'user_id' => $user->id,
                    'job_id' => $job->id ?? null,
                ]);
            }

            // Redirect to frontend with the API token
            // The token is passed in the URL hash so it's not sent to the server
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

            return redirect("{$frontendUrl}/#/auth/callback?token={$apiToken}");
        } catch (Exception $e) {
            Log::error('Microsoft auth callback failed', [
                'message' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

            return redirect("{$frontendUrl}/#/?error=auth_failed");
        }
    }

    /**
     * This endpoint is no longer needed with token-based auth.
     * Kept for backwards compatibility but redirects to frontend.
     *
     * @deprecated Use token-based auth instead
     */
    public function establishSession(Request $request)
    {
        $frontendUrl = config('app.frontend_url', 'http://localhost:5173');

        return redirect("{$frontendUrl}/#/?error=deprecated_endpoint");
    }

    /**
     * Logout the user by revoking their API token.
     */
    public function logout(Request $request)
    {
        // Revoke the current access token
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully']);
    }

    /**
     * Get the authenticated user with token expiry information.
     */
    public function user(Request $request)
    {
        $user = $request->user()->load('office365Connection');
        $userData = $user->toArray();

        // Add token expiry information if connection exists
        if ($user->office365Connection) {
            $connection = $user->office365Connection;
            $expiresAt = $connection->token_expires_at;

            // Calculate time until token expires
            if ($expiresAt) {
                $expiresInSeconds = max(0, $expiresAt->diffInSeconds(now(), false));
                $isExpired = $connection->isTokenExpired();

                $userData['office365_connection']['token_expires_in_seconds'] = abs($expiresInSeconds);
                $userData['office365_connection']['token_is_expired'] = $isExpired;
                $userData['office365_connection']['token_expires_soon'] = ! $isExpired && $expiresInSeconds < 300; // < 5 minutes
            }
        }

        return response()->json($userData);
    }
}
