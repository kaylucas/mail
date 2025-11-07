<?php

namespace App\Services\Actions;

use App\Models\Email;
use App\Models\EmailRuleAction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class ForwardActionHandler
{
    /**
     * Execute forward action on an email.
     *
     * @param Email $email
     * @param EmailRuleAction $action
     * @return array
     */
    public function execute(Email $email, EmailRuleAction $action): array
    {
        try {
            $config = $action->action_config;

            if (!isset($config['email_addresses']) || empty($config['email_addresses'])) {
                return [
                    'success' => false,
                    'error' => 'No email addresses specified in action config',
                ];
            }

            $emailAddresses = $config['email_addresses'];
            $includeNote = $config['include_note'] ?? true;

            // Validate email addresses
            if (!is_array($emailAddresses)) {
                $emailAddresses = [$emailAddresses];
            }

            $validAddresses = array_filter($emailAddresses, function ($address) {
                return filter_var($address, FILTER_VALIDATE_EMAIL);
            });

            if (empty($validAddresses)) {
                return [
                    'success' => false,
                    'error' => 'No valid email addresses to forward to',
                ];
            }

            // Build forwarded email
            $subject = "Fwd: " . ($email->subject ?? '(No Subject)');

            $bodyPreview = $email->body_preview ?? '';
            if (strlen($bodyPreview) > 1000) {
                $bodyPreview = substr($bodyPreview, 0, 1000) . '...';
            }

            $body = $this->buildForwardedEmailBody($email, $bodyPreview, $includeNote);

            // Queue email for sending
            Mail::queue([], [], function (Message $message) use ($validAddresses, $subject, $body) {
                $message->to($validAddresses)
                    ->subject($subject)
                    ->html($body);
            });

            Log::info('Email forwarded', [
                'email_id' => $email->id,
                'rule_id' => $action->email_rule_id,
                'forwarded_to' => $validAddresses,
            ]);

            return [
                'success' => true,
                'action_type' => 'forward',
                'forwarded_to' => $validAddresses,
                'queued' => true,
            ];

        } catch (\Exception $e) {
            Log::error('Forward action execution failed', [
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

    /**
     * Build the forwarded email body with original email details.
     *
     * @param Email $email
     * @param string $bodyPreview
     * @param bool $includeNote
     * @return string
     */
    private function buildForwardedEmailBody(Email $email, string $bodyPreview, bool $includeNote): string
    {
        $html = '<html><body style="font-family: Arial, sans-serif; font-size: 14px; color: #333;">';

        if ($includeNote) {
            $html .= '<p><em>This email was automatically forwarded by an email rule.</em></p>';
            $html .= '<hr style="border: none; border-top: 1px solid #ccc; margin: 20px 0;">';
        }

        $html .= '<div style="background-color: #f5f5f5; padding: 15px; border-left: 3px solid #007bff;">';
        $html .= '<p style="margin: 5px 0;"><strong>From:</strong> ' . htmlspecialchars($email->from_name ?? '') . ' &lt;' . htmlspecialchars($email->from_email ?? '') . '&gt;</p>';

        if ($email->received_date_time) {
            $html .= '<p style="margin: 5px 0;"><strong>Date:</strong> ' . $email->received_date_time->format('F j, Y \a\t g:i A') . '</p>';
        }

        $html .= '<p style="margin: 5px 0;"><strong>Subject:</strong> ' . htmlspecialchars($email->subject ?? '(No Subject)') . '</p>';

        if (is_array($email->to_recipients) && !empty($email->to_recipients)) {
            $toAddresses = array_map(fn($r) => htmlspecialchars($r['email'] ?? ''), $email->to_recipients);
            $html .= '<p style="margin: 5px 0;"><strong>To:</strong> ' . implode(', ', $toAddresses) . '</p>';
        }

        $html .= '</div>';

        $html .= '<div style="margin-top: 20px;">';
        $html .= nl2br(htmlspecialchars($bodyPreview));
        $html .= '</div>';

        $html .= '</body></html>';

        return $html;
    }
}
