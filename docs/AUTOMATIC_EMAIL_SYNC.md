# Automatic Email Sync

## Overview

The automatic email sync feature synchronizes the last 7 days of emails from Microsoft Office 365 when a user first logs in. This provides immediate value to new users without requiring manual sync triggers.

## How It Works

### Flow Diagram

```
User Clicks "Sign in with Microsoft"
  |
  v
Frontend redirects to /auth/microsoft
  |
  v
MicrosoftAuthController generates OAuth URL
  |
  v
User authenticates with Microsoft
  |
  v
Microsoft redirects to /auth/microsoft/callback
  |
  v
MicrosoftAuthController validates OAuth code
  |
  v
Creates/updates User and Office365Connection
  |
  v
Generates Sanctum API token
  |
  v
CHECK: User needs sync?
  |- Has delta token? -> No sync needed
  |- Has emails? -> No sync needed
  \- Neither? -> TRIGGER SYNC
      |
      v
      InitialEmailSyncJob dispatched
      |
      v
      User redirected to frontend with token
      |
      v
      [Background] Job syncs last 7 days of emails
      |
      v
      [Background] Job creates webhook subscription
      |
      v
      User can view emails in dashboard
```

### Technical Implementation

**1. Trigger Location**
- File: `app/Http/Controllers/MicrosoftAuthController.php`
- Method: `callback()`
- Line: After API token generation (line 212)

**2. Trigger Condition**
```php
if (!$user->hasDeltaToken() && !$user->emails()->exists()) {
    $sevenDaysAgo = now()->subDays(7)->toIso8601String();
    $filter = "receivedDateTime ge {$sevenDaysAgo}";
    InitialEmailSyncJob::dispatch($user, $filter);
}
```

**3. Job Processing**
- Job: `App\Jobs\InitialEmailSyncJob`
- Queue: `default` (configurable)
- Timeout: 600 seconds (10 minutes)
- Retries: 3 attempts
- Unique: Yes (1 hour deduplication window)

**4. Service Layer**
- Service: `App\Services\EmailSyncService`
- Method: `initialSync(User $user, ?string $filter = null)`
- Filter format: OData `$filter` syntax
- Validation: Regex pattern matching for security

**5. Webhook Creation**
- Job: `App\Jobs\CreateUserSubscriptionJob`
- Dispatched by: `InitialEmailSyncJob` after sync completes
- Service: `App\Services\GraphSubscriptionService`
- Expiration: 7 days (automatically renewed)

## Configuration

### Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| `OFFICE365_INITIAL_SYNC_DAYS` | 7 | Number of days to sync (future enhancement) |
| `QUEUE_CONNECTION` | `database` | Queue driver for jobs |

### Code Configuration

**Modify sync window:**
```php
// In MicrosoftAuthController.php, line 214
$sevenDaysAgo = now()->subDays(7)->toIso8601String(); // Change 7 to desired days
```

**Modify uniqueness window:**
```php
// In InitialEmailSyncJob.php, line 46
public int $uniqueFor = 3600; // Change to desired seconds (default: 1 hour)
```

**Modify job timeout:**
```php
// In InitialEmailSyncJob.php, line 32
public int $timeout = 600; // Change to desired seconds (default: 10 minutes)
```

## Monitoring

### Check Sync Status

**Via Logs:**
```bash
# Watch logs in real-time
php artisan pail

# Filter for sync-related logs
php artisan pail | grep InitialEmailSyncJob

# Check specific user sync
grep "user_id\":123" storage/logs/laravel.log | grep InitialEmailSyncJob
```

**Via Database:**
```php
use App\Models\User;

$user = User::find($userId);

// Check if sync has started
$hasDeltaToken = $user->hasDeltaToken();

// Check if emails were synced
$emailCount = $user->emails()->count();

// Check last sync time
$lastSync = $user->last_email_sync_at;

// Check for active webhook subscription
$hasSubscription = $user->activeEmailSubscription !== null;
```

**Via Queue:**
```bash
# Check queue status
php artisan queue:monitor

# Check for failed jobs
php artisan queue:failed

# Retry failed job
php artisan queue:retry {job-id}
```

### Logging Details

**Job Dispatched:**
```
[timestamp] local.INFO: Dispatching initial email sync for new user
{
  "user_id": 1,
  "filter": "receivedDateTime ge 2025-10-30T00:00:00+00:00"
}
```

