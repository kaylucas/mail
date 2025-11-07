<?php

namespace App\Services\Actions;

use App\Models\Email;
use App\Models\EmailLabel;
use App\Models\EmailRuleAction;
use Illuminate\Support\Facades\Log;

class LabelActionHandler
{
    /**
     * Execute label action on an email.
     *
     * @param Email $email
     * @param EmailRuleAction $action
     * @return array
     */
    public function execute(Email $email, EmailRuleAction $action): array
    {
        try {
            $config = $action->action_config;

            if (!isset($config['label_name'])) {
                return [
                    'success' => false,
                    'error' => 'Label name not specified in action config',
                ];
            }

            $labelName = $config['label_name'];

            // Create or update label
            $label = EmailLabel::firstOrCreate(
                [
                    'email_id' => $email->id,
                    'label_name' => $labelName,
                ],
                [
                    'applied_by_rule_id' => $action->email_rule_id,
                ]
            );

            // If label already exists, update the applied_by_rule_id
            if (!$label->wasRecentlyCreated) {
                $label->update([
                    'applied_by_rule_id' => $action->email_rule_id,
                ]);
            }

            Log::info('Label applied to email', [
                'email_id' => $email->id,
                'label_name' => $labelName,
                'rule_id' => $action->email_rule_id,
                'label_id' => $label->id,
                'was_created' => $label->wasRecentlyCreated,
            ]);

            return [
                'success' => true,
                'action_type' => 'label',
                'label_name' => $labelName,
                'label_id' => $label->id,
                'was_created' => $label->wasRecentlyCreated,
            ];

        } catch (\Exception $e) {
            Log::error('Label action execution failed', [
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
