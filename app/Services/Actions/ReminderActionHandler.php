<?php

namespace App\Services\Actions;

use App\Models\Email;
use App\Models\EmailReminder;
use App\Models\EmailRuleAction;
use Illuminate\Support\Facades\Log;

class ReminderActionHandler
{
    /**
     * Execute reminder action on an email.
     *
     * @param Email $email
     * @param EmailRuleAction $action
     * @return array
     */
    public function execute(Email $email, EmailRuleAction $action): array
    {
        try {
            $config = $action->action_config;

            if (!isset($config['days_after'])) {
                return [
                    'success' => false,
                    'error' => 'days_after not specified in action config',
                ];
            }

            $daysAfter = (int) $config['days_after'];
            $message = $config['message'] ?? 'Reminder to follow up on this email';

            if ($daysAfter < 1) {
                return [
                    'success' => false,
                    'error' => 'days_after must be at least 1',
                ];
            }

            // Calculate reminder date
            $remindAt = $email->received_date_time
                ? $email->received_date_time->copy()->addDays($daysAfter)
                : now()->addDays($daysAfter);

            // Create reminder
            $reminder = EmailReminder::create([
                'user_id' => $email->user_id,
                'email_id' => $email->id,
                'rule_id' => $action->email_rule_id,
                'remind_at' => $remindAt,
                'message' => $message,
                'status' => 'pending',
            ]);

            Log::info('Email reminder created', [
                'email_id' => $email->id,
                'rule_id' => $action->email_rule_id,
                'reminder_id' => $reminder->id,
                'remind_at' => $remindAt->toIso8601String(),
                'days_after' => $daysAfter,
            ]);

            return [
                'success' => true,
                'action_type' => 'reminder',
                'reminder_id' => $reminder->id,
                'remind_at' => $remindAt->toIso8601String(),
                'days_after' => $daysAfter,
                'message' => $message,
            ];

        } catch (\Exception $e) {
            Log::error('Reminder action execution failed', [
                'email_id' => $email->id,
                'action_id' => $action->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
