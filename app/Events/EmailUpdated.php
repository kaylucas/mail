<?php

namespace App\Events;

use App\Models\Email;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailUpdated implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The email instance.
     *
     * @var \App\Models\Email
     */
    public $email;

    /**
     * The changes made to the email.
     *
     * @var array
     */
    public $changes;

    /**
     * Create a new event instance.
     */
    public function __construct(Email $email, array $changes)
    {
        $this->email = $email;
        $this->changes = $changes;
    }
}
