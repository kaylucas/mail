<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class EmailRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
        'prompt',
        'simple_conditions',
        'is_active',
        'priority',
        'ai_provider',
        'ai_model',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'priority' => 'integer',
        'simple_conditions' => 'array',
    ];

    /**
     * Get the user that owns the email rule.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the actions for the email rule.
     */
    public function actions(): HasMany
    {
        return $this->hasMany(EmailRuleAction::class);
    }

    /**
     * Get the executions for the email rule.
     */
    public function executions(): HasMany
    {
        return $this->hasMany(EmailRuleExecution::class);
    }

    /**
     * Scope a query to only include active rules.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope a query to only include rules for a specific user.
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope a query to order rules by priority.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority', 'asc')->orderBy('created_at', 'asc');
    }

    /**
     * Get the AI provider to use for this rule.
     */
    public function getAiProvider(): string
    {
        return $this->ai_provider ?: config('email_rules.default_provider');
    }

    /**
     * Get the AI model to use for this rule.
     */
    public function getAiModel(): string
    {
        if ($this->ai_model) {
            return $this->ai_model;
        }

        $provider = $this->getAiProvider();
        return config("prism.providers.{$provider}.model");
    }

    /**
     * Check if email matches simple conditions (pre-filter).
     */
    public function matchesSimpleConditions(Email $email): bool
    {
        if (empty($this->simple_conditions)) {
            return false; // No simple conditions, must use AI
        }

        $conditions = $this->simple_conditions;
        
        // Check 'from' condition
        if (!empty($conditions['from'])) {
            $from = strtolower($email->from_email ?? '');
            $fromName = strtolower($email->from_name ?? '');
            $searchTerm = strtolower($conditions['from']);
            
            if (!str_contains($from, $searchTerm) && !str_contains($fromName, $searchTerm)) {
                return false;
            }
        }

        // Check 'to' condition
        if (!empty($conditions['to'])) {
            $searchTerm = strtolower($conditions['to']);
            $found = false;

            if (is_array($email->to_recipients)) {
                foreach ($email->to_recipients as $recipient) {
                    $recipientEmail = strtolower($recipient['email'] ?? '');
                    if (str_contains($recipientEmail, $searchTerm)) {
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                return false;
            }
        }

        // Check 'subject' condition
        if (!empty($conditions['subject'])) {
            $subject = strtolower($email->subject ?? '');
            $searchTerm = strtolower($conditions['subject']);
            
            if (!str_contains($subject, $searchTerm)) {
                return false;
            }
        }

        // All specified conditions match
        return true;
    }
}
