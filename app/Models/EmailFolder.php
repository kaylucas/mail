<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailFolder extends Model
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
        'folder_id',
        'parent_folder_id',
        'display_name',
        'total_item_count',
        'unread_item_count',
        'child_folder_count',
        'is_hidden',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_hidden' => 'boolean',
            'total_item_count' => 'integer',
            'unread_item_count' => 'integer',
            'child_folder_count' => 'integer',
        ];
    }

    /**
     * Get the user that owns the email folder.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the Office365 connection that owns the email folder.
     */
    public function office365Connection(): BelongsTo
    {
        return $this->belongsTo(Office365Connection::class);
    }

    /**
     * Get the emails in this folder.
     */
    public function emails(): HasMany
    {
        return $this->hasMany(Email::class);
    }

    /**
     * Get the parent folder.
     *
     * Note: parent_folder_id stores the Graph folder ID (not the local numeric id).
     * This relates to another EmailFolder record via its folder_id column.
     */
    public function parentFolder(): BelongsTo
    {
        return $this->belongsTo(EmailFolder::class, 'parent_folder_id', 'folder_id');
    }

    /**
     * Get the child folders.
     *
     * Note: Children reference this record's folder_id via their parent_folder_id column.
     */
    public function childFolders(): HasMany
    {
        return $this->hasMany(EmailFolder::class, 'parent_folder_id', 'folder_id');
    }

    /**
     * Scope a query to only include root folders.
     */
    public function scopeRootFolders($query)
    {
        return $query->whereNull('parent_folder_id');
    }

    /**
     * Scope a query to only include visible folders.
     */
    public function scopeVisible($query)
    {
        return $query->where('is_hidden', false);
    }

    /**
     * Check if this is a root folder.
     */
    public function isRoot(): bool
    {
        return $this->parent_folder_id === null;
    }

    /**
     * Check if this folder has children.
     */
    public function hasChildren(): bool
    {
        return $this->child_folder_count > 0;
    }
}
