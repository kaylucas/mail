<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailRule;
use App\Models\EmailRuleExecution;
use App\Services\Actions\ForwardActionHandler;
use App\Services\Actions\LabelActionHandler;
use App\Services\Actions\ReminderActionHandler;
use Prism\Prism\Facades\Prism;
use Prism\Prism\Schema\BooleanSchema;
use Prism\Prism\Schema\ObjectSchema;
use Illuminate\Support\Facades\Log;

class EmailRuleEvaluator
{
    public function __construct(
        private LabelActionHandler $labelHandler,
        private ForwardActionHandler $forwardHandler,
        private ReminderActionHandler $reminderHandler
    ) {}

    /**
     * Evaluate all active rules for a given email.
     *
     * @param Email $email
     * @return array Summary of rule executions
     */
    public function evaluateRulesForEmail(Email $email): array
    {
        $summary = [
            'email_id' => $email->id,
            'rules_evaluated' => 0,
            'rules_matched' => 0,
            'actions_executed' => 0,
            'actions_failed' => 0,
            'matched_rules' => [],
        ];

        try {
            // Load user's active rules
            $rules = $email->user->activeEmailRules;

            if ($rules->isEmpty()) {
                Log::info('No active email rules found for user', [
                    'user_id' => $email->user_id,
                    'email_id' => $email->id,
                ]);
                return $summary;
            }

            $summary['rules_evaluated'] = $rules->count();

            // Pre-filter: check simple conditions without AI
            $simpleMatches = [];
            $aiEvaluationNeeded = [];

            foreach ($rules as $rule) {
                if ($rule->matchesSimpleConditions($email)) {
                    Log::info('Rule matched simple conditions', [
                        'rule_id' => $rule->id,
                        'email_id' => $email->id,
                    ]);
                    $simpleMatches[] = $rule;
                } else {
                    $aiEvaluationNeeded[] = $rule;
                }
            }

            // Execute actions for simple matches immediately
            foreach ($simpleMatches as $rule) {
                $this->executeRuleActions($email, $rule);
                $summary['rules_matched']++;
                $summary['matched_rules'][] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'match_type' => 'simple',
                ];
            }

            // Batch AI evaluation for remaining rules
            if (!empty($aiEvaluationNeeded)) {
                $aiMatches = $this->evaluateWithAI($email, $aiEvaluationNeeded);

                foreach ($aiMatches as $rule) {
                    $this->executeRuleActions($email, $rule);
                    $summary['rules_matched']++;
                    $summary['matched_rules'][] = [
                        'rule_id' => $rule->id,
                        'rule_name' => $rule->name,
                        'match_type' => 'ai',
                    ];
                }
            }

            Log::info('Email rule evaluation completed', $summary);

            return $summary;

        } catch (\Exception $e) {
            Log::error('Email rule evaluation failed', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Evaluate rules using AI with batched prompt.
     * Uses text mode with JSON output as a workaround for OpenAI API issues.
     *
     * @param Email $email
     * @param array $rules
     * @return array Matched rules
     */
    private function evaluateWithAI(Email $email, array $rules): array
    {
        if (empty($rules)) {
            return [];
        }

        try {
            $prompt = $this->buildBatchedPrompt($email, $rules);
            $jsonSchema = $this->buildJsonSchemaString($rules);
            
            $provider = config('prism.default');
            $model = config("prism.providers.{$provider}.model");
            
            // Fix model name if it's invalid
            if ($model === 'gpt-4.1-mini') {
                $model = 'gpt-4o-mini';
            }

            Log::info('AI Evaluation Request Details', [
                'email_id' => $email->id,
                'rules_count' => count($rules),
                'provider' => $provider,
                'model' => $model,
                'prompt' => $prompt,
                'email_subject' => $email->subject,
                'email_from' => $email->from_email,
                'rules_details' => array_map(fn($rule) => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'description' => $rule->description,
                    'conditions' => $rule->conditions,
                ], $rules),
            ]);

            // Use text mode with JSON output instructions
            $systemPrompt = 'You are an email classifier. Analyze the email and determine which rules match based on their criteria. ' .
                          'You MUST respond with valid JSON only, no other text. The JSON must match this schema: ' . $jsonSchema;
            
            $response = Prism::text()
                ->using($provider, $model)
                ->withSystemPrompt($systemPrompt)
                ->withPrompt($prompt . "\n\nRespond with JSON only.")
                ->withMaxTokens(2000)
                ->generate();

            $resultText = $response->text;
            
            // Extract JSON from the response (in case there's any extra text)
            $jsonStart = strpos($resultText, '{');
            $jsonEnd = strrpos($resultText, '}');
            if ($jsonStart !== false && $jsonEnd !== false) {
                $resultText = substr($resultText, $jsonStart, $jsonEnd - $jsonStart + 1);
            }
            
            $result = json_decode($resultText, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from AI: ' . json_last_error_msg());
            }

            Log::info('AI evaluation response received', [
                'email_id' => $email->id,
                'result' => $result,
            ]);

            // Filter rules that matched
            $matched = [];
            foreach ($rules as $rule) {
                $ruleKey = "rule_{$rule->id}";
                if (isset($result[$ruleKey]) && $result[$ruleKey] === true) {
                    $matched[] = $rule;
                }
            }

            return $matched;

        } catch (\Exception $e) {
            Log::error('AI evaluation failed', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            // Return empty array on AI failure - fail gracefully
            return [];
        }
    }

    /**
     * Build batched prompt for AI evaluation.
     *
     * @param Email $email
     * @param array $rules
     * @return string
     */
    public function buildBatchedPrompt(Email $email, array $rules): string
    {
        $emailContext = $this->buildEmailContext($email);

        $prompt = "Analyze the following email and determine which rules match:\n\n";
        $prompt .= "EMAIL DETAILS:\n";
        $prompt .= "Subject: {$emailContext['subject']}\n";
        $prompt .= "From: {$emailContext['from_name']} <{$emailContext['from_email']}>\n";
        $prompt .= "To: {$emailContext['to_recipients']}\n";
        $prompt .= "Received: {$emailContext['received_date_time']}\n";
        $prompt .= "Body Preview: {$emailContext['body_preview']}\n\n";

        $prompt .= "RULES TO EVALUATE:\n\n";

        foreach ($rules as $index => $rule) {
            $ruleNumber = $index + 1;
            $prompt .= "Rule {$ruleNumber} (ID: {$rule->id}):\n";
            $prompt .= "Name: {$rule->name}\n";
            $prompt .= "Criteria: {$rule->prompt}\n\n";
        }

        $prompt .= "\nFor each rule, respond with true if the email matches the criteria, false otherwise.";

        return $prompt;
    }

    /**
     * Build response schema for AI evaluation.
     *
     * @param array $rules
     * @return ObjectSchema
     */
    private function buildResponseSchema(array $rules): ObjectSchema
    {
        $properties = [
            'is_automated' => new BooleanSchema('is_automated', 'Whether the email appears to be automated/newsletter'),
            'needs_response' => new BooleanSchema('needs_response', 'Whether the email requires a human response'),
        ];

        foreach ($rules as $rule) {
            $properties["rule_{$rule->id}"] = new BooleanSchema("rule_{$rule->id}", "Whether rule '{$rule->name}' matches");
        }

        return new ObjectSchema(
            'email_classification_result',
            'Result of email classification and rule matching',
            $properties,
            array_keys($properties)
        );
    }

    /**
     * Build JSON schema string for AI prompt.
     *
     * @param array $rules
     * @return string
     */
    private function buildJsonSchemaString(array $rules): string
    {
        $properties = [
            'is_automated' => ['type' => 'boolean', 'description' => 'Whether the email appears to be automated/newsletter'],
            'needs_response' => ['type' => 'boolean', 'description' => 'Whether the email requires a human response']
        ];

        foreach ($rules as $rule) {
            $properties["rule_{$rule->id}"] = [
                'type' => 'boolean',
                'description' => "Whether rule '{$rule->name}' matches"
            ];
        }

        $schema = [
            'type' => 'object',
            'properties' => $properties,
            'required' => ['is_automated', 'needs_response'],
            'additionalProperties' => false
        ];

        return json_encode($schema, JSON_PRETTY_PRINT);
    }

    /**
     * Execute all actions for a matched rule.
     *
     * @param Email $email
     * @param EmailRule $rule
     * @return array Execution results
     */
    public function executeRuleActions(Email $email, EmailRule $rule): array
    {
        $results = [];
        $actionsExecuted = 0;
        $actionsFailed = 0;

        try {
            // Load rule actions
            $actions = $rule->actions;

            Log::info('Executing rule actions', [
                'rule_id' => $rule->id,
                'email_id' => $email->id,
                'actions_count' => $actions->count(),
            ]);

            foreach ($actions as $action) {
                try {
                    $result = match ($action->action_type) {
                        'add_label' => $this->labelHandler->execute($email, $action),
                        'forward' => $this->forwardHandler->execute($email, $action),
                        'add_reminder' => $this->reminderHandler->execute($email, $action),
                        default => [
                            'success' => false,
                            'error' => "Unknown action type: {$action->action_type}",
                        ],
                    };

                    $results[] = $result;

                    if ($result['success']) {
                        $actionsExecuted++;
                    } else {
                        $actionsFailed++;
                    }

                } catch (\Exception $e) {
                    Log::error('Action execution failed', [
                        'action_id' => $action->id,
                        'action_type' => $action->action_type,
                        'error' => $e->getMessage(),
                    ]);

                    $results[] = [
                        'success' => false,
                        'error' => $e->getMessage(),
                    ];
                    $actionsFailed++;
                }
            }

            // Log execution to database
            EmailRuleExecution::create([
                'email_rule_id' => $rule->id,
                'email_id' => $email->id,
                'user_id' => $email->user_id,
                'evaluation_result' => true,
                'actions_executed' => $actionsExecuted,
                'actions_taken' => $results,
                'ai_provider' => null,
                'ai_model' => null,
                'prompt_sent' => null,
                'ai_response' => null,
                'error_message' => null,
                'execution_time_ms' => null,
            ]);

            return [
                'success' => true,
                'actions_executed' => $actionsExecuted,
                'actions_failed' => $actionsFailed,
                'results' => $results,
            ];

        } catch (\Exception $e) {
            Log::error('Rule action execution failed', [
                'rule_id' => $rule->id,
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            // Log failed execution
            EmailRuleExecution::create([
                'email_rule_id' => $rule->id,
                'email_id' => $email->id,
                'user_id' => $email->user_id,
                'evaluation_result' => false,
                'actions_executed' => 0,
                'actions_taken' => [],
                'ai_provider' => null,
                'ai_model' => null,
                'prompt_sent' => null,
                'ai_response' => null,
                'error_message' => $e->getMessage(),
                'execution_time_ms' => null,
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Build email context for AI evaluation.
     *
     * @param Email $email
     * @return array
     */
    public function buildEmailContext(Email $email): array
    {
        $bodyPreview = $email->body_preview ?? '';
        if (strlen($bodyPreview) > 500) {
            $bodyPreview = substr($bodyPreview, 0, 500) . '...';
        }

        $toRecipients = is_array($email->to_recipients)
            ? implode(', ', array_map(fn($r) => $r['email'] ?? '', $email->to_recipients))
            : '';

        return [
            'subject' => $email->subject ?? '(No Subject)',
            'from_name' => $email->from_name ?? '',
            'from_email' => $email->from_email ?? '',
            'body_preview' => $bodyPreview,
            'to_recipients' => $toRecipients,
            'received_date_time' => $email->received_date_time?->format('Y-m-d H:i:s') ?? '',
        ];
    }

    /**
     * Test rules against an email without executing actions.
     * This is a dry-run mode for debugging and validation.
     *
     * @param Email $email
     * @return array Test results with matched/non-matched rules
     */
    public function testRulesForEmail(Email $email): array
    {
        $startTime = microtime(true);

        $result = [
            'email_id' => $email->id,
            'rules_evaluated' => 0,
            'matched_rules' => [],
            'non_matched_rules' => [],
            'ai_classification' => [
                'is_automated' => null,
                'needs_response' => null,
            ],
            'execution_time_ms' => 0,
        ];

        try {
            // Load user's active rules
            $rules = $email->user->activeEmailRules()->with('actions')->get();

            if ($rules->isEmpty()) {
                Log::info('No active email rules found for test', [
                    'user_id' => $email->user_id,
                    'email_id' => $email->id,
                ]);
                $result['execution_time_ms'] = (int)((microtime(true) - $startTime) * 1000);
                return $result;
            }

            $result['rules_evaluated'] = $rules->count();

            // Pre-filter: check simple conditions without AI
            $simpleMatches = [];
            $aiEvaluationNeeded = [];

            foreach ($rules as $rule) {
                if ($rule->matchesSimpleConditions($email)) {
                    $simpleMatches[] = $rule;
                } else {
                    $aiEvaluationNeeded[] = $rule;
                }
            }

            // Collect matched rules from simple conditions
            foreach ($simpleMatches as $rule) {
                $result['matched_rules'][] = [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'rule_description' => $rule->description,
                    'match_type' => 'simple',
                    'actions' => $this->formatActionsForDisplay($rule->actions),
                ];
            }

            // Batch AI evaluation for remaining rules
            if (!empty($aiEvaluationNeeded)) {
                $aiResponse = $this->evaluateWithAIForTest($email, $aiEvaluationNeeded);

                // Store AI classification
                $result['ai_classification'] = [
                    'is_automated' => $aiResponse['is_automated'] ?? null,
                    'needs_response' => $aiResponse['needs_response'] ?? null,
                ];

                // Process each rule that needed AI evaluation
                foreach ($aiEvaluationNeeded as $rule) {
                    $ruleKey = "rule_{$rule->id}";
                    $matched = isset($aiResponse[$ruleKey]) && $aiResponse[$ruleKey] === true;

                    if ($matched) {
                        $result['matched_rules'][] = [
                            'rule_id' => $rule->id,
                            'rule_name' => $rule->name,
                            'rule_description' => $rule->description,
                            'match_type' => 'ai',
                            'actions' => $this->formatActionsForDisplay($rule->actions),
                        ];
                    } else {
                        $result['non_matched_rules'][] = [
                            'rule_id' => $rule->id,
                            'rule_name' => $rule->name,
                            'rule_description' => $rule->description,
                        ];
                    }
                }
            }

            $result['execution_time_ms'] = (int)((microtime(true) - $startTime) * 1000);

            Log::info('Email rules tested successfully', [
                'email_id' => $email->id,
                'matched_count' => count($result['matched_rules']),
                'execution_time_ms' => $result['execution_time_ms'],
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Email rule testing failed', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Evaluate rules using AI for testing (returns full response including classification).
     * Uses text mode with JSON output as a workaround for OpenAI API issues.
     *
     * @param Email $email
     * @param array $rules
     * @return array AI response with rule matches and classification
     * @throws \RuntimeException When AI evaluation fails
     */
    private function evaluateWithAIForTest(Email $email, array $rules): array
    {
        if (empty($rules)) {
            return [
                'is_automated' => null,
                'needs_response' => null,
            ];
        }

        try {
            $prompt = $this->buildBatchedPrompt($email, $rules);
            $jsonSchema = $this->buildJsonSchemaString($rules);
            
            $provider = config('prism.default');
            $model = config("prism.providers.{$provider}.model");
            
            // Fix model name if it's invalid
            if ($model === 'gpt-4.1-mini') {
                $model = 'gpt-4o-mini';
            }

            Log::info('AI Evaluation Request Details (TEST MODE)', [
                'email_id' => $email->id,
                'rules_count' => count($rules),
                'provider' => $provider,
                'model' => $model,
                'prompt' => $prompt,
                'email_subject' => $email->subject,
                'email_from' => $email->from_email,
                'rules_details' => array_map(fn($rule) => [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'description' => $rule->description,
                    'conditions' => $rule->conditions,
                ], $rules),
            ]);

            // Use text mode with JSON output instructions
            $systemPrompt = 'You are an email classifier. Analyze the email and determine which rules match based on their criteria. ' .
                          'You MUST respond with valid JSON only, no other text. The JSON must match this schema: ' . $jsonSchema;
            
            $response = Prism::text()
                ->using($provider, $model)
                ->withSystemPrompt($systemPrompt)
                ->withPrompt($prompt . "\n\nRespond with JSON only.")
                ->withMaxTokens(2000)
                ->generate();

            $resultText = $response->text;
            
            // Extract JSON from the response (in case there's any extra text)
            $jsonStart = strpos($resultText, '{');
            $jsonEnd = strrpos($resultText, '}');
            if ($jsonStart !== false && $jsonEnd !== false) {
                $resultText = substr($resultText, $jsonStart, $jsonEnd - $jsonStart + 1);
            }
            
            $result = json_decode($resultText, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Invalid JSON response from AI: ' . json_last_error_msg());
            }

            Log::info('AI evaluation response received (test mode)', [
                'email_id' => $email->id,
                'result' => $result,
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('AI evaluation failed (test mode)', [
                'email_id' => $email->id,
                'error' => $e->getMessage(),
                'provider' => config('prism.default'),
                'trace' => app()->environment('local') ? $e->getTraceAsString() : null,
            ]);

            // Re-throw the exception with a more descriptive message
            throw new \RuntimeException(
                sprintf(
                    'AI evaluation failed for email %d: %s. Please check your AI provider configuration and API keys.',
                    $email->id,
                    $e->getMessage()
                ),
                0,
                $e
            );
        }
    }

    /**
     * Format rule actions for display without executing them.
     *
     * @param \Illuminate\Database\Eloquent\Collection $actions
     * @return array
     */
    private function formatActionsForDisplay($actions): array
    {
        $formatted = [];

        foreach ($actions as $action) {
            $description = match ($action->action_type) {
                'add_label' => 'Add label: ' . ($action->action_config['label_name'] ?? 'Unknown'),
                'forward' => 'Forward to: ' . implode(', ', $action->action_config['email_addresses'] ?? []),
                'add_reminder' => sprintf(
                    'Add reminder in %d days: %s',
                    $action->action_config['days_after'] ?? 0,
                    $action->action_config['message'] ?? ''
                ),
                default => 'Unknown action: ' . $action->action_type,
            };

            $formatted[] = [
                'action_type' => $action->action_type,
                'action_config' => $action->action_config,
                'description' => $description,
            ];
        }

        return $formatted;
    }
}
