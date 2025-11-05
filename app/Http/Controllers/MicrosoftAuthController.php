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

            Log::info('OAuth redirect initiated', [
                'session_id' => $request->session()->getId(),
                'redirect_uri' => $redirectUri,
                'client_id' => $clientId,
                'host' => $request->getHost(),
                'url' => $request->fullUrl(),
                'has_session_cookie' => $request->hasCookie(config('session.cookie')),
            ]);

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

            Log::info('OAuth state generated and stored in cache', [
                'state' => $state,
                'session_id' => $request->session()->getId(),
                'cache_key' => "oauth_state_{$state}",
                'ttl_minutes' => 10,
            ]);

            // Generate authorization URL
            $authUrl = $this->service->generateAuthorizationUrl($tempConnection, $state);

            Log::info('Redirecting to Microsoft', [
                'auth_url' => $authUrl,
            ]);

            return redirect($authUrl);
        } catch (Exception $e) {
            Log::error('Microsoft auth redirect failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
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
                'has_code' => !empty($code),
                'code_length' => $code ? strlen($code) : 0,
                'state_from_query' => $state,
            ]);

            if (!$code || !$state) {
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

            if (!$cachedData) {
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

            // Find or create user
            $user = User::where('microsoft_id', $microsoftId)
                ->orWhere('email', $email)
                ->first();

            if ($user) {
                // Update existing user
                $user->update([
                    'name' => $name,
                    'email' => $email,
                    'microsoft_id' => $microsoftId,
                ]);
            } else {
                // Create new user
                $user = User::create([
                    'name' => $name,
                    'email' => $email,
                    'microsoft_id' => $microsoftId,
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

            // Log the user in
            Auth::login($user);

            // Store user ID in cache for session transfer
            $sessionToken = Str::random(60);
            Cache::put("auth_session_{$sessionToken}", $user->id, now()->addMinutes(5));

            Log::info('User authenticated successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'session_token' => $sessionToken,
            ]);

            // Redirect to local backend to establish session, then to frontend
            // This is necessary because the OAuth callback happens on ngrok (HTTPS) but
            // the app runs on mail.loc (HTTP). We need to establish the session on the correct domain.
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            return redirect("http://mail.loc/auth/session?token={$sessionToken}&redirect=" . urlencode("{$frontendUrl}/#/dashboard"));
        } catch (Exception $e) {
            Log::error('Microsoft auth callback failed', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            return redirect("{$frontendUrl}/#/?error=auth_failed");
        }
    }

    /**
     * Establish session on the local domain after OAuth callback.
     * This is needed because OAuth callback happens on ngrok (HTTPS) but
     * the app runs on mail.loc (HTTP), and session cookies need to be
     * established on the correct domain.
     */
    public function establishSession(Request $request)
    {
        try {
            $token = $request->query('token');
            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            $redirect = $request->query('redirect', "{$frontendUrl}/#/dashboard");

            if (!$token) {
                throw new Exception('Missing session token');
            }

            // Get user ID from cache
            $cacheKey = "auth_session_{$token}";
            $userId = Cache::get($cacheKey);

            if (!$userId) {
                throw new Exception('Invalid or expired session token');
            }

            // Delete the token from cache (single use)
            Cache::forget($cacheKey);

            // Find user
            $user = User::find($userId);
            if (!$user) {
                throw new Exception('User not found');
            }

            // Log the user in and regenerate session
            Auth::login($user);
            $request->session()->regenerate();

            Log::info('Session established on local domain', [
                'user_id' => $user->id,
                'session_id' => $request->session()->getId(),
            ]);

            // Redirect to frontend
            return redirect($redirect);
        } catch (Exception $e) {
            Log::error('Session establishment failed', [
                'message' => $e->getMessage(),
            ]);

            $frontendUrl = config('app.frontend_url', 'http://localhost:5173');
            return redirect("{$frontendUrl}/#/?error=session_failed");
        }
    }

    /**
     * Logout the user.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    /**
     * Get the authenticated user.
     */
    public function user(Request $request)
    {
        if (!Auth::check()) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $user = Auth::user()->load('office365Connection');

        return response()->json($user);
    }
}
