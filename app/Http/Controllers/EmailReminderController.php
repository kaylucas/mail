<?php

namespace App\Http\Controllers;

use App\Models\EmailReminder;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class EmailReminderController extends Controller
{
    /**
     * List all email reminders for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            // Validate query parameters
            $validated = $request->validate([
                'status' => 'sometimes|in:pending,dismissed,completed',
            ]);

            $query = EmailReminder::where('user_id', $user->id)
                ->with(['email.folder']);

            // Apply status filter if provided
            if (isset($validated['status'])) {
                $query->where('status', $validated['status']);
            }

            $reminders = $query->orderBy('remind_at', 'asc')
                ->get();

            Log::info('Email reminders listed', [
                'user_id' => $user->id,
                'reminder_count' => $reminders->count(),
                'status_filter' => $validated['status'] ?? 'all',
            ]);

            return response()->json([
                'data' => $reminders,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to list email reminders', [
                'user_id' => auth()->id(),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to retrieve email reminders',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Mark a reminder as dismissed.
     */
    public function dismiss(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $reminder = EmailReminder::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (! $reminder) {
                return response()->json([
                    'message' => 'Email reminder not found',
                ], 404);
            }

            $reminder->update([
                'status' => 'dismissed',
                'dismissed_at' => Carbon::now(),
            ]);

            Log::info('Email reminder dismissed', [
                'user_id' => $user->id,
                'reminder_id' => $reminder->id,
                'email_id' => $reminder->email_id,
            ]);

            return response()->json([
                'message' => 'Reminder dismissed successfully',
                'data' => $reminder,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to dismiss email reminder', [
                'user_id' => auth()->id(),
                'reminder_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to dismiss reminder',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Mark a reminder as completed.
     */
    public function complete(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $reminder = EmailReminder::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (! $reminder) {
                return response()->json([
                    'message' => 'Email reminder not found',
                ], 404);
            }

            $reminder->update([
                'status' => 'completed',
                'completed_at' => Carbon::now(),
            ]);

            Log::info('Email reminder completed', [
                'user_id' => $user->id,
                'reminder_id' => $reminder->id,
                'email_id' => $reminder->email_id,
            ]);

            return response()->json([
                'message' => 'Reminder completed successfully',
                'data' => $reminder,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to complete email reminder', [
                'user_id' => auth()->id(),
                'reminder_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to complete reminder',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Snooze a reminder by updating remind_at.
     */
    public function snooze(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $reminder = EmailReminder::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (! $reminder) {
                return response()->json([
                    'message' => 'Email reminder not found',
                ], 404);
            }

            // Validate snooze days
            $validated = $request->validate([
                'days' => 'required|integer|min:1|max:30',
            ]);

            $newRemindAt = Carbon::now()->addDays($validated['days']);

            $reminder->update([
                'remind_at' => $newRemindAt,
                'status' => 'pending', // Reset to pending when snoozed
            ]);

            Log::info('Email reminder snoozed', [
                'user_id' => $user->id,
                'reminder_id' => $reminder->id,
                'email_id' => $reminder->email_id,
                'days' => $validated['days'],
                'new_remind_at' => $newRemindAt->toIso8601String(),
            ]);

            return response()->json([
                'message' => 'Reminder snoozed successfully',
                'data' => $reminder,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to snooze email reminder', [
                'user_id' => auth()->id(),
                'reminder_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to snooze reminder',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }
}
