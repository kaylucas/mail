<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookNotification extends Model
{
    use HasFactory, MassPrunable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'subscription_id',
        'change_type',
        'resource',
        'resource_data',
        'client_state',
        'tenant_id',
        'processed_at',
        'processing_attempts',
        'last_error',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resource_data' => 'array',
            'processed_at' => 'datetime',
            'processing_attempts' => 'integer',
        ];
    }

    /**
     * Get the graph subscription that owns the notification.
     */
    public function graphSubscription(): BelongsTo
    {
        return $this->belongsTo(GraphSubscription::class, 'subscription_id', 'subscription_id');
    }

    /**
     * Alias for graphSubscription relationship.
     */
    public function subscription(): BelongsTo
    {
        return $this->graphSubscription();
    }

    /**
     * Scope a query to only include unprocessed notifications.
     */
    public function scopeUnprocessed($query)
    {
        return $query->whereNull('processed_at');
    }

    /**
     * Scope a query to only include processed notifications.
     */
    public function scopeProcessed($query)
    {
        return $query->whereNotNull('processed_at');
    }

    /**
     * Scope a query to only include failed notifications.
     */
    public function scopeFailed($query)
    {
        return $query->whereNull('processed_at')
            ->where('processing_attempts', '>=', 3);
    }

    /**
     * Scope a query to only include retryable notifications.
     */
    public function scopeRetryable($query)
    {
        return $query->whereNull('processed_at')
            ->where('processing_attempts', '<', 3);
    }

    /**
     * Scope a query to filter by change type.
     */
    public function scopeByChangeType($query, $changeType)
    {
        return $query->where('change_type', $changeType);
    }

    /**
     * Scope a query to only include notifications older than a certain number of days.
     */
    public function scopeOlderThan($query, $days)
    {
        return $query->where('created_at', '<', now()->subDays($days));
    }

    /**
     * Mark the notification as processed.
     */
    public function markAsProcessed(): bool
    {
        return $this->update(['processed_at' => now()]);
    }

    /**
     * Increment the processing attempts counter.
     */
    public function incrementAttempts(): bool
    {
        return $this->increment('processing_attempts');
    }

    /**
     * Record an error and increment attempts atomically.
     */
    public function recordError(string $error): bool
    {
        $this->increment('processing_attempts');
        return $this->update(['last_error' => $error]);
    }

    /**
     * Check if the notification has been processed.
     */
    public function isProcessed(): bool
    {
        return $this->processed_at !== null;
    }

    /**
     * Check if the notification can be retried.
     */
    public function canRetry(): bool
    {
        return $this->processing_attempts < 3;
    }

    /**
     * Check if the notification has failed.
     */
    public function isFailed(): bool
    {
        return $this->processing_attempts >= 3 && !$this->isProcessed();
    }

    /**
     * Get the prunable model query.
     *
     * Prune processed notifications older than 30 days.
     */
    public function prunable()
    {
        return static::whereNotNull('processed_at')
            ->where('created_at', '<=', now()->subDays(30));
    }
}
