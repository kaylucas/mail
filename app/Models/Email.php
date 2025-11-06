<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Email extends Model
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
        'email_folder_id',
        'message_id',
        'internet_message_id',
        'conversation_id',
        'subject',
        'body_preview',
        'body_content',
        'body_content_type',
        'from_name',
        'from_email',
        'to_recipients',
        'cc_recipients',
        'bcc_recipients',
        'reply_to',
        'sender_name',
        'sender_email',
        'received_date_time',
        'sent_date_time',
        'has_attachments',
        'is_read',
        'is_draft',
        'importance',
        'flag_status',
        'categories',
        'web_link',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'to_recipients' => 'array',
            'cc_recipients' => 'array',
            'bcc_recipients' => 'array',
            'reply_to' => 'array',
            'categories' => 'array',
            'received_date_time' => 'datetime',
            'sent_date_time' => 'datetime',
            'has_attachments' => 'boolean',
            'is_read' => 'boolean',
            'is_draft' => 'boolean',
        ];
    }

    /**
     * Get the user that owns the email.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Office365 connection that owns the email.
     */
    public function office365Connection(): BelongsTo
    {
        return $this->belongsTo(Office365Connection::class);
    }

    /**
     * Get the folder that contains the email.
     */
    public function folder(): BelongsTo
    {
        return $this->belongsTo(EmailFolder::class, 'email_folder_id');
    }

    /**
     * Get the attachments for the email.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    /**
     * Scope a query to only include unread emails.
     */
    public function scopeUnread($query)
    {
        return $query->where('is_read', false);
    }

    /**
     * Scope a query to only include emails with attachments.
     */
    public function scopeWithAttachments($query)
    {
        return $query->where('has_attachments', true);
    }

    /**
     * Scope a query to only include emails in a specific folder.
     */
    public function scopeInFolder($query, $folderId)
    {
        return $query->where('email_folder_id', $folderId);
    }

    /**
     * Scope a query to only include emails from a specific sender.
     */
    public function scopeFromSender($query, $email)
    {
        return $query->where('from_email', $email);
    }

    /**
     * Scope a query to order emails by most recent first.
     */
    public function scopeRecent($query)
    {
        return $query->orderBy('received_date_time', 'desc');
    }

    /**
     * Mark the email as read.
     */
    public function markAsRead(): bool
    {
        return $this->update(['is_read' => true]);
    }

    /**
     * Mark the email as unread.
     */
    public function markAsUnread(): bool
    {
        return $this->update(['is_read' => false]);
    }

    /**
     * Toggle the read status of the email.
     */
    public function toggleRead(): bool
    {
        return $this->update(['is_read' => ! $this->is_read]);
    }
}
