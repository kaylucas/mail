<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailAttachment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<string>
     */
    protected $fillable = [
        'email_id',
        'attachment_id',
        'name',
        'content_type',
        'size',
        'is_inline',
        'content_id',
        'content_location',
        'content_bytes',
        'download_url',
        'last_modified_date_time',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_inline' => 'boolean',
            'size' => 'integer',
            'last_modified_date_time' => 'datetime',
        ];
    }

    /**
     * Get the email that owns the attachment.
     */
    public function email(): BelongsTo
    {
        return $this->belongsTo(Email::class);
    }

    /**
     * Scope a query to only include inline attachments.
     */
    public function scopeInline($query)
    {
        return $query->where('is_inline', true);
    }

    /**
     * Scope a query to only include regular attachments.
     */
    public function scopeRegular($query)
    {
        return $query->where('is_inline', false);
    }

    /**
     * Scope a query to filter by content type.
     */
    public function scopeByType($query, $contentType)
    {
        return $query->where('content_type', 'like', '%'.$contentType.'%');
    }

    /**
     * Check if the attachment is an image.
     */
    public function isImage(): bool
    {
        return str_starts_with($this->content_type, 'image/');
    }

    /**
     * Check if the attachment is a PDF.
     */
    public function isPdf(): bool
    {
        return $this->content_type === 'application/pdf';
    }

    /**
     * Check if the attachment is a document.
     */
    public function isDocument(): bool
    {
        $documentTypes = [
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        ];

        return in_array($this->content_type, $documentTypes);
    }

    /**
     * Get human-readable file size.
     */
    public function getHumanReadableSize(): string
    {
        $bytes = $this->size;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2).' '.$units[$i];
    }
}
