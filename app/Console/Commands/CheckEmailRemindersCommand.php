<?php

namespace App\Console\Commands;

use App\Jobs\SendReminderNotificationJob;
use App\Models\EmailReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckEmailRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'email:check-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check for due email reminders and trigger notifications';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Checking for due email reminders...');

        try {
            // Load due reminders with relationships
            $dueReminders = EmailReminder::due()
                ->with(['email', 'user'])
                ->get();

            if ($dueReminders->isEmpty()) {
                $this->info('No due reminders found.');
                return self::SUCCESS;
            }

            $this->info("Found {$dueReminders->count()} due reminder(s).");

            $processed = 0;
            $skipped = 0;
            $triggered = 0;
            $completed = 0;

            foreach ($dueReminders as $reminder) {
                try {
                    // Verify email and user exist
                    if (!$reminder->email || !$reminder->user) {
                        $this->warn("Skipping reminder {$reminder->id}: missing email or user");
                        $skipped++;
                        continue;
                    }

                    // Check if email has been replied to (heuristic)
                    $hasReply = $this->checkForReply($reminder);

                    if ($hasReply) {
                        // Mark as completed - no need to send reminder
                        $reminder->update([
                            'status' => EmailReminder::STATUS_COMPLETED,
                            'completed_at' => now(),
                        ]);

                        $this->info("Reminder {$reminder->id}: Email has been replied to, marked as completed");
                        $completed++;
                    } else {
                        // Trigger notification
                        $reminder->update([
                            'status' => EmailReminder::STATUS_TRIGGERED,
                            'triggered_at' => now(),
                        ]);

                        // Dispatch notification job
                        SendReminderNotificationJob::dispatch($reminder->id);

                        $this->info("Reminder {$reminder->id}: Notification triggered");
                        $triggered++;
                    }

                    $processed++;

                } catch (\Exception $e) {
                    $this->error("Error processing reminder {$reminder->id}: {$e->getMessage()}");
                    Log::error('Error processing reminder in command', [
                        'reminder_id' => $reminder->id,
                        'error' => $e->getMessage(),
                    ]);
                    $skipped++;
                }
            }

            $this->info("\nSummary:");
            $this->info("- Total processed: {$processed}");
            $this->info("- Triggered: {$triggered}");
            $this->info("- Completed (replied): {$completed}");
            $this->info("- Skipped (errors): {$skipped}");

            Log::info('Email reminders check completed', [
                'total_due' => $dueReminders->count(),
                'processed' => $processed,
                'triggered' => $triggered,
                'completed' => $completed,
                'skipped' => $skipped,
            ]);

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Fatal error checking reminders: {$e->getMessage()}");
            Log::error('Fatal error in CheckEmailRemindersCommand', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        }
    }

    /**
     * Check if the email has been replied to (heuristic).
     *
     * @param EmailReminder $reminder
     * @return bool
     */
    private function checkForReply(EmailReminder $reminder): bool
    {
        $email = $reminder->email;
        $user = $reminder->user;

        try {
            // Heuristic 1: Check if there are any emails sent after this one
            // with "Re:" in the subject or same conversation ID
            $replyExists = \App\Models\Email::where('user_id', $user->id)
                ->where('id', '!=', $email->id)
                ->where(function ($query) use ($email) {
                    // Check for "Re:" subject line
                    $query->where('subject', 'like', 'Re: %' . $email->subject)
                        ->orWhere('subject', 'like', 'RE: %' . $email->subject);

                    // If conversation_id exists, check for same conversation
                    if ($email->conversation_id) {
                        $query->orWhere('conversation_id', $email->conversation_id);
                    }
                })
                ->where('received_date_time', '>', $email->received_date_time)
                ->exists();

            return $replyExists;

        } catch (\Exception $e) {
            Log::warning('Error checking for reply', [
                'reminder_id' => $reminder->id,
                'email_id' => $email->id,
                'error' => $e->getMessage(),
            ]);

            // On error, assume no reply to ensure reminder is sent
            return false;
        }
    }
}
