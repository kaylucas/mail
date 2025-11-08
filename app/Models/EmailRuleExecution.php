<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EmailRuleExecution extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'email_rule_id',
        'email_id',
        'user_id',
        'ai_provider',
        'ai_model',
        'prompt_sent',
        'ai_response',
        'evaluation_result',
        'actions_executed',
        'actions_taken',
        'error_message',
        'execution_time_ms',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_response' => 'array',
            'actions_taken' => 'array',
            'evaluation_result' => 'boolean',
            'actions_executed' => 'integer',
            'execution_time_ms' => 'integer',
        ];
    }

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
