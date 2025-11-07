<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EmailReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'user_id',
        'applied_by_rule_id',
        'remind_at',
        'message',
        'status',
        'triggered_at',
    ];

    protected $casts = [
        'remind_at' => 'datetime',
        'triggered_at' => 'datetime',
    ];

    /**
     * Get the email that owns the reminder.
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * Get the user that owns the reminder.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the rule that created this reminder.
     */
    public function appliedByRule(): BelongsTo
    {
        return $this->belongsTo(EmailRule::class, 'applied_by_rule_id');
    }

    /**
     * Scope a query to only include pending reminders.
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope a query to only include due reminders.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', 'pending')
                     ->where('remind_at', '<=', now());
    }

    /**
     * Scope a query to only include reminders for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Mark the reminder as triggered.
     */
    public function markAsTriggered(): void
    {
        $this->update([
            'status' => 'triggered',
            'triggered_at' => now(),
        ]);
    }

    /**
     * Mark the reminder as dismissed.
     */
    public function markAsDismissed(): void
    {
        $this->update(['status' => 'dismissed']);
    }

    /**
     * Mark the reminder as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update(['status' => 'completed']);
    }
}
