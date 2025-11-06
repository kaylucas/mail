<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Office365Connection extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'user_id',
        'tenant_id',
        'client_id',
        'client_secret',
        'redirect_uri',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'is_active',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<string>
     */
    protected $hidden = [
        'client_secret',
        'access_token',
        'refresh_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'token_expires_at' => 'datetime',
            'last_sync_at' => 'datetime',
            'scopes' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the Office365 connection.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the email folders for this connection.
     */
    public function emailFolders()
    {
        return $this->hasMany(EmailFolder::class);
    }

    /**
     * Get the emails for this connection.
     */
    public function emails()
    {
        return $this->hasMany(Email::class);
    }

    /**
     * Get the graph subscriptions for this connection.
     */
    public function graphSubscriptions()
    {
        return $this->hasMany(GraphSubscription::class);
    }

    /**
     * Check if the access token is expired.
     */
    public function isTokenExpired(): bool
    {
        if (! $this->token_expires_at) {
            return true;
        }

        return $this->token_expires_at->isPast();
    }
}