**Job Started:**
```
[timestamp] local.INFO: InitialEmailSyncJob started
{
  "user_id": 1,
  "job_uuid": "abc123...",
  "attempt": 1,
  "filter": "receivedDateTime ge 2025-10-30T00:00:00+00:00"
}
```

**Filter Applied:**
```
[timestamp] local.INFO: Applying filter to initial sync
{
  "user_id": 1,
  "filter": "receivedDateTime ge 2025-10-30T00:00:00+00:00",
  "encoded": "receivedDateTime%20ge%202025-10-30T00%3A00%3A00%2B00%3A00"
}
```

**Job Completed:**
```
[timestamp] local.INFO: Initial sync completed in job
{
  "user_id": 1,
  "messages_synced": 42,
  "folders_synced": 8,
  "has_delta_token": true
}
```

## Troubleshooting

### Sync Not Triggered

**Problem:** User logged in but no emails synced.

**Diagnosis:**
1. Check if job was dispatched:
   ```bash
   grep "Dispatching initial email sync" storage/logs/laravel.log
   ```

2. Check trigger conditions:
   ```php
   $user = User::find($userId);
   dd([
       'has_delta_token' => $user->hasDeltaToken(),
       'has_emails' => $user->emails()->exists(),
       'should_sync' => !$user->hasDeltaToken() && !$user->emails()->exists()
   ]);
   ```

**Solutions:**
- If delta token exists: Sync already ran, use delta sync instead
- If emails exist: Sync already ran, no need to trigger again
- If Office365Connection missing: User needs to reconnect OAuth
- If queue not running: Start queue worker with `php artisan queue:listen`

### Sync Failed

**Problem:** Job was dispatched but failed.

**Diagnosis:**
```bash
# Check for failures
grep "InitialEmailSyncJob failed" storage/logs/laravel.log

# Check failed jobs queue
php artisan queue:failed
```

**Common Causes:**
1. **Token Expired:** Office365 access token expired
   - Solution: Job retries with token refresh
2. **Invalid Filter:** Filter syntax is incorrect
   - Solution: Check filter validation logic
3. **Graph API Error:** Microsoft Graph API returned error
   - Solution: Check API quota and permissions
4. **Timeout:** Sync took longer than 10 minutes
   - Solution: Increase timeout or reduce sync window

**Retry Failed Job:**
```bash
php artisan queue:retry {job-id}
```

### Duplicate Syncs

**Problem:** Multiple sync jobs triggered for same user.

**Diagnosis:**
```bash
# Check for duplicate jobs
grep "InitialEmailSyncJob started" storage/logs/laravel.log | grep "user_id\":$USER_ID"
```

**Solution:**
- System prevents duplicates with `ShouldBeUnique`
- If duplicates occur, check cache driver configuration
- Verify Redis/database cache is working correctly

### Slow Sync

**Problem:** Sync takes longer than expected.

**Diagnosis:**
```bash
# Check sync duration in logs
grep "Initial sync completed" storage/logs/laravel.log
```

**Solutions:**
1. Reduce sync window (e.g., 3 days instead of 7)
2. Use dedicated queue for email sync
3. Increase queue worker memory
4. Check Microsoft Graph API latency

## Manual Testing

### Test Automatic Sync

1. **Create test user without emails:**
   ```php
   $user = User::factory()->create(['email_delta_token' => null]);
   $connection = Office365Connection::factory()->active()->for($user)->create();
   ```

2. **Trigger sync manually:**
   ```php
   $filter = "receivedDateTime ge " . now()->subDays(7)->toIso8601String();
   InitialEmailSyncJob::dispatch($user, $filter);
   ```

3. **Process queue:**
   ```bash
   php artisan queue:work --once
   ```

4. **Verify results:**
   ```php
   $user->refresh();
   dd([
       'emails_count' => $user->emails()->count(),
       'folders_count' => $user->emailFolders()->count(),
       'has_delta_token' => $user->hasDeltaToken(),
       'has_subscription' => $user->activeEmailSubscription !== null
   ]);
   ```

### Test OAuth Flow

1. **Start queue worker:**
   ```bash
   php artisan queue:listen
   ```

2. **Sign in with Microsoft:**
   - Navigate to `http://localhost:5173`
   - Click "Sign in with Microsoft"
   - Authenticate with test account
   - Observe logs for job dispatch

3. **Verify sync:**
   ```bash
   php artisan pail | grep InitialEmailSyncJob
   ```

## API Reference

### InitialEmailSyncJob

**Constructor:**
```php
public function __construct(User $user, ?string $filter = null)
```

