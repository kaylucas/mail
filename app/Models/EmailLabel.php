<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class EmailLabel extends Model
{
    use HasFactory;

    protected $fillable = [
        'email_id',
        'label_name',
        'applied_by_rule_id',
    ];

    public $timestamps = false;

    /**
     * Get the email that owns the label.
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * Get the rule that applied this label.
     */
    public function appliedByRule(): BelongsTo
    {
        return $this->belongsTo(EmailRule::class, 'applied_by_rule_id');
    }

    /**
     * Scope a query to only include labels for a specific email.
     */
    public function scopeForEmail(Builder $query, int $emailId): Builder
    {
        return $query->where('email_id', $emailId);
    }

    /**
     * Scope a query to only include labels with a specific name.
     */
    public function scopeByLabel(Builder $query, string $labelName): Builder
    {
        return $query->where('label_name', $labelName);
    }
}
