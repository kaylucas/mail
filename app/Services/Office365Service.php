<?php

namespace App\Services;

use App\Models\Office365Connection;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Microsoft\Graph\Graph;

class Office365Service
{
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
     * Get a configured Microsoft Graph client.
     */
    public function getGraphClient(Office365Connection $connection): Graph
    {
        $graph = new Graph();
        $graph->setAccessToken($connection->access_token);

        return $graph;
    }

    /**
     * Get user profile from Microsoft Graph API.
     */
    public function getUserProfile(string $accessToken): array
    {
        try {
            $graph = new Graph();
            $graph->setAccessToken($accessToken);

            $user = $graph->createRequest('GET', '/me')
                ->addHeaders(['Content-Type' => 'application/json'])
                ->setReturnType(\Microsoft\Graph\Model\User::class)
                ->execute();

            return [
                'id' => $user->getId(),
                'displayName' => $user->getDisplayName(),
                'mail' => $user->getMail(),
                'userPrincipalName' => $user->getUserPrincipalName(),
            ];
        } catch (Exception $e) {
            Log::error('Failed to fetch user profile from Microsoft Graph', [
                'message' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get user photo from Microsoft Graph API.
     */
    public function getUserPhoto(string $accessToken): ?string
    {
        try {
            $graph = new Graph();
            $graph->setAccessToken($accessToken);

            $response = $graph->createRequest('GET', '/me/photos/48x48/$value')
                ->execute();

            if ($response->getStatus() === 200) {
                // Convert photo to base64 data URL
                $photoData = base64_encode($response->getBody());
                return "data:image/jpeg;base64,{$photoData}";
            }

            return null;
        } catch (Exception $e) {
            Log::warning('Failed to fetch user photo from Microsoft Graph', [
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
