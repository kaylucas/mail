<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EmailRuleExecution extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'email_rule_id',
        'user_id',
        'ai_provider',
        'ai_model',
        'prompt_sent',
        'ai_response',
        'evaluation_result',
        'actions_executed',
        'execution_time_ms',
        'error_message',
    ];

    protected $casts = [
        'ai_response' => 'array',
        'actions_executed' => 'array',
        'evaluation_result' => 'boolean',
        'execution_time_ms' => 'integer',
    ];

    public $timestamps = false;

    /**
     * Get the email that was evaluated.
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * Get the rule that was executed.
     */
    public function emailRule(): BelongsTo
    {
        return $this->belongsTo(EmailRule::class);
    }

    /**
     * Get the user that owns this execution.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope a query to only include executions for a specific email.
     */
    public function scopeForEmail(Builder $query, int $emailId): Builder
    {
        return $query->where('email_id', $emailId);
    }

    /**
     * Scope a query to only include executions for a specific rule.
     */
    public function scopeForRule(Builder $query, int $ruleId): Builder
    {
        return $query->where('email_rule_id', $ruleId);
    }

    /**
     * Scope a query to only include successful executions.
     */
    public function scopeSuccessful(Builder $query): Builder
    {
        return $query->where('evaluation_result', true);
    }

    /**
     * Scope a query to only include failed executions.
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->whereNotNull('error_message');
    }
}
