<?php

namespace App\Jobs;

use App\Models\Email;
use App\Services\EmailRuleEvaluator;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessEmailRulesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 180;
    public $maxExceptions = 3;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $emailId,
        public int $userId
    ) {
        $this->onQueue(config('email_rules.queue_name', 'email-rules'));
    }

    /**
     * Execute the job.
     */
    public function handle(EmailRuleEvaluator $evaluator): void
    {
        Log::info('ProcessEmailRulesJob started', [
            'email_id' => $this->emailId,
            'user_id' => $this->userId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Load email with user relationship
            $email = Email::with('user')->find($this->emailId);

            if (!$email) {
                Log::warning('Email not found for rule processing', [
                    'email_id' => $this->emailId,
                ]);
                return;
            }

            if (!$email->user) {
                Log::warning('User not found for email rule processing', [
                    'email_id' => $this->emailId,
                    'user_id' => $this->userId,
                ]);
                return;
            }

            // Verify user ID matches
            if ($email->user_id !== $this->userId) {
                Log::error('User ID mismatch in email rule processing', [
                    'email_id' => $this->emailId,
                    'expected_user_id' => $this->userId,
                    'actual_user_id' => $email->user_id,
                ]);
                return;
            }

            // Evaluate rules for email
            $summary = $evaluator->evaluateRulesForEmail($email);

            Log::info('ProcessEmailRulesJob completed', [
                'email_id' => $this->emailId,
                'user_id' => $this->userId,
                'summary' => $summary,
            ]);

        } catch (\Exception $e) {
            Log::error('ProcessEmailRulesJob exception', [
                'email_id' => $this->emailId,
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::critical('ProcessEmailRulesJob failed after all retries', [
            'email_id' => $this->emailId,
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);
    }
}
