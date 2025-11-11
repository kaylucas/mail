<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Email;
use App\Services\EmailRuleEvaluator;

class TestEmailRules extends Command
{
    protected $signature = 'test:email-rules {email_id=146}';
    protected $description = 'Test email rule evaluation with current AI provider configuration';

    public function handle()
    {
        $emailId = $this->argument('email_id');
        
        // Show current configuration
        $this->info('=== Prism Configuration ===');
        $provider = config('prism.default');
        $model = config("prism.providers.{$provider}.model");
        $hasApiKey = !empty(config("prism.providers.{$provider}.api_key"));
        
        $this->line("Provider: {$provider}");
        $this->line("Model: {$model}");
        $this->line("API Key configured: " . ($hasApiKey ? 'Yes' : 'No'));
        $this->newLine();
        
        // Find email
        $email = Email::find($emailId);
        if (!$email) {
            $this->error("Email with ID {$emailId} not found");
            return 1;
        }
        
        $this->info('=== Testing Email ===');
        $this->line("Subject: {$email->subject}");
        $this->line("From: {$email->from_email}");
        $rulesCount = $email->user->activeEmailRules()->count();
        $this->line("Active rules: {$rulesCount}");
        $this->newLine();
        
        if ($rulesCount === 0) {
            $this->warn("No active rules found for this user");
            return 0;
        }
        
        // Run evaluation
        $evaluator = app(EmailRuleEvaluator::class);
        
        $this->line("Starting rule evaluation...");
        
        try {
            $result = $evaluator->testRulesForEmail($email);
            
            $this->newLine();
            $this->info('✓ Evaluation successful!');
            $this->line("- Rules evaluated: {$result['rules_evaluated']}");
            $this->line("- Matched rules: " . count($result['matched_rules']));
            $this->line("- Execution time: {$result['execution_time_ms']}ms");
            $this->line("- AI Classification:");
            $this->line("  - Is automated: " . json_encode($result['ai_classification']['is_automated']));
            $this->line("  - Needs response: " . json_encode($result['ai_classification']['needs_response']));
            
            if (!empty($result['matched_rules'])) {
                $this->newLine();
                $this->info('=== Matched Rules ===');
                foreach ($result['matched_rules'] as $rule) {
                    $this->line("- {$rule['rule_name']} (ID: {$rule['rule_id']}, Type: {$rule['match_type']})");
                    if (!empty($rule['actions'])) {
                        foreach ($rule['actions'] as $action) {
                            $this->line("  → {$action['description']}");
                        }
                    }
                }
            }
            
            if (!empty($result['non_matched_rules'])) {
                $this->newLine();
                $this->info('=== Non-Matched Rules ===');
                foreach ($result['non_matched_rules'] as $rule) {
                    $this->line("- {$rule['rule_name']} (ID: {$rule['rule_id']})");
                }
            }
            
            return 0;
            
        } catch (\Exception $e) {
            $this->newLine();
            $this->error('✗ Evaluation failed: ' . $e->getMessage());
            
            if (strpos($e->getMessage(), 'x-api-key header is required') !== false) {
                $this->newLine();
                $this->error('This error indicates the API key is not being sent correctly.');
                $this->error("Please check that " . strtoupper($provider) . "_API_KEY is set in your .env file.");
            }
            
            return 1;
        }
    }
}
