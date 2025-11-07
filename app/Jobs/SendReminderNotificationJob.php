<?php

namespace App\Jobs;

use App\Models\EmailReminder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Message;

class SendReminderNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 60;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $reminderId
    ) {
        $this->onQueue(config('email_rules.queue_name', 'email-rules'));
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('SendReminderNotificationJob started', [
            'reminder_id' => $this->reminderId,
            'attempt' => $this->attempts(),
        ]);

        try {
            // Load reminder with relationships
            $reminder = EmailReminder::with(['email', 'user'])->find($this->reminderId);

            if (!$reminder) {
                Log::warning('Reminder not found', [
                    'reminder_id' => $this->reminderId,
                ]);
                return;
            }

            // Verify status is 'triggered'
            if ($reminder->status !== 'triggered') {
                Log::info('Reminder status is not triggered, skipping notification', [
                    'reminder_id' => $this->reminderId,
                    'status' => $reminder->status,
                ]);
                return;
            }

            if (!$reminder->user) {
                Log::error('User not found for reminder', [
                    'reminder_id' => $this->reminderId,
                    'user_id' => $reminder->user_id,
                ]);
                return;
            }

            if (!$reminder->email) {
                Log::error('Email not found for reminder', [
                    'reminder_id' => $this->reminderId,
                    'email_id' => $reminder->email_id,
                ]);
                return;
            }

            // Send reminder notification email
            $this->sendReminderEmail($reminder);

            // Mark reminder as completed
            $reminder->update([
                'status' => EmailReminder::STATUS_COMPLETED,
                'completed_at' => now(),
            ]);

            Log::info('Reminder notification sent successfully', [
                'reminder_id' => $this->reminderId,
                'user_id' => $reminder->user_id,
                'email_id' => $reminder->email_id,
            ]);

        } catch (\Exception $e) {
            Log::error('SendReminderNotificationJob exception', [
                'reminder_id' => $this->reminderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Send the reminder notification email.
     *
     * @param EmailReminder $reminder
     * @return void
     */
    private function sendReminderEmail(EmailReminder $reminder): void
    {
        $email = $reminder->email;
        $user = $reminder->user;

        $subject = "Reminder: " . ($email->subject ?? '(No Subject)');
        $body = $this->buildReminderEmailBody($reminder);

        Mail::send([], [], function (Message $message) use ($user, $subject, $body) {
            $message->to($user->email)
                ->subject($subject)
                ->html($body);
        });
    }

    /**
     * Build the reminder email body.
     *
     * @param EmailReminder $reminder
     * @return string
     */
    private function buildReminderEmailBody(EmailReminder $reminder): string
    {
        $email = $reminder->email;

        $html = '<html><body style="font-family: Arial, sans-serif; font-size: 14px; color: #333;">';

        $html .= '<h2 style="color: #007bff;">Email Reminder</h2>';

        $html .= '<p>' . htmlspecialchars($reminder->message) . '</p>';

        $html .= '<div style="background-color: #f5f5f5; padding: 15px; border-left: 3px solid #007bff; margin-top: 20px;">';
        $html .= '<h3 style="margin-top: 0;">Original Email</h3>';

        $html .= '<p style="margin: 5px 0;"><strong>From:</strong> ' . htmlspecialchars($email->from_name ?? '') . ' &lt;' . htmlspecialchars($email->from_email ?? '') . '&gt;</p>';

        if ($email->received_date_time) {
            $html .= '<p style="margin: 5px 0;"><strong>Date:</strong> ' . $email->received_date_time->format('F j, Y \a\t g:i A') . '</p>';
        }

        $html .= '<p style="margin: 5px 0;"><strong>Subject:</strong> ' . htmlspecialchars($email->subject ?? '(No Subject)') . '</p>';

        $bodyPreview = $email->body_preview ?? '';
        if (strlen($bodyPreview) > 300) {
            $bodyPreview = substr($bodyPreview, 0, 300) . '...';
        }

        if ($bodyPreview) {
            $html .= '<p style="margin-top: 10px;"><strong>Preview:</strong></p>';
            $html .= '<p style="font-style: italic; color: #666;">' . nl2br(htmlspecialchars($bodyPreview)) . '</p>';
        }

        $html .= '</div>';

        // Add link to view email (adjust URL based on your frontend routes)
        $emailUrl = config('app.frontend_url', 'http://localhost:5173') . '/#/emails/' . $email->id;
        $html .= '<p style="margin-top: 20px;"><a href="' . $emailUrl . '" style="color: #007bff; text-decoration: none;">View Email</a></p>';

        $html .= '<hr style="border: none; border-top: 1px solid #ccc; margin: 30px 0;">';
        $html .= '<p style="font-size: 12px; color: #999;">This reminder was automatically generated by your email rules.</p>';

        $html .= '</body></html>';

        return $html;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendReminderNotificationJob failed after all retries', [
            'reminder_id' => $this->reminderId,
            'error' => $exception->getMessage(),
        ]);
    }
}
