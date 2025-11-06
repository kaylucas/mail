<?php

namespace App\Models;

use Carbon\CarbonInterval;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GraphSubscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'office365_connection_id',
        'subscription_id',
        'resource',
        'change_types',
        'notification_url',
        'client_state',
        'expires_at',
        'last_renewed_at',
        'status',
        'failure_reason',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'change_types' => 'array',
            'expires_at' => 'datetime',
            'last_renewed_at' => 'datetime',
        ];
    }

    /**
     * Get the user that owns the subscription.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Office365 connection that owns the subscription.
     */
    public function office365Connection(): BelongsTo
    {
        return $this->belongsTo(Office365Connection::class);
    }

    /**
     * Get the webhook notifications for this subscription.
     */
    public function webhookNotifications(): HasMany
    {
        return $this->hasMany(WebhookNotification::class, 'subscription_id', 'subscription_id');
    }

    /**
     * Scope a query to only include active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    /**
     * Scope a query to only include expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope a query to only include subscriptions expiring soon.
     */
    public function scopeExpiringSoon($query, $hours = 24)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '<=', now()->addHours($hours))
            ->where('expires_at', '>', now());
    }

    /**
     * Scope a query to only include subscriptions that need renewal.
     */
    public function scopeNeedsRenewal($query, $thresholdHours = 12)
    {
        return $query->where('status', 'active')
            ->where('expires_at', '<=', now()->addHours($thresholdHours));
    }

    /**
     * Scope a query to filter by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Check if the subscription is expired.
     */
    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return true;
        }

        return $this->expires_at->isPast();
    }

    /**
     * Check if the subscription is expiring soon.
     */
    public function isExpiringSoon(int $hours = 24): bool
    {
        if (! $this->expires_at) {
            return true;
        }

        return $this->expires_at->lessThanOrEqualTo(now()->addHours($hours))
            && $this->expires_at->greaterThan(now());
    }

    /**
     * Check if the subscription needs renewal.
     */
    public function needsRenewal(int $thresholdHours = 12): bool
    {
        if (! $this->expires_at) {
            return true;
        }

        return $this->status === 'active'
            && $this->expires_at->lessThanOrEqualTo(now()->addHours($thresholdHours));
    }

    /**
     * Mark the subscription as expired.
     */
    public function markAsExpired(): bool
    {
        return $this->update(['status' => 'expired']);
    }

    /**
     * Mark the subscription as active.
     */
    public function markAsActive(): bool
    {
        return $this->update(['status' => 'active']);
    }

    /**
     * Mark the subscription as failed.
     */
    public function markAsFailed(string $reason): bool
    {
        return $this->update([
            'status' => 'failed',
            'failure_reason' => $reason,
        ]);
    }

    /**
     * Mark the subscription as renewed (standard renewal - does not change subscription_id).
     *
     * Note: If Microsoft returns a new subscription ID during renewal, create a new
     * GraphSubscription record instead and mark the old one as expired. Updating
     * subscription_id can orphan existing WebhookNotifications.
     */
    public function markAsRenewed(\Carbon\Carbon $expiresAt): bool
    {
        return $this->update([
            'expires_at' => $expiresAt,
            'last_renewed_at' => now(),
            'status' => 'active',
            'failure_reason' => null,
        ]);
    }

    /**
     * Get the time until expiration.
     */
    public function getTimeUntilExpiration(): CarbonInterval
    {
        if (! $this->expires_at) {
            return CarbonInterval::seconds(0);
        }

        return now()->diffAsCarbonInterval($this->expires_at);
    }
}
