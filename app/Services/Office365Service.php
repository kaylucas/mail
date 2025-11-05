<?php

namespace App\Services;

use App\Models\Office365Connection;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\GraphServiceClient;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAccessTokenProvider;
use Microsoft\Graph\Core\Authentication\GraphPhpLeagueAuthenticationProvider;
use Microsoft\Kiota\Authentication\Cache\InMemoryAccessTokenCache;
use Microsoft\Kiota\Abstractions\ApiException;
use League\OAuth2\Client\Token\AccessToken;
use League\OAuth2\Client\Grant\AuthorizationCode;
use Microsoft\Kiota\Authentication\Oauth\AuthorizationCodeContext;

class Office365Service
{
    /**
     * Validate that the token is an access token (not ID token) by checking JWT claims.
     * Logs warnings if the token appears to be an ID token.
     */
    private function validateAccessToken(string $token): void
    {
        try {
            // Split JWT token (format: header.payload.signature)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                Log::warning('Token is not a valid JWT format', [
                    'token_preview' => substr($token, 0, 20) . '...',
                ]);
                return;
            }

            // Decode payload (base64url decode)
            $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

            if (!$payload) {
                Log::warning('Unable to decode token payload');
                return;
            }

            // Check for ID token indicators
            if (isset($payload['aud']) && !isset($payload['scp'])) {
                Log::error('Token appears to be an ID token (has aud, missing scp claim)', [
                    'aud' => $payload['aud'],
                    'has_roles' => isset($payload['roles']),
                    'has_scp' => isset($payload['scp']),
                    'token_type' => 'id_token',
                ]);
            }

            // Log access token details
            if (isset($payload['scp'])) {
                Log::info('Access token validated successfully', [
                    'scopes' => $payload['scp'],
                    'aud' => $payload['aud'] ?? null,
                    'token_type' => 'access_token',
                ]);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to validate token type', [
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Normalize scopes to a space-separated string.
     */
    private function normalizeScopes(string|array|null $raw): string
    {
        if (is_null($raw) || $raw === '') {
            return '';
        }
        if (is_string($raw)) {
            $raw = preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        }
        return implode(' ', $raw);
    }

    /**
     * Normalize scopes to an array of scope strings.
     */
    private function normalizeScopesArray(string|array|null $raw): array
    {
        if (is_null($raw) || $raw === '') {
            return [];
        }
        if (is_string($raw)) {
            return preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        }
        return $raw;
    }

    /**
     * Generate the Microsoft OAuth authorization URL.
     */
    public function generateAuthorizationUrl(Office365Connection $connection, string $state): string
    {
        $tenantId = $connection->tenant_id ?? 'common';
        $scopes = $this->normalizeScopes($connection->scopes ?? config('services.office365.scopes', 'offline_access Mail.Read'));

        $params = http_build_query([
            'client_id' => $connection->client_id,
            'response_type' => 'code',
            'redirect_uri' => $connection->redirect_uri,
            'scope' => $scopes,
            'state' => $state,
            'response_mode' => 'query',
        ]);

        return "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/authorize?{$params}";
    }

    /**
     * Exchange authorization code for access and refresh tokens.
     */
    public function exchangeCodeForTokens(Office365Connection $connection, string $code): array
    {
        $tenantId = $connection->tenant_id ?? 'common';
        $scopes = $this->normalizeScopes($connection->scopes ?? config('services.office365.scopes', 'offline_access Mail.Read'));

        try {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $connection->client_id,
                    'client_secret' => $connection->client_secret,
                    'code' => $code,
                    'redirect_uri' => $connection->redirect_uri,
                    'grant_type' => 'authorization_code',
                    'scope' => $scopes,
                ]
            );

            if ($response->failed()) {
                $errorData = $response->json();
                Log::error('Office365 token exchange failed', [
                    'status' => $response->status(),
                    'error' => $errorData,
                ]);

                throw new Exception(
                    'Failed to exchange authorization code: '.($errorData['error_description'] ?? 'Unknown error')
                );
            }

            $data = $response->json();

            return [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? null,
                'expires_at' => now()->addSeconds($data['expires_in']),
            ];
        } catch (Exception $e) {
            Log::error('Office365 token exchange exception', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Refresh an expired access token.
     */
    public function refreshAccessToken(Office365Connection $connection): array
    {
        $tenantId = $connection->tenant_id ?? 'common';
        $scopes = $this->normalizeScopes($connection->scopes ?? config('services.office365.scopes', 'offline_access Mail.Read'));

        try {
            $response = Http::asForm()->post(
                "https://login.microsoftonline.com/{$tenantId}/oauth2/v2.0/token",
                [
                    'client_id' => $connection->client_id,
                    'client_secret' => $connection->client_secret,
                    'refresh_token' => $connection->refresh_token,
                    'grant_type' => 'refresh_token',
                    'scope' => $scopes,
                ]
            );

            if ($response->failed()) {
                $errorData = $response->json();
                Log::error('Office365 token refresh failed', [
                    'status' => $response->status(),
                    'error' => $errorData,
                ]);

                throw new Exception(
                    'Failed to refresh access token: '.($errorData['error_description'] ?? 'Unknown error')
                );
            }

            $data = $response->json();

            return [
                'access_token' => $data['access_token'],
                'refresh_token' => $data['refresh_token'] ?? $connection->refresh_token,
                'expires_at' => now()->addSeconds($data['expires_in']),
            ];
        } catch (Exception $e) {
            Log::error('Office365 token refresh exception', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a Graph client with proper TokenRequestContext, cache, and provider wiring.
     *
     * @param string $tenantId
     * @param string $clientId
     * @param string $clientSecret
     * @param string $redirectUri
     * @param string $accessTokenValue
     * @param string|null $refreshTokenValue
     * @param int|null $expiresIn
     * @param array $scopes
     * @return GraphServiceClient
     */
    private function createGraphClient(
        string $tenantId,
        string $clientId,
        string $clientSecret,
        string $redirectUri,
        string $accessTokenValue,
        ?string $refreshTokenValue = null,
        ?int $expiresIn = null,
        array $scopes = []
    ): GraphServiceClient {
        // Build TokenRequestContext with authorization code context
        $tokenRequestContext = new AuthorizationCodeContext(
            $tenantId,
            $clientId,
            $clientSecret,
            'placeholder_auth_code', // Placeholder as we already have tokens
            $redirectUri
        );

        // Create AccessToken with all available token data
        $tokenData = [
            'access_token' => $accessTokenValue,
        ];

        if ($refreshTokenValue) {
            $tokenData['refresh_token'] = $refreshTokenValue;
        }

        // CRITICAL: Always set an expiry time. If not provided, default to 1 hour from now.
        // Without a valid expiry, the SDK will attempt to acquire a new token using the
        // AuthorizationCodeContext, which will fail with "invalid_grant" since we use a placeholder code.
        if ($expiresIn !== null && $expiresIn > 0) {
            $tokenData['expires'] = time() + $expiresIn;
        } else {
            // Default to 1 hour from now if no expiry provided
            $tokenData['expires'] = time() + 3600;
        }

        $accessToken = new AccessToken($tokenData);

        // Create in-memory cache seeded with the token context and access token
        // The cache constructor automatically handles the mapping
        $tokenCache = new InMemoryAccessTokenCache($tokenRequestContext, $accessToken);

        // Create the access token provider with cache, context, and scopes
        $tokenProvider = GraphPhpLeagueAccessTokenProvider::createWithCache(
            $tokenCache,
            $tokenRequestContext,
            $scopes
        );

        // Create the authentication provider
        $authProvider = GraphPhpLeagueAuthenticationProvider::createWithAccessTokenProvider($tokenProvider);

        // Return the configured GraphServiceClient
        return GraphServiceClient::createWithAuthenticationProvider($authProvider);
    }

    /**
     * Get a configured Microsoft Graph client from an Office365Connection.
     */
    public function getGraphClient(Office365Connection $connection): GraphServiceClient
    {
        $tenantId = $connection->tenant_id ?? 'common';
        $scopes = $this->normalizeScopesArray($connection->scopes ?? config('services.office365.scopes', 'offline_access Mail.Read'));

        // Calculate expires_in from token_expires_at if available
        $expiresIn = null;
        if ($connection->token_expires_at) {
            $expiresIn = max(0, $connection->token_expires_at->timestamp - time());
        }

        return $this->createGraphClient(
            $tenantId,
            $connection->client_id,
            $connection->client_secret,
            $connection->redirect_uri,
            $connection->access_token,
            $connection->refresh_token,
            $expiresIn,
            $scopes
        );
    }

    /**
     * Get user profile from Microsoft Graph API.
     *
     * @param string $accessToken The OAuth access token (not ID token)
     * @param string $tenantId Tenant ID for the OAuth context
     * @param string $clientId Client ID for the OAuth context
     * @param string $clientSecret Client secret for the OAuth context
     * @param string $redirectUri Redirect URI for the OAuth context
     * @param string|array|null $scopes Scopes for the token
     */
    public function getUserProfile(
        string $accessToken,
        string $tenantId = 'common',
        string $clientId = '',
        string $clientSecret = '',
        string $redirectUri = '',
        string|array|null $scopes = null
    ): array {
        try {
            // Validate that we have an access token, not an ID token
            $this->validateAccessToken($accessToken);

            // Use config defaults if not provided
            $tenantId = $tenantId ?: config('services.office365.tenant_id', 'common');
            $clientId = $clientId ?: config('services.office365.client_id');
            $clientSecret = $clientSecret ?: config('services.office365.client_secret');
            $redirectUri = $redirectUri ?: config('services.office365.redirect_uri');
            $scopesArray = $this->normalizeScopesArray($scopes ?? config('services.office365.scopes', 'offline_access User.Read'));

            // Create Graph client using centralized method
            $graphServiceClient = $this->createGraphClient(
                $tenantId,
                $clientId,
                $clientSecret,
                $redirectUri,
                $accessToken,
                null,
                null,
                $scopesArray
            );

            // Fetch user profile using v2.x API
            $user = $graphServiceClient->me()->get()->wait();

            return [
                'id' => $user->getId(),
                'displayName' => $user->getDisplayName(),
                'mail' => $user->getMail(),
                'userPrincipalName' => $user->getUserPrincipalName(),
            ];
        } catch (ApiException $e) {
            Log::error('Failed to fetch user profile from Microsoft Graph', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Create a new Microsoft Graph subscription.
     */
    public function createGraphSubscription(Office365Connection $connection, array $params): array
    {
        try {
            // Check if token is expired and refresh if needed
            if ($connection->isTokenExpired()) {
                Log::info('Access token expired, refreshing before creating subscription', [
                    'connection_id' => $connection->id,
                ]);
                $tokenData = $this->refreshAccessToken($connection);
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => $tokenData['expires_at'],
                ]);
            }

            // Normalize changeType to comma-separated string if array
            $changeType = is_array($params['changeType'])
                ? implode(',', $params['changeType'])
                : $params['changeType'];

            // Convert expirationDateTime to ISO8601 format if Carbon instance
            $expirationDateTime = $params['expirationDateTime'] instanceof \Carbon\Carbon
                ? $params['expirationDateTime']->toIso8601String()
                : $params['expirationDateTime'];

            // Build request body
            $requestBody = [
                'changeType' => $changeType,
                'notificationUrl' => $params['notificationUrl'],
                'resource' => $params['resource'],
                'expirationDateTime' => $expirationDateTime,
                'clientState' => $params['clientState'],
            ];

            Log::info('Creating Microsoft Graph subscription', [
                'resource' => $params['resource'],
                'changeType' => $changeType,
                'notificationUrl' => $params['notificationUrl'],
                'expirationDateTime' => $expirationDateTime,
            ]);

            // Make POST request to Microsoft Graph API
            $response = Http::withToken($connection->access_token)
                ->post('https://graph.microsoft.com/v1.0/subscriptions', $requestBody);

            // Handle 401 by refreshing token and retrying once
            if ($response->status() === 401) {
                Log::info('Received 401 response, refreshing token and retrying', [
                    'connection_id' => $connection->id,
                ]);
                $tokenData = $this->refreshAccessToken($connection);
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => $tokenData['expires_at'],
                ]);

                // Retry request with new token
                $response = Http::withToken($connection->access_token)
                    ->post('https://graph.microsoft.com/v1.0/subscriptions', $requestBody);
            }

            Log::info('Microsoft Graph subscription response', [
                'status' => $response->status(),
                'subscription_id' => $response->json('id'),
            ]);

            if ($response->failed()) {
                $errorData = $response->json();
                Log::error('Failed to create Microsoft Graph subscription', [
                    'status' => $response->status(),
                    'error' => $errorData,
                    'resource' => $params['resource'],
                ]);

                throw new Exception(
                    'Failed to create Graph subscription: ' . ($errorData['error']['message'] ?? 'Unknown error')
                );
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Exception while creating Microsoft Graph subscription', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Renew an existing Microsoft Graph subscription.
     */
    public function renewGraphSubscription(Office365Connection $connection, string $subscriptionId, string $expirationDateTime): array
    {
        try {
            // Check if token is expired and refresh if needed
            if ($connection->isTokenExpired()) {
                Log::info('Access token expired, refreshing before renewing subscription', [
                    'connection_id' => $connection->id,
                    'subscription_id' => $subscriptionId,
                ]);
                $tokenData = $this->refreshAccessToken($connection);
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => $tokenData['expires_at'],
                ]);
            }

            // Convert expirationDateTime to ISO8601 format if Carbon instance
            $expirationDateTime = $expirationDateTime instanceof \Carbon\Carbon
                ? $expirationDateTime->toIso8601String()
                : $expirationDateTime;

            // Build request body
            $requestBody = [
                'expirationDateTime' => $expirationDateTime,
            ];

            Log::info('Renewing Microsoft Graph subscription', [
                'subscription_id' => $subscriptionId,
                'new_expiration' => $expirationDateTime,
            ]);

            // Make PATCH request to Microsoft Graph API
            $response = Http::withToken($connection->access_token)
                ->patch("https://graph.microsoft.com/v1.0/subscriptions/{$subscriptionId}", $requestBody);

            // Handle 401 by refreshing token and retrying once
            if ($response->status() === 401) {
                Log::info('Received 401 response, refreshing token and retrying', [
                    'connection_id' => $connection->id,
                    'subscription_id' => $subscriptionId,
                ]);
                $tokenData = $this->refreshAccessToken($connection);
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => $tokenData['expires_at'],
                ]);

                // Retry request with new token
                $response = Http::withToken($connection->access_token)
                    ->patch("https://graph.microsoft.com/v1.0/subscriptions/{$subscriptionId}", $requestBody);
            }

            Log::info('Microsoft Graph subscription renewal response', [
                'status' => $response->status(),
                'subscription_id' => $subscriptionId,
            ]);

            if ($response->failed()) {
                $errorData = $response->json();
                Log::error('Failed to renew Microsoft Graph subscription', [
                    'status' => $response->status(),
                    'error' => $errorData,
                    'subscription_id' => $subscriptionId,
                ]);

                throw new Exception(
                    'Failed to renew Graph subscription: ' . ($errorData['error']['message'] ?? 'Unknown error')
                );
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Exception while renewing Microsoft Graph subscription', [
                'message' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Delete a Microsoft Graph subscription.
     */
    public function deleteGraphSubscription(Office365Connection $connection, string $subscriptionId): bool
    {
        try {
            // Check if token is expired and refresh if needed
            if ($connection->isTokenExpired()) {
                Log::info('Access token expired, refreshing before deleting subscription', [
                    'connection_id' => $connection->id,
                    'subscription_id' => $subscriptionId,
                ]);
                $tokenData = $this->refreshAccessToken($connection);
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => $tokenData['expires_at'],
                ]);
            }

            Log::info('Deleting Microsoft Graph subscription', [
                'subscription_id' => $subscriptionId,
            ]);

            // Make DELETE request to Microsoft Graph API
            $response = Http::withToken($connection->access_token)
                ->delete("https://graph.microsoft.com/v1.0/subscriptions/{$subscriptionId}");

            // Handle 401 by refreshing token and retrying once
            if ($response->status() === 401) {
                Log::info('Received 401 response, refreshing token and retrying', [
                    'connection_id' => $connection->id,
                    'subscription_id' => $subscriptionId,
                ]);
                $tokenData = $this->refreshAccessToken($connection);
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => $tokenData['expires_at'],
                ]);

                // Retry request with new token
                $response = Http::withToken($connection->access_token)
                    ->delete("https://graph.microsoft.com/v1.0/subscriptions/{$subscriptionId}");
            }

            if ($response->status() === 204) {
                Log::info('Successfully deleted Microsoft Graph subscription', [
                    'subscription_id' => $subscriptionId,
                ]);
                return true;
            }

            if ($response->status() === 404) {
                Log::warning('Microsoft Graph subscription already deleted or not found', [
                    'subscription_id' => $subscriptionId,
                ]);
                return true;
            }

            // Other errors
            $errorData = $response->json();
            Log::error('Failed to delete Microsoft Graph subscription', [
                'status' => $response->status(),
                'error' => $errorData,
                'subscription_id' => $subscriptionId,
            ]);

            throw new Exception(
                'Failed to delete Graph subscription: ' . ($errorData['error']['message'] ?? 'Unknown error')
            );
        } catch (Exception $e) {
            Log::error('Exception while deleting Microsoft Graph subscription', [
                'message' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Get Microsoft Graph subscription details.
     */
    public function getGraphSubscription(Office365Connection $connection, string $subscriptionId): array
    {
        try {
            // Check if token is expired and refresh if needed
            if ($connection->isTokenExpired()) {
                Log::info('Access token expired, refreshing before fetching subscription', [
                    'connection_id' => $connection->id,
                    'subscription_id' => $subscriptionId,
                ]);
                $tokenData = $this->refreshAccessToken($connection);
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => $tokenData['expires_at'],
                ]);
            }

            Log::info('Fetching Microsoft Graph subscription details', [
                'subscription_id' => $subscriptionId,
            ]);

            // Make GET request to Microsoft Graph API
            $response = Http::withToken($connection->access_token)
                ->get("https://graph.microsoft.com/v1.0/subscriptions/{$subscriptionId}");

            // Handle 401 by refreshing token and retrying once
            if ($response->status() === 401) {
                Log::info('Received 401 response, refreshing token and retrying', [
                    'connection_id' => $connection->id,
                    'subscription_id' => $subscriptionId,
                ]);
                $tokenData = $this->refreshAccessToken($connection);
                $connection->update([
                    'access_token' => $tokenData['access_token'],
                    'refresh_token' => $tokenData['refresh_token'],
                    'token_expires_at' => $tokenData['expires_at'],
                ]);

                // Retry request with new token
                $response = Http::withToken($connection->access_token)
                    ->get("https://graph.microsoft.com/v1.0/subscriptions/{$subscriptionId}");
            }

            Log::info('Microsoft Graph subscription fetch response', [
                'status' => $response->status(),
                'subscription_id' => $subscriptionId,
            ]);

            if ($response->failed()) {
                $errorData = $response->json();
                Log::error('Failed to fetch Microsoft Graph subscription', [
                    'status' => $response->status(),
                    'error' => $errorData,
                    'subscription_id' => $subscriptionId,
                ]);

                throw new Exception(
                    'Failed to fetch Graph subscription: ' . ($errorData['error']['message'] ?? 'Unknown error'),
                    $response->status()
                );
            }

            return $response->json();
        } catch (Exception $e) {
            Log::error('Exception while fetching Microsoft Graph subscription', [
                'message' => $e->getMessage(),
                'subscription_id' => $subscriptionId,
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

}
