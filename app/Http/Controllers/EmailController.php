<?php

namespace App\Http\Controllers;

use App\Models\Email;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailController extends Controller
{
    /**
     * List emails for authenticated user with pagination and filters.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            // Validate query parameters
            $validated = $request->validate([
                'folder_id' => 'sometimes|exists:email_folders,id',
                'is_read' => 'sometimes|boolean',
                'has_attachments' => 'sometimes|boolean',
                'search' => 'sometimes|string|max:255',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'sort_by' => 'sometimes|in:received_date_time,sent_date_time,subject',
                'sort_order' => 'sometimes|in:asc,desc',
            ]);

            $query = Email::where('user_id', $user->id)
                ->with(['folder', 'attachments', 'labels', 'reminders']);

            // Apply filters
            if (isset($validated['folder_id'])) {
                $query->where('email_folder_id', $validated['folder_id']);
            }

            if (isset($validated['is_read'])) {
                $query->where('is_read', $validated['is_read']);
            }

            if (isset($validated['has_attachments'])) {
                $query->where('has_attachments', $validated['has_attachments']);
            }

            // Search in subject, from, body preview
            if (isset($validated['search'])) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                        ->orWhere('from_email', 'like', "%{$search}%")
                        ->orWhere('from_name', 'like', "%{$search}%")
                        ->orWhere('body_preview', 'like', "%{$search}%");
                });
            }

            // Sorting
            $sortBy = $validated['sort_by'] ?? 'received_date_time';
            $sortOrder = $validated['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Pagination
            $perPage = $validated['per_page'] ?? 50;
            $emails = $query->paginate($perPage);

            Log::info('Emails listed', [
                'user_id' => $user->id,
                'total' => $emails->total(),
                'per_page' => $perPage,
                'current_page' => $emails->currentPage(),
            ]);

            return response()->json($emails, 200);
        } catch (\Exception $e) {
            Log::error('Failed to list emails', [
                'user_id' => auth()->id(),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to retrieve emails',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Get a single email by ID.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $email = Email::where('user_id', $user->id)
                ->where('id', $id)
                ->with(['folder', 'attachments', 'labels', 'reminders'])
                ->first();

            if (! $email) {
                return response()->json([
                    'message' => 'Email not found',
                ], 404);
            }

            Log::info('Email viewed', [
                'user_id' => $user->id,
                'email_id' => $email->id,
                'subject' => $email->subject,
            ]);

            return response()->json([
                'data' => $email,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve email', [
                'user_id' => auth()->id(),
                'email_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to retrieve email',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Mark email as read or unread.
     */
    public function updateReadStatus(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $validated = $request->validate([
                'is_read' => 'required|boolean',
            ]);

            $email = Email::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (! $email) {
                return response()->json([
                    'message' => 'Email not found',
                ], 404);
            }

            $email->update([
                'is_read' => $validated['is_read'],
            ]);

            Log::info('Email read status updated', [
                'user_id' => $user->id,
                'email_id' => $email->id,
                'is_read' => $validated['is_read'],
            ]);

            return response()->json([
                'message' => 'Email read status updated',
                'data' => $email,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to update email read status', [
                'user_id' => auth()->id(),
                'email_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to update email read status',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Get email folders for authenticated user.
     */
    public function folders(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $folders = $user->emailFolders()
                ->orderBy('display_name', 'asc')
                ->get();

            Log::info('Email folders listed', [
                'user_id' => $user->id,
                'folder_count' => $folders->count(),
            ]);

            return response()->json([
                'data' => $folders,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to list email folders', [
                'user_id' => auth()->id(),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to retrieve email folders',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Get email statistics for authenticated user.
     */
    public function stats(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $stats = [
                'total_emails' => $user->emails()->count(),
                'unread_emails' => $user->emails()->where('is_read', false)->count(),
                'total_folders' => $user->emailFolders()->count(),
                'emails_with_attachments' => $user->emails()->where('has_attachments', true)->count(),
                'last_sync_at' => $user->last_email_sync_at,
                'has_active_subscription' => $user->activeEmailSubscription !== null,
            ];

            Log::info('Email statistics retrieved', [
                'user_id' => $user->id,
                'total_emails' => $stats['total_emails'],
            ]);

            return response()->json([
                'data' => $stats,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve email statistics', [
                'user_id' => auth()->id(),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to retrieve email statistics',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }
}
