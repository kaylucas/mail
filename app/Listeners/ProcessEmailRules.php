<?php

namespace App\Listeners;

use App\Events\EmailCreated;
use App\Events\EmailUpdated;
use App\Jobs\ProcessEmailRulesJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ProcessEmailRules implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The name of the queue the job should be sent to.
     *
     * @var string|null
     */
    public $queue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The maximum number of seconds the job should run.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        $this->queue = config('email_rules.queue');
    }

    /**
     * Handle the event.
     */
    public function handle(EmailCreated|EmailUpdated $event): void
    {
        // Check if email rules are enabled
        if (!config('email_rules.enabled')) {
            return;
        }

        // For EmailUpdated events, check if we should skip processing
        if ($event instanceof EmailUpdated) {
            $skipFields = config('email_rules.skip_update_fields', []);
            $changedFields = array_keys($event->changes);
            
            // Check if all changed fields are in the skip list
            $shouldSkip = !empty($changedFields) && 
                          empty(array_diff($changedFields, $skipFields));
            
            if ($shouldSkip) {
                return;
            }
        }

        // Dispatch the job to process email rules
        ProcessEmailRulesJob::dispatch($event->email->id, $event->email->user_id)
            ->onQueue($this->queue);
    }
}
