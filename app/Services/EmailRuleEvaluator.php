<?php

namespace App\Services;

use App\Models\Email;
use App\Models\EmailRule;
use App\Models\EmailRuleExecution;
use App\Services\Actions\ForwardActionHandler;
use App\Services\Actions\LabelActionHandler;
use App\Services\Actions\ReminderActionHandler;
use EchoLabs\Prism\Prism;
use EchoLabs\Prism\Schema\BooleanSchema;
use EchoLabs\Prism\Schema\ObjectSchema;
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
            $schema = $this->buildResponseSchema($rules);

            Log::info('Sending batched AI evaluation request', [
                'email_id' => $email->id,
                'rules_count' => count($rules),
            ]);

            $response = Prism::structured()
                ->using('anthropic', config('prism.providers.anthropic.default'))
                ->withSystemPrompt('You are an email classifier. Analyze the email and determine which rules match based on their criteria. Return a JSON object with boolean values for each rule.')
                ->withPrompt($prompt)
                ->withSchema($schema)
                ->generate();

            $result = $response->structured;

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
            'is_automated' => new BooleanSchema('Whether the email appears to be automated/newsletter'),
            'needs_response' => new BooleanSchema('Whether the email requires a human response'),
        ];

        foreach ($rules as $rule) {
            $properties["rule_{$rule->id}"] = new BooleanSchema("Whether rule '{$rule->name}' matches");
        }

        return new ObjectSchema(
            'Email classification result',
            $properties,
            ['is_automated', 'needs_response']
        );
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
}
