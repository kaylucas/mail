<?php

namespace App\Http\Controllers;

use App\Models\EmailRule;
use App\Models\EmailRuleAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class EmailRuleController extends Controller
{
    /**
     * List all email rules for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            $rules = EmailRule::where('user_id', $user->id)
                ->with('actions')
                ->orderBy('priority', 'asc')
                ->orderBy('created_at', 'desc')
                ->get();

            Log::info('Email rules listed', [
                'user_id' => $user->id,
                'rule_count' => $rules->count(),
            ]);

            return response()->json([
                'data' => $rules,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to list email rules', [
                'user_id' => auth()->id(),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to retrieve email rules',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Create a new email rule.
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = auth()->user();

            // Validate request
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'prompt' => 'required|string',
                'simple_conditions' => 'nullable|array',
                'is_active' => 'nullable|boolean',
                'priority' => 'nullable|integer|min:0|max:1000',
                'ai_provider' => ['nullable', Rule::in(['anthropic', 'openai', 'gemini'])],
                'ai_model' => 'nullable|string|max:100',
                'actions' => 'required|array|min:1',
                'actions.*.action_type' => ['required', Rule::in(['add_label', 'forward', 'add_reminder'])],
                'actions.*.action_config' => 'required|array',
            ]);

            // Validate each action's config based on action_type
            foreach ($validated['actions'] as $index => $action) {
                $this->validateActionConfig($action['action_type'], $action['action_config'], $index);
            }

            // Create rule and actions in transaction
            DB::beginTransaction();

            $rule = EmailRule::create([
                'user_id' => $user->id,
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'prompt' => $validated['prompt'],
                'simple_conditions' => $validated['simple_conditions'] ?? null,
                'is_active' => $validated['is_active'] ?? true,
                'priority' => $validated['priority'] ?? 100,
                'ai_provider' => $validated['ai_provider'] ?? null,
                'ai_model' => $validated['ai_model'] ?? null,
            ]);

            // Create actions
            foreach ($validated['actions'] as $actionData) {
                EmailRuleAction::create([
                    'email_rule_id' => $rule->id,
                    'action_type' => $actionData['action_type'],
                    'action_config' => $actionData['action_config'],
                ]);
            }

            DB::commit();

            // Load actions relationship
            $rule->load('actions');

            Log::info('Email rule created', [
                'user_id' => $user->id,
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'action_count' => count($validated['actions']),
            ]);

            return response()->json([
                'message' => 'Email rule created successfully',
                'data' => $rule,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create email rule', [
                'user_id' => auth()->id(),
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to create email rule',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Get a single email rule by ID.
     */
    public function show(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $rule = EmailRule::where('user_id', $user->id)
                ->where('id', $id)
                ->with('actions')
                ->first();

            if (! $rule) {
                return response()->json([
                    'message' => 'Email rule not found',
                ], 404);
            }

            Log::info('Email rule retrieved', [
                'user_id' => $user->id,
                'rule_id' => $rule->id,
            ]);

            return response()->json([
                'data' => $rule,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to retrieve email rule', [
                'user_id' => auth()->id(),
                'rule_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to retrieve email rule',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Update an existing email rule.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            // Check ownership
            $rule = EmailRule::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (! $rule) {
                return response()->json([
                    'message' => 'Email rule not found',
                ], 404);
            }

            // Validate request
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:1000',
                'prompt' => 'required|string',
                'simple_conditions' => 'nullable|array',
                'is_active' => 'nullable|boolean',
                'priority' => 'nullable|integer|min:0|max:1000',
                'ai_provider' => ['nullable', Rule::in(['anthropic', 'openai', 'gemini'])],
                'ai_model' => 'nullable|string|max:100',
                'actions' => 'required|array|min:1',
                'actions.*.action_type' => ['required', Rule::in(['add_label', 'forward', 'add_reminder'])],
                'actions.*.action_config' => 'required|array',
            ]);

            // Validate each action's config based on action_type
            foreach ($validated['actions'] as $index => $action) {
                $this->validateActionConfig($action['action_type'], $action['action_config'], $index);
            }

            // Update rule and recreate actions in transaction
            DB::beginTransaction();

            $rule->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'prompt' => $validated['prompt'],
                'simple_conditions' => $validated['simple_conditions'] ?? null,
                'is_active' => $validated['is_active'] ?? $rule->is_active,
                'priority' => $validated['priority'] ?? $rule->priority,
                'ai_provider' => $validated['ai_provider'] ?? $rule->ai_provider,
                'ai_model' => $validated['ai_model'] ?? $rule->ai_model,
            ]);

            // Delete old actions and create new ones
            $rule->actions()->delete();

            foreach ($validated['actions'] as $actionData) {
                EmailRuleAction::create([
                    'email_rule_id' => $rule->id,
                    'action_type' => $actionData['action_type'],
                    'action_config' => $actionData['action_config'],
                ]);
            }

            DB::commit();

            // Load actions relationship
            $rule->load('actions');

            Log::info('Email rule updated', [
                'user_id' => $user->id,
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'action_count' => count($validated['actions']),
            ]);

            return response()->json([
                'message' => 'Email rule updated successfully',
                'data' => $rule,
            ], 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to update email rule', [
                'user_id' => auth()->id(),
                'rule_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to update email rule',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Delete an email rule.
     */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $rule = EmailRule::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (! $rule) {
                return response()->json([
                    'message' => 'Email rule not found',
                ], 404);
            }

            $ruleName = $rule->name;

            // Delete rule (cascade will delete actions due to foreign key constraint)
            $rule->delete();

            Log::info('Email rule deleted', [
                'user_id' => $user->id,
                'rule_id' => $id,
                'rule_name' => $ruleName,
            ]);

            return response()->json([
                'message' => 'Email rule deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to delete email rule', [
                'user_id' => auth()->id(),
                'rule_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to delete email rule',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Toggle a rule's active status.
     */
    public function toggle(Request $request, int $id): JsonResponse
    {
        try {
            $user = auth()->user();

            $rule = EmailRule::where('user_id', $user->id)
                ->where('id', $id)
                ->first();

            if (! $rule) {
                return response()->json([
                    'message' => 'Email rule not found',
                ], 404);
            }

            // Toggle is_active
            $rule->update([
                'is_active' => ! $rule->is_active,
            ]);

            // Load actions relationship for complete response
            $rule->load('actions');

            Log::info('Email rule toggled', [
                'user_id' => $user->id,
                'rule_id' => $rule->id,
                'is_active' => $rule->is_active,
            ]);

            return response()->json([
                'message' => 'Email rule ' . ($rule->is_active ? 'enabled' : 'disabled') . ' successfully',
                'data' => $rule,
            ], 200);
        } catch (\Exception $e) {
            Log::error('Failed to toggle email rule', [
                'user_id' => auth()->id(),
                'rule_id' => $id,
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : 'Stack trace hidden in production',
            ]);

            return response()->json([
                'message' => 'Failed to toggle email rule',
                'error' => $this->sanitizeErrorMessage($e->getMessage()),
            ], 500);
        }
    }

    /**
     * Validate action config based on action type.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function validateActionConfig(string $actionType, array $config, int $index): void
    {
        $rules = [];

        switch ($actionType) {
            case 'add_label':
                $rules = [
                    "actions.{$index}.action_config.label_name" => 'required|string|max:100',
                ];
                break;

            case 'forward':
                $rules = [
                    "actions.{$index}.action_config.email_addresses" => 'required|array|min:1',
                    "actions.{$index}.action_config.email_addresses.*" => 'required|email',
                    "actions.{$index}.action_config.include_note" => 'nullable|boolean',
                ];
                break;

            case 'add_reminder':
                $rules = [
                    "actions.{$index}.action_config.days_after" => 'required|integer|min:1',
                    "actions.{$index}.action_config.message" => 'required|string|max:500',
                ];
                break;
        }

        if (! empty($rules)) {
            validator(['actions' => [['action_config' => $config]]], $rules)->validate();
        }
    }
}
