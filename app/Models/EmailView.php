<?php

namespace App\Models;

use App\Models\Email;
use App\Models\EmailRuleExecution;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class EmailView extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'icon',
        'color',
        'is_visible',
        'order',
    ];

    protected $casts = [
        'is_visible' => 'boolean',
        'order' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rules(): BelongsToMany
    {
        return $this->belongsToMany(EmailRule::class, 'email_view_rules');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_visible', true);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('order', 'asc')->orderBy('created_at', 'asc');
    }

    public function getEmailCount(): int
    {
        $ruleIds = $this->getRuleIds();

        if ($ruleIds->isEmpty()) {
            return 0;
        }

        return EmailRuleExecution::where('user_id', $this->user_id)
            ->where('evaluation_result', true)
            ->whereIn('email_rule_id', $ruleIds)
            ->distinct('email_id')
            ->count('email_id');
    }

    public function getUnreadEmailCount(): int
    {
        $ruleIds = $this->getRuleIds();

        if ($ruleIds->isEmpty()) {
            return 0;
        }

        return Email::where('user_id', $this->user_id)
            ->where('is_read', false)
            ->whereIn('id', function ($query) use ($ruleIds) {
                $query->select('email_id')
                    ->from('email_rule_executions')
                    ->where('evaluation_result', true)
                    ->whereIn('email_rule_id', $ruleIds);
            })
            ->distinct('id')
            ->count('id');
    }

    protected function getRuleIds()
    {
        if ($this->relationLoaded('rules')) {
            return $this->rules->pluck('id');
        }

        return $this->rules()->pluck('email_rules.id');
    }
}