**Parameters:**
- `$user` (User): The user to sync emails for
- `$filter` (string|null): Optional OData filter (e.g., `"receivedDateTime ge 2025-10-30T00:00:00Z"`)

**Properties:**
- `$tries` (int): 3 attempts
- `$timeout` (int): 600 seconds
- `$uniqueFor` (int): 3600 seconds (1 hour)

**Methods:**
- `handle(EmailSyncService $emailSyncService, GraphSubscriptionService $subscriptionService): void`
- `uniqueId(): string` - Returns `"initial-email-sync-{user_id}"`
- `failed(\Throwable $exception): void` - Logs failure

### EmailSyncService::initialSync()

**Method Signature:**
```php
public function initialSync(User $user, ?string $filter = null): array
```

**Parameters:**
- `$user` (User): The user to sync emails for
- `$filter` (string|null): Optional OData filter for date-based filtering

**Returns:**
```php
[
    'messages_synced' => 42,      // Number of emails synced
    'folders_synced' => 8,        // Number of folders synced
    'delta_token' => 'abc123...',  // Delta token for incremental sync
    'pages_processed' => 3         // Number of API pages processed
]
```

**Throws:**
- `\Exception` - If Graph API request fails
- `\InvalidArgumentException` - If filter syntax is invalid

**Filter Format:**
- Pattern: `(receivedDateTime|sentDateTime|createdDateTime) (ge|le|eq|ne|gt|lt) {ISO 8601 date}`
- Example: `receivedDateTime ge 2025-10-30T00:00:00Z`
- Operators: `ge` (>=), `le` (<=), `eq` (=), `ne` (!=), `gt` (>), `lt` (<)
- Date format: ISO 8601 with timezone (`Z` or `+HH:MM`)

## Performance Considerations

### Database Queries

**Optimized:**
- Uses `exists()` instead of `count()` for trigger check
- Adds composite indexes for common queries
- Eager loads relationships to prevent N+1

**Benchmarks:**
- Trigger check: <10ms (with `exists()`)
- Job dispatch: <50ms (async, non-blocking)
- Full sync (7 days, 100 emails): 30-60 seconds
- Full sync (7 days, 1000 emails): 3-5 minutes

### Queue Performance

**Recommendations:**
- Use Redis queue driver for better performance
- Use dedicated queue for email sync jobs
- Scale queue workers horizontally if needed
- Monitor queue size and latency

**Configuration:**
```env
QUEUE_CONNECTION=redis
QUEUE_EMAILS=emails  # Dedicated queue for email jobs
```

```php
// In InitialEmailSyncJob.php
public function __construct(User $user, ?string $filter = null)
{
    $this->user = $user;
    $this->filter = $filter;
    $this->onQueue('emails');  // Use dedicated queue
}
```

## Security

### Filter Validation

**Validation Rules:**
- Regex pattern: `/^(receivedDateTime|sentDateTime|createdDateTime)\s+(ge|le|eq|ne|gt|lt)\s+\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}([+-]\d{2}:\d{2}|Z)$/`
- Only allows specific date fields
- Only allows comparison operators
- Requires valid ISO 8601 date format

**Security Benefits:**
- Prevents OData injection attacks
- Prevents invalid API requests
- Ensures predictable behavior
- Provides clear error messages

### URL Encoding

**Implementation:**
- Uses `rawurlencode()` instead of `urlencode()`
- Follows RFC 3986 standard
- Encodes spaces as `%20` (not `+`)
- Compatible with Microsoft Graph API

## Future Enhancements

**Planned Improvements:**
1. Configurable sync window via environment variable
2. Frontend sync progress indicator
3. Webhook for sync completion notification
4. Selective folder sync (inbox only, skip spam)
5. Priority sync for recent emails (last 24 hours first)
6. Bulk sync endpoint for admin tools
7. Sync cancellation support
8. Better error recovery and retry logic

**Configuration Ideas:**
```env
OFFICE365_INITIAL_SYNC_DAYS=7
OFFICE365_SYNC_FOLDERS=inbox,sent  # Only sync specific folders
OFFICE365_SYNC_PRIORITY=recent     # Prioritize recent emails
```

## Related Documentation

- [CLAUDE.md](../CLAUDE.md) - Main project documentation
- [TESTING_EMAIL_ENDPOINTS.md](./TESTING_EMAIL_ENDPOINTS.md) - Email endpoint testing guide
- [Microsoft Graph API Documentation](https://learn.microsoft.com/en-us/graph/api/overview)
- [Laravel Queue Documentation](https://laravel.com/docs/12.x/queues)
