<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'microsoft_id',
        'avatar',
        'email_delta_token',
        'last_email_sync_at',
        'current_sync_job_id',
        'sync_started_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_email_sync_at' => 'datetime',
            'sync_started_at' => 'datetime',
        ];
    }

    /**
     * Get the Office365 connection for the user.
     */
    public function office365Connection()
    {
        return $this->hasOne(Office365Connection::class);
    }

    /**
     * Get the email folders for the user.
     */
    public function emailFolders()
    {
        return $this->hasMany(EmailFolder::class);
    }

    /**
     * Get the emails for the user.
     */
    public function emails()
    {
        return $this->hasMany(Email::class);
    }

    /**
     * Get the graph subscriptions for the user.
     */
    public function graphSubscriptions()
    {
        return $this->hasMany(GraphSubscription::class);
    }

    /**
     * Get the active email subscription for the user.
     */
    public function activeEmailSubscription()
    {
        return $this->hasOne(GraphSubscription::class)
            ->where('status', 'active')
            ->where('expires_at', '>', now());
    }

    /**
     * Check if the user has a delta token.
     */
    public function hasDeltaToken(): bool
    {
        return $this->email_delta_token !== null;
    }

    /**
     * Clear the delta token.
     */
    public function clearDeltaToken(): bool
    {
        return $this->update(['email_delta_token' => null]);
    }

    /**
     * Update the delta token.
     */
    public function updateDeltaToken(string $token): bool
    {
        return $this->update([
            'email_delta_token' => $token,
            'last_email_sync_at' => now(),
        ]);
    }
}

    /**
     * Get the email rules for the user.
     */
    public function emailRules()
    {
        return $this->hasMany(EmailRule::class);
    }

    /**
     * Get the email reminders for the user.
     */
    public function emailReminders()
    {
        return $this->hasMany(EmailReminder::class);
    }

    /**
     * Get the active email rules for the user.
     */
    public function activeEmailRules()
    {
        return $this->hasMany(EmailRule::class)
            ->active()
            ->ordered();
    }
