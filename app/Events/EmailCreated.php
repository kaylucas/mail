<?php

namespace App\Events;

use App\Models\Email;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EmailCreated implements ShouldDispatchAfterCommit
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The email instance.
     *
     * @var \App\Models\Email
     */
    public $email;

    /**
     * Create a new event instance.
     */
    public function __construct(Email $email)
    {
        $this->email = $email;
    }
}
