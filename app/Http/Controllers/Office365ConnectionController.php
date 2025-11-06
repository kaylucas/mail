<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOffice365ConnectionRequest;
use App\Models\Office365Connection;
use App\Services\Office365Service;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class Office365ConnectionController extends Controller
{
    public function __construct(
        private Office365Service $service
    ) {}

    /**
     * Store or update an Office365 connection.
     */
    public function store(StoreOffice365ConnectionRequest $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $data = $request->validated();
            $data['tenant_id'] = $data['tenant_id'] ?? config('services.office365.tenant_id');
            $data['client_id'] = config('services.office365.client_id');
            $data['client_secret'] = config('services.office365.client_secret');
            $data['redirect_uri'] = config('services.office365.redirect_uri');
            $data['scopes'] = $data['scopes'] ?? (array) preg_split('/[\s,]+/', config('services.office365.scopes'));

            $connection = Office365Connection::updateOrCreate(
                ['user_id' => $userId],
                $data
            );

            return response()->json([
                'message' => 'Office365 connection saved successfully.',
                'connection' => $connection,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to save Office365 connection.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the current Office365 connection status.
     */
    public function show(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $connection = Office365Connection::where('user_id', $userId)->first();

            if (! $connection) {
                return response()->json([
                    'message' => 'No Office365 connection found.',
                ], 404);
            }

            return response()->json([
                'connection' => $connection,
                'is_token_expired' => $connection->isTokenExpired(),
                'is_active' => $connection->is_active,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to retrieve Office365 connection.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete the Office365 connection.
     */
    public function destroy(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $connection = Office365Connection::where('user_id', $userId)->first();

            if ($connection) {
                $connection->delete();
            }

            return response()->json(null, 204);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to delete Office365 connection.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get the OAuth authorization URL.
     *
     * @deprecated This API route is deprecated. OAuth callback is now handled via the web route
     * /auth/microsoft/callback using session-based state validation. If you need an API callback,
     * create an authenticated route that uses auth()->id() with state stored in the session.
     */
    public function getAuthUrl(Request $request): JsonResponse
    {
        try {
            $userId = auth()->id();

            $connection = Office365Connection::where('user_id', $userId)->first();

            if (! $connection) {
                return response()->json([
                    'message' => 'No Office365 connection found. Please configure connection first.',
                ], 404);
            }

            if (! $connection->is_active) {
                return response()->json(['message' => 'Connection is inactive.'], 409);
            }

            // Generate CSRF protection state
            $state = Str::random(40);

            // Store state in cache for 10 minutes
            Cache::put("office365_state_{$userId}", $state, now()->addMinutes(10));

            // Generate authorization URL
            $authUrl = $this->service->generateAuthorizationUrl($connection, $state);

            return response()->json([
                'authorization_url' => $authUrl,
                'state' => $state,
            ], 200);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Failed to generate authorization URL.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
