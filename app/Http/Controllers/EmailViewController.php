<?php

namespace App\Http\Controllers;

use App\Models\Email;
use App\Models\EmailRuleExecution;
use App\Models\EmailView;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class EmailViewController extends Controller
{
    /**
     * List all email views for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $views = EmailView::with('rules')
                ->forUser($user->id)
                ->ordered()
                ->get();

            $views->each(function (EmailView $view) {
                $view->email_count = $view->getEmailCount();
                $view->unread_count = $view->getUnreadEmailCount();
            });

            Log::info('Email views listed', [
                'user_id' => $user->id,
                'view_count' => $views->count(),
            ]);

            return response()->json([
                'data' => $views,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to list email views', [
                'user_id' => auth()->id(),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to retrieve email views',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Create a new email view.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $validated = $this->validateViewRequest($request, $user->id);

            $view = EmailView::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'icon' => $validated['icon'] ?? null,
                'color' => $validated['color'] ?? null,
                'is_visible' => $validated['is_visible'] ?? true,
                'order' => $validated['order'] ?? 0,
            ]);

            $view->rules()->sync($validated['rule_ids']);
            $view->load('rules');

            $view->email_count = $view->getEmailCount();
            $view->unread_count = $view->getUnreadEmailCount();

            Log::info('Email view created', [
                'user_id' => $user->id,
                'view_id' => $view->id,
            ]);

            return response()->json([
                'message' => 'Email view created successfully',
                'data' => $view,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create email view', [
                'user_id' => auth()->id(),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to create email view',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Show a single email view.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $view = EmailView::with('rules')
                ->forUser($user->id)
                ->find($id);

            if (! $view) {
                return response()->json([
                    'message' => 'Email view not found',
                ], 404);
            }

            $view->email_count = $view->getEmailCount();
            $view->unread_count = $view->getUnreadEmailCount();

            Log::info('Email view retrieved', [
                'user_id' => $user->id,
                'view_id' => $view->id,
            ]);

            return response()->json([
                'data' => $view,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve email view', [
                'user_id' => auth()->id(),
                'view_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to retrieve email view',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Update an existing email view.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $view = EmailView::forUser($user->id)->find($id);

            if (! $view) {
                return response()->json([
                    'message' => 'Email view not found',
                ], 404);
            }

            $validated = $this->validateViewRequest($request, $user->id);

            $view->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'icon' => $validated['icon'] ?? null,
                'color' => $validated['color'] ?? null,
                'is_visible' => $validated['is_visible'] ?? $view->is_visible,
                'order' => $validated['order'] ?? $view->order,
            ]);

            $view->rules()->sync($validated['rule_ids']);
            $view->load('rules');

            $view->email_count = $view->getEmailCount();
            $view->unread_count = $view->getUnreadEmailCount();

            Log::info('Email view updated', [
                'user_id' => $user->id,
                'view_id' => $view->id,
            ]);

            return response()->json([
                'message' => 'Email view updated successfully',
                'data' => $view,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update email view', [
                'user_id' => auth()->id(),
                'view_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to update email view',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Delete an email view.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $view = EmailView::forUser($user->id)->find($id);

            if (! $view) {
                return response()->json([
                    'message' => 'Email view not found',
                ], 404);
            }

            $view->delete();

            Log::info('Email view deleted', [
                'user_id' => $user->id,
                'view_id' => $view->id,
            ]);

            return response()->json([
                'message' => 'Email view deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to delete email view', [
                'user_id' => auth()->id(),
                'view_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to delete email view',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Toggle view visibility.
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $view = EmailView::forUser($user->id)->find($id);

            if (! $view) {
                return response()->json([
                    'message' => 'Email view not found',
                ], 404);
            }

            $view->update([
                'is_visible' => ! $view->is_visible,
            ]);

            $view->email_count = $view->getEmailCount();
            $view->unread_count = $view->getUnreadEmailCount();

            Log::info('Email view toggled', [
                'user_id' => $user->id,
                'view_id' => $view->id,
                'is_visible' => $view->is_visible,
            ]);

            return response()->json([
                'message' => 'Email view visibility updated',
                'data' => $view,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to toggle email view', [
                'user_id' => auth()->id(),
                'view_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to toggle email view',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Retrieve emails for a specific view.
     */
    public function getEmails(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $view = EmailView::with('rules')
                ->forUser($user->id)
                ->find($id);

            if (! $view) {
                return response()->json([
                    'message' => 'Email view not found',
                ], 404);
            }

            $ruleIds = $view->rules->pluck('id');

            if ($ruleIds->isEmpty()) {
                return response()->json([
                    'data' => []
                ], 200);
            }

            $validated = $request->validate([
                'is_read' => 'sometimes|boolean',
                'has_attachments' => 'sometimes|boolean',
                'search' => 'sometimes|string|max:255',
                'per_page' => 'sometimes|integer|min:1|max:100',
                'sort_by' => 'sometimes|in:received_date_time,sent_date_time,subject',
                'sort_order' => 'sometimes|in:asc,desc',
            ]);

            $emailIdsSubquery = EmailRuleExecution::select('email_id')
                ->where('user_id', $user->id)
                ->where('evaluation_result', true)
                ->whereIn('email_rule_id', $ruleIds);

            $query = Email::where('user_id', $user->id)
                ->whereIn('id', $emailIdsSubquery)
                ->with(['folder', 'attachments', 'labels', 'reminders']);

            if (isset($validated['is_read'])) {
                $query->where('is_read', $validated['is_read']);
            }

            if (isset($validated['has_attachments'])) {
                $query->where('has_attachments', $validated['has_attachments']);
            }

            if (isset($validated['search'])) {
                $search = $validated['search'];
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                        ->orWhere('from_email', 'like', "%{$search}%")
                        ->orWhere('from_name', 'like', "%{$search}%")
                        ->orWhere('body_preview', 'like', "%{$search}%");
                });
            }

            $sortBy = $validated['sort_by'] ?? 'received_date_time';
            $sortOrder = $validated['sort_order'] ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            $perPage = $validated['per_page'] ?? 50;
            $emails = $query->paginate($perPage);

            Log::info('Emails listed for view', [
                'user_id' => $user->id,
                'view_id' => $view->id,
                'total' => $emails->total(),
            ]);

            return response()->json($emails, 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to retrieve emails for view', [
                'user_id' => auth()->id(),
                'view_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to retrieve emails for view',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Validate request payload for creating/updating views.
     */
    private function validateViewRequest(Request $request, int $userId): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:7',
            'is_visible' => 'sometimes|boolean',
            'order' => 'sometimes|integer|min:0',
            'rule_ids' => 'required|array|min:1',
            'rule_ids.*' => [
                'integer',
                Rule::exists('email_rules', 'id')->where(function ($query) use ($userId) {
                    $query->where('user_id', $userId);
                }),
            ],
        ]);
    }
}
