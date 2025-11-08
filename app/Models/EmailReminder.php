<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EmailReminder extends Model
{
    use HasFactory;

    /**
     * Status constants for type safety.
     */
    public const STATUS_PENDING = 'pending';
    public const STATUS_TRIGGERED = 'triggered';
    public const STATUS_DISMISSED = 'dismissed';
    public const STATUS_COMPLETED = 'completed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'email_id',
        'user_id',
        'reminder_text',
        'reminder_date',
        'status',
        'applied_by_rule_id',
        'triggered_at',
        'dismissed_at',
        'completed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'reminder_date' => 'datetime',
            'triggered_at' => 'datetime',
            'dismissed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

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
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope a query to only include due reminders.
     */
    public function scopeDue(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING)
                     ->where('reminder_date', '<=', now());
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
            'status' => self::STATUS_TRIGGERED,
            'triggered_at' => now(),
        ]);
    }

    /**
     * Mark the reminder as dismissed.
     */
    public function markAsDismissed(): void
    {
        $this->update([
            'status' => self::STATUS_DISMISSED,
            'dismissed_at' => now(),
        ]);
    }

    /**
     * Mark the reminder as completed.
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'status' => self::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }
}
