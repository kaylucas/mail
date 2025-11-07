<?php

namespace App\Models;

use App\Services\Actions\LabelActionHandler;
use App\Services\Actions\ForwardActionHandler;
use App\Services\Actions\ReminderActionHandler;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailRuleAction extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_rule_id',
        'action_type',
        'action_config',
    ];

    protected $casts = [
        'action_config' => 'array',
    ];

    /**
     * Get the email rule that owns the action.
     */
    public function emailRule(): BelongsTo
    {
        return $this->belongsTo(EmailRule::class);
    }

    /**
     * Get the appropriate handler for this action type.
     */
    public function getHandler()
    {
        return match ($this->action_type) {
            'add_label' => new LabelActionHandler(),
            'forward' => new ForwardActionHandler(),
            'add_reminder' => new ReminderActionHandler(),
            default => throw new \InvalidArgumentException("Unknown action type: {$this->action_type}"),
        };
    }

    /**
     * Execute this action on the given email.
     */
    public function execute(Email $email): array
    {
        return $this->getHandler()->execute($email, $this);
    }
}
