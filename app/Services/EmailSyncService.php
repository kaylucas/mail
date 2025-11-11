<?php

namespace App\Services;

use App\Events\EmailCreated;
use App\Events\EmailUpdated;
use App\Models\Email;
use App\Models\EmailAttachment;
use App\Models\EmailFolder;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EmailSyncService
{
    protected Office365Service $office365Service;

    public function __construct(Office365Service $office365Service)
    {
        $this->office365Service = $office365Service;
    }

    /**
     * Sync all mail folders from Microsoft Graph to database
     *
     * @throws \Exception
     */
    public function syncFolders(User $user): array
    {
        try {
            Log::info('Starting folder sync', ['user_id' => $user->id]);

            $connection = $user->office365Connection;
            if (! $connection || ! $connection->is_active) {
                throw new \Exception("No active Office365 connection found for user {$user->id}");
            }

            // Check token expiration and refresh if needed
            if ($connection->isTokenExpired()) {
                Log::info('Access token expired, refreshing', ['user_id' => $user->id]);
                $this->office365Service->refreshAccessToken($connection);
                $connection->refresh();
            }

            $folderIds = [];
            $foldersCount = 0;

            // Fetch top-level folders
            $topLevelFolders = $this->fetchFoldersPage(
                $connection->access_token,
                'https://graph.microsoft.com/v1.0/me/mailFolders?$select=id,displayName,parentFolderId,totalItemCount,unreadItemCount,childFolderCount,isHidden&$top=100'
            );

            foreach ($topLevelFolders as $folderData) {
                $this->storeFolder($user, $folderData, $connection->id);
                $folderIds[] = $folderData['id'];
                $foldersCount++;

                // Recursively fetch child folders
                if (isset($folderData['childFolderCount']) && $folderData['childFolderCount'] > 0) {
                    $childResult = $this->fetchChildFolders($user, $connection->access_token, $folderData['id'], $connection->id);
                    $folderIds = array_merge($folderIds, $childResult['folder_ids']);
                    $foldersCount += $childResult['count'];
                }
            }

            Log::info('Folder sync completed', [
                'user_id' => $user->id,
                'folders_synced' => $foldersCount,
                'folder_ids_count' => count($folderIds),
            ]);

            return [
                'folders_synced' => $foldersCount,
                'folder_ids' => $folderIds,
            ];
        } catch (\Exception $e) {
            Log::error('Folder sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception("Failed to sync folders: {$e->getMessage()}");
        }
    }

    /**
     * Perform initial delta query to sync all messages
     *
     * @param  string|null  $filter  Optional OData filter (e.g., 'receivedDateTime ge 2025-10-30T00:00:00Z')
     *
     * @throws \Exception
     */
    public function initialSync(User $user, ?string $filter = null): array
    {
        try {
            Log::info('Starting initial email sync', ['user_id' => $user->id]);

            // Sync folders first
            $folderResult = $this->syncFolders($user);

            $connection = $user->office365Connection;
            if (! $connection || ! $connection->is_active) {
                throw new \Exception("No active Office365 connection found for user {$user->id}");
            }

            // Check token expiration
            if ($connection->isTokenExpired()) {
                Log::info('Access token expired, refreshing', ['user_id' => $user->id]);
                $this->office365Service->refreshAccessToken($connection);
                $connection->refresh();
            }

            $messagesSynced = 0;
            $pagesProcessed = 0;
            $deltaToken = null;

            // Start delta query
            $url = 'https://graph.microsoft.com/v1.0/me/messages?$top=100&$orderby=receivedDateTime desc';

            // Add filter if provided (e.g., last 7 days)
            if ($filter) {
                // Validate filter syntax before using
                if (! $this->isValidODataFilter($filter)) {
                    Log::warning('Invalid OData filter provided', [
                        'user_id' => $user->id,
                        'filter' => $filter,
                    ]);
                    throw new \InvalidArgumentException("Invalid OData filter syntax: {$filter}");
                }

                $url .= '&$filter='.rawurlencode($filter);
                Log::info('Applying filter to initial sync', [
                    'user_id' => $user->id,
                    'filter' => $filter,
                    'encoded' => rawurlencode($filter),
                ]);
            }

            while ($url) {
                $response = Http::withToken($connection->access_token)
                    ->timeout(30)
                    ->get($url);

                if ($response->status() === 401) {
                    Log::info('Token expired during sync, refreshing and retrying', ['user_id' => $user->id]);
                    $this->office365Service->refreshAccessToken($connection);
                    $connection->refresh();

                    $response = Http::withToken($connection->access_token)
                        ->timeout(30)
                        ->get($url);
                }

                if (! $response->successful()) {
                    throw new \Exception("Graph API request failed: {$response->status()} - {$response->body()}");
                }

                $data = $response->json();
                $pagesProcessed++;

                // Process messages
                $messages = $data['value'] ?? [];
                foreach ($messages as $messageData) {
                    $this->storeMessage($user, $messageData);
                    $messagesSynced++;
                }

                // Update progress in cache every 5 pages
                if ($pagesProcessed % 5 === 0) {
                    Cache::put("sync_progress_{$user->id}", [
                        'folders_synced' => $folderResult['folders_synced'] ?? 0,
                        'messages_synced' => $messagesSynced,
                        'current_page' => $pagesProcessed,
                        'last_updated' => now()->toIso8601String(),
                    ], 3600); // 1 hour TTL
                }

                // Log progress every 10 pages
                if ($pagesProcessed % 10 === 0) {
                    Log::info('Initial sync progress', [
                        'user_id' => $user->id,
                        'messages_synced' => $messagesSynced,
                        'pages_processed' => $pagesProcessed,
                    ]);
                }

                // Check for next link or delta link
                if (isset($data['@odata.nextLink'])) {
                    $url = $data['@odata.nextLink'];
                } elseif (isset($data['@odata.deltaLink'])) {
                    $deltaToken = $data['@odata.deltaLink'];
                    Log::info('Delta link found in response', [
                        'user_id' => $user->id,
                        'delta_token_preview' => substr($deltaToken, 0, 100),
                    ]);
                    $url = null; // End loop
                } else {
                    // Neither link present, end pagination
                    Log::warning('No next link or delta link in response', [
                        'user_id' => $user->id,
                        'page' => $pagesProcessed,
                        'response_keys' => array_keys($data),
                    ]);
                    $url = null;
                }
            }

            // Store delta token if found
            if ($deltaToken) {
                $result = $user->updateDeltaToken($deltaToken);
                Log::info('Delta token update attempted', [
                    'user_id' => $user->id,
                    'delta_token_length' => strlen($deltaToken),
                    'update_result' => $result,
                    'token_preview' => substr($deltaToken, 0, 100),
                ]);

                // Verify it was saved
                $user->refresh();
                if (!$user->hasDeltaToken()) {
                    Log::error('Delta token failed to persist', [
                        'user_id' => $user->id,
                        'attempted_length' => strlen($deltaToken),
                    ]);
                } else {
                    Log::info('Delta token successfully persisted', [
                        'user_id' => $user->id,
                        'persisted_length' => strlen($user->email_delta_token),
                    ]);
                }
            } else {
                Log::warning('No delta token received from initial sync', [
                    'user_id' => $user->id,
                    'pages_processed' => $pagesProcessed,
                    'filter_applied' => !empty($filter),
                ]);

                // If filter was applied and no delta token, make an empty request to establish delta
                if (!empty($filter)) {
                    Log::info('Making empty delta request to establish delta token', [
                        'user_id' => $user->id,
                    ]);

                    try {
                        $deltaUrl = 'https://graph.microsoft.com/v1.0/me/mailFolders/inbox/messages/delta?$select=id';
                        $deltaResponse = Http::withToken($connection->access_token)
                            ->timeout(30)
                            ->get($deltaUrl);

                        if ($deltaResponse->successful()) {
                            $deltaData = $deltaResponse->json();

                            // Skip to the delta link by following all nextLinks
                            while (isset($deltaData['@odata.nextLink'])) {
                                $response = Http::withToken($connection->access_token)
                                    ->timeout(30)
                                    ->get($deltaData['@odata.nextLink']);

                                if (!$response->successful()) {
                                    break;
                                }
                                $deltaData = $response->json();
                            }

                            if (isset($deltaData['@odata.deltaLink'])) {
                                $deltaToken = $deltaData['@odata.deltaLink'];
                                $result = $user->updateDeltaToken($deltaToken);

                                Log::info('Delta token established via empty request', [
                                    'user_id' => $user->id,
                                    'update_result' => $result,
                                    'token_length' => strlen($deltaToken),
                                ]);

                                $user->refresh();
                                if ($user->hasDeltaToken()) {
                                    Log::info('Delta token successfully persisted via fallback', [
                                        'user_id' => $user->id,
                                    ]);
                                }
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Failed to establish delta token via empty request', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            // Final check
            $user->refresh();
            Log::info('Initial email sync completed', [
                'user_id' => $user->id,
                'messages_synced' => $messagesSynced,
                'folders_synced' => $folderResult['folders_synced'],
                'pages_processed' => $pagesProcessed,
                'has_delta_token' => $user->hasDeltaToken(),
            ]);

            return [
                'messages_synced' => $messagesSynced,
                'folders_synced' => $folderResult['folders_synced'],
                'delta_token' => $deltaToken,
                'pages_processed' => $pagesProcessed,
            ];
        } catch (\Exception $e) {
            Log::error('Initial email sync failed', [
                'user_id' => $user->id,
                'messages_synced' => $messagesSynced ?? 0,
                'pages_processed' => $pagesProcessed ?? 0,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception("Failed to perform initial sync: {$e->getMessage()}");
        }
    }

    /**
     * Perform incremental sync using stored delta token
     *
     * @throws \Exception
     */
    public function processDeltaSync(User $user): array
    {
        try {
            Log::info('Starting delta sync', ['user_id' => $user->id]);

            if (! $user->hasDeltaToken()) {
                throw new \Exception("No delta token found for user {$user->id}. Must run initial sync first.");
            }

            $connection = $user->office365Connection;
            if (! $connection || ! $connection->is_active) {
                throw new \Exception("No active Office365 connection found for user {$user->id}");
            }

            // Check token expiration
            if ($connection->isTokenExpired()) {
                Log::info('Access token expired, refreshing', ['user_id' => $user->id]);
                $this->office365Service->refreshAccessToken($connection);
                $connection->refresh();
            }

            $messagesCreated = 0;
            $messagesUpdated = 0;
            $messagesDeleted = 0;
            $newDeltaToken = null;

            $url = $user->email_delta_token;

            while ($url) {
                $response = Http::withToken($connection->access_token)
                    ->timeout(30)
                    ->get($url);

                if ($response->status() === 401) {
                    Log::info('Token expired during delta sync, refreshing and retrying', ['user_id' => $user->id]);
                    $this->office365Service->refreshAccessToken($connection);
                    $connection->refresh();

                    $response = Http::withToken($connection->access_token)
                        ->timeout(30)
                        ->get($url);
                }

                if (! $response->successful()) {
                    throw new \Exception("Graph API delta request failed: {$response->status()} - {$response->body()}");
                }

                $data = $response->json();

                // Process changes
                $messages = $data['value'] ?? [];
                foreach ($messages as $messageData) {
                    // Check for deletion
                    if (isset($messageData['@removed'])) {
                        $deleted = Email::where('user_id', $user->id)
                            ->where('message_id', $messageData['id'])
                            ->delete();
                        if ($deleted) {
                            $messagesDeleted++;
                        }
                    } else {
                        // Update or create
                        $existing = Email::where('user_id', $user->id)
                            ->where('message_id', $messageData['id'])
                            ->exists();

                        $this->storeMessage($user, $messageData);

                        if ($existing) {
                            $messagesUpdated++;
                        } else {
                            $messagesCreated++;
                        }
                    }
                }

                // Check for next link or delta link
                if (isset($data['@odata.nextLink'])) {
                    $url = $data['@odata.nextLink'];
                } elseif (isset($data['@odata.deltaLink'])) {
                    $newDeltaToken = $data['@odata.deltaLink'];
                    $url = null;
                } else {
                    $url = null;
                }
            }

            // Update delta token
            if ($newDeltaToken) {
                $user->updateDeltaToken($newDeltaToken);
            }

            Log::info('Delta sync completed', [
                'user_id' => $user->id,
                'messages_created' => $messagesCreated,
                'messages_updated' => $messagesUpdated,
                'messages_deleted' => $messagesDeleted,
            ]);

            return [
                'messages_created' => $messagesCreated,
                'messages_updated' => $messagesUpdated,
                'messages_deleted' => $messagesDeleted,
                'delta_token' => $newDeltaToken,
            ];
        } catch (\Exception $e) {
            Log::error('Delta sync failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw new \Exception("Failed to perform delta sync: {$e->getMessage()}");
        }
    }

    /**
     * Fetch and store a single message by ID
     */
    /**
     * Sync a single message from Microsoft Graph
     *
     * @param User $user
     * @param string $messageId
     * @param bool $isWebhookSync Whether this is triggered by a webhook notification
     * @return Email|null
     * @throws \Exception
     */
    public function syncSingleMessage(User $user, string $messageId, bool $isWebhookSync = false): ?Email
    {
        try {
            Log::info('Syncing single message', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'is_webhook_sync' => $isWebhookSync,
            ]);

            $connection = $user->office365Connection;
            if (! $connection || ! $connection->is_active) {
                throw new \Exception("No active Office365 connection found for user {$user->id}");
            }

            // Check token expiration
            if ($connection->isTokenExpired()) {
                Log::info('Access token expired, refreshing', ['user_id' => $user->id]);
                $this->office365Service->refreshAccessToken($connection);
                $connection->refresh();
            }

            $url = "https://graph.microsoft.com/v1.0/me/messages/{$messageId}?";
            $url .= '$select=id,subject,body,bodyPreview,from,toRecipients,ccRecipients,bccRecipients,replyTo,sender,receivedDateTime,sentDateTime,hasAttachments,isRead,isDraft,importance,flag,categories,conversationId,internetMessageId,webLink,parentFolderId';
            $url .= '&$expand=attachments($select=id,name,contentType,size,isInline,contentId,contentLocation,lastModifiedDateTime)';

            $response = Http::withToken($connection->access_token)
                ->timeout(30)
                ->get($url);

            if ($response->status() === 404) {
                Log::warning('Message not found', ['user_id' => $user->id, 'message_id' => $messageId]);

                return null;
            }

            if (! $response->successful()) {
                throw new \Exception("Graph API request failed: {$response->status()} - {$response->body()}");
            }

            $messageData = $response->json();
            // Pass webhook flag to storeMessage so it knows whether to dispatch events
            $email = $this->storeMessage($user, $messageData, $isWebhookSync);

            Log::info('Single message synced', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'subject' => $messageData['subject'] ?? 'N/A',
                'is_webhook_sync' => $isWebhookSync,
            ]);

            return $email;
        } catch (\Exception $e) {
            Log::error('Failed to sync single message', [
                'user_id' => $user->id,
                'message_id' => $messageId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Parse Graph API message data and store/update in database
     *
     * @param User $user
     * @param array $messageData
     * @param bool $isWebhookSync Whether this email is being synced from a webhook notification (triggers rules)
     * @return Email
     */
    private function storeMessage(User $user, array $messageData, bool $isWebhookSync = false): Email
    {
        // Find folder by Graph folder ID
        $folder = null;
        if (isset($messageData['parentFolderId'])) {
            $folder = EmailFolder::where('user_id', $user->id)
                ->where('folder_id', $messageData['parentFolderId'])
                ->first();
        }

        // Parse recipients
        $toRecipients = $this->parseRecipients($messageData['toRecipients'] ?? []);
        $ccRecipients = $this->parseRecipients($messageData['ccRecipients'] ?? []);
        $bccRecipients = $this->parseRecipients($messageData['bccRecipients'] ?? []);
        $replyTo = $this->parseRecipients($messageData['replyTo'] ?? []);

        // Parse from and sender
        // Parse from and sender (with length limits for database)
        $fromName = isset($messageData['from']['emailAddress']['name'])
            ? mb_substr($messageData['from']['emailAddress']['name'], 0, 1000)
            : null;
        $fromEmail = isset($messageData['from']['emailAddress']['address'])
            ? mb_substr($messageData['from']['emailAddress']['address'], 0, 250)
            : null;
        $senderName = isset($messageData['sender']['emailAddress']['name'])
            ? mb_substr($messageData['sender']['emailAddress']['name'], 0, 1000)
            : null;
        $senderEmail = isset($messageData['sender']['emailAddress']['address'])
            ? mb_substr($messageData['sender']['emailAddress']['address'], 0, 250)
            : null;

        // Parse body
        $bodyContentType = isset($messageData['body']['contentType'])
            ? strtolower($messageData['body']['contentType'])
            : 'text';
        $bodyContent = $messageData['body']['content'] ?? null;

        // Parse dates
        $receivedDateTime = isset($messageData['receivedDateTime'])
            ? Carbon::parse($messageData['receivedDateTime'])
            : null;
        $sentDateTime = isset($messageData['sentDateTime'])
            ? Carbon::parse($messageData['sentDateTime'])
            : null;

        // Parse flag
        $flagStatus = isset($messageData['flag']['flagStatus'])
            ? $messageData['flag']['flagStatus']
            : 'notFlagged';

        // Create/update email
        $email = Email::updateOrCreate(
            [
                'user_id' => $user->id,
                'message_id' => $messageData['id'],
            ],
            [
                'internet_message_id' => $messageData['internetMessageId'] ?? null,
                'conversation_id' => $messageData['conversationId'] ?? null,
                'subject' => $messageData['subject'] ?? null,
                'body_preview' => $messageData['bodyPreview'] ?? null,
                'body_content' => $bodyContent,
                'body_content_type' => $bodyContentType,
                'from_name' => $fromName,
                'from_email' => $fromEmail,
                'to_recipients' => $toRecipients,
                'cc_recipients' => $ccRecipients,
                'bcc_recipients' => $bccRecipients,
                'reply_to' => $replyTo,
                'sender_name' => $senderName,
                'sender_email' => $senderEmail,
                'received_date_time' => $receivedDateTime,
                'sent_date_time' => $sentDateTime,
                'has_attachments' => $messageData['hasAttachments'] ?? false,
                'is_read' => $messageData['isRead'] ?? false,
                'is_draft' => $messageData['isDraft'] ?? false,
                'importance' => $messageData['importance'] ?? 'normal',
                'flag_status' => $flagStatus,
                'categories' => $messageData['categories'] ?? [],
                'web_link' => $messageData['webLink'] ?? null,
                'email_folder_id' => $folder?->id,
                'office365_connection_id' => $user->office365Connection->id,
            ]
        );

        // Capture whether this was a new email or an update
        $wasRecentlyCreated = $email->wasRecentlyCreated;
        $changes = $email->getChanges();

        // Store attachments if present
        if (isset($messageData['hasAttachments']) && $messageData['hasAttachments'] === true) {
            if (isset($messageData['attachments'])) {
                $this->storeAttachments($email, $messageData['attachments']);
            }
        }

        // Dispatch events for email rules processing
        // IMPORTANT: Only dispatch events for webhook syncs (new incoming emails)
        // Do NOT dispatch for bulk sync operations (initial sync, delta sync, manual sync)
        if ($isWebhookSync) {
            if ($wasRecentlyCreated) {
                Log::debug('Dispatching EmailCreated event for webhook sync', [
                    'email_id' => $email->id,
                    'user_id' => $user->id,
                    'subject' => $email->subject,
                ]);
                EmailCreated::dispatch($email);
            } elseif (! empty($changes)) {
                Log::debug('Dispatching EmailUpdated event for webhook sync', [
                    'email_id' => $email->id,
                    'user_id' => $user->id,
                    'changes' => array_keys($changes),
                ]);
                EmailUpdated::dispatch($email, $changes);
            }
        } else {
            Log::debug('Skipping event dispatch for non-webhook sync', [
                'email_id' => $email->id,
                'user_id' => $user->id,
                'was_created' => $wasRecentlyCreated,
                'has_changes' => ! empty($changes),
            ]);
        }

        return $email;
    }

    /**
     * Store attachment metadata (not content) for an email
     */
    private function storeAttachments(Email $email, array $attachmentsData): void
    {
        foreach ($attachmentsData as $attachmentData) {
            EmailAttachment::updateOrCreate(
                [
                    'email_id' => $email->id,
                    'attachment_id' => $attachmentData['id'],
                ],
                [
                    'name' => $attachmentData['name'] ?? null,
                    'content_type' => $attachmentData['contentType'] ?? null,
                    'size' => $attachmentData['size'] ?? null,
                    'is_inline' => $attachmentData['isInline'] ?? false,
                    'content_id' => $attachmentData['contentId'] ?? null,
                    'content_location' => $attachmentData['contentLocation'] ?? null,
                    'last_modified_date_time' => isset($attachmentData['lastModifiedDateTime'])
                        ? Carbon::parse($attachmentData['lastModifiedDateTime'])
                        : null,
                ]
            );
        }

        Log::info('Attachments stored', [
            'email_id' => $email->id,
            'attachment_count' => count($attachmentsData),
        ]);
    }

    /**
     * Parse recipients array from Graph API format
     */
    private function parseRecipients(array $recipients): array
    {
        return array_map(function ($recipient) {
            return [
                'name' => $recipient['emailAddress']['name'] ?? null,
                'email' => $recipient['emailAddress']['address'] ?? null,
            ];
        }, $recipients);
    }

    /**
     * Validate OData filter syntax for Microsoft Graph API
     */
    private function isValidODataFilter(string $filter): bool
    {
        // Check for basic OData filter pattern
        // Allowed: receivedDateTime/sentDateTime/createdDateTime ge/le/eq/ne/gt/lt ISO8601_DATE
        $pattern = '/^(receivedDateTime|sentDateTime|createdDateTime)\s+(ge|le|eq|ne|gt|lt)\s+\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2}|Z)$/';

        return preg_match($pattern, $filter) === 1;
    }

    /**
     * Fetch child folders recursively
     */
    private function fetchChildFolders(User $user, string $accessToken, string $parentFolderId, int $connectionId): array
    {
        $folderIds = [];
        $count = 0;

        $url = "https://graph.microsoft.com/v1.0/me/mailFolders/{$parentFolderId}/childFolders?";
        $url .= '$select=id,displayName,parentFolderId,totalItemCount,unreadItemCount,childFolderCount,isHidden&$top=100';

        $folders = $this->fetchFoldersPage($accessToken, $url);

        foreach ($folders as $folderData) {
            $this->storeFolder($user, $folderData, $connectionId);
            $folderIds[] = $folderData['id'];
            $count++;

            // Recursively fetch child folders
            if (isset($folderData['childFolderCount']) && $folderData['childFolderCount'] > 0) {
                $childResult = $this->fetchChildFolders($user, $accessToken, $folderData['id'], $connectionId);
                $folderIds = array_merge($folderIds, $childResult['folder_ids']);
                $count += $childResult['count'];
            }
        }

        return [
            'folder_ids' => $folderIds,
            'count' => $count,
        ];
    }

    /**
     * Fetch a page of folders with pagination support
     */
    private function fetchFoldersPage(string $accessToken, string $url): array
    {
        $allFolders = [];

        while ($url) {
            $response = Http::withToken($accessToken)
                ->timeout(30)
                ->get($url);

            if (! $response->successful()) {
                Log::error('Failed to fetch folders page', [
                    'status' => $response->status(),
                    'url' => $url,
                    'body' => $response->body(),
                ]);
                throw new \Exception("Failed to fetch folders: {$response->status()}");
            }

            $data = $response->json();
            $folders = $data['value'] ?? [];
            $allFolders = array_merge($allFolders, $folders);

            // Check for next page
            $url = $data['@odata.nextLink'] ?? null;
        }

        return $allFolders;
    }

    /**
     * Store folder in database
     */
    private function storeFolder(User $user, array $folderData, int $connectionId): EmailFolder
    {
        return EmailFolder::updateOrCreate(
            [
                'user_id' => $user->id,
                'folder_id' => $folderData['id'],
            ],
            [
                'parent_folder_id' => $folderData['parentFolderId'] ?? null,
                'office365_connection_id' => $connectionId,
                'display_name' => $folderData['displayName'] ?? 'Unknown',
                'total_item_count' => $folderData['totalItemCount'] ?? 0,
                'unread_item_count' => $folderData['unreadItemCount'] ?? 0,
                'child_folder_count' => $folderData['childFolderCount'] ?? 0,
                'is_hidden' => $folderData['isHidden'] ?? false,
            ]
        );
    }
}
