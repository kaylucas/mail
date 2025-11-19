# OAuth Token Expiration Fix
**Date:** 2025-11-12  
**Issue:** Webhook notifications failing with 401 "InvalidAuthenticationToken - token is expired"  
**Status:** Solution Ready  
**Priority:** Critical

## Problem Statement

Webhook notifications from Microsoft Graph fail when processing emails because OAuth access tokens expire before the API request is made:

```
Graph API request failed: 401 - {"error":{"code":"InvalidAuthenticationToken","message":"Lifetime validation failed, the token is expired."}}
```

**Error Location:**
- `/app/Services/EmailSyncService.php:491` - Graph API request
- Called from `/app/Jobs/ProcessWebhookNotificationJob.php:112` - webhook processing

## Root Cause

### The Bug
Token refresh logic exists but fails due to Laravel's encrypted attribute caching:

```php
// Current broken code (line 470-474 in EmailSyncService.php)
if ($connection->isTokenExpired()) {
    Log::info('Access token expired, refreshing', ['user_id' => $user->id]);
    $this->office365Service->refreshAccessToken($connection);  // ✅ Updates database
    $connection->refresh();  // ❌ Doesn't properly reload encrypted attribute
}
// Line 480
Http::withToken($connection->access_token)  // ❌ Uses OLD cached token value
```

**Why It Fails:**
1. `refreshAccessToken()` updates `access_token` in the database ✅
2. `$connection->refresh()` attempts to reload from database ✅
3. Laravel's encrypted casting caches the decrypted value ❌
4. `refresh()` reloads but doesn't re-decrypt in same request context ❌
5. `$connection->access_token` still contains the EXPIRED token ❌

### Impact
- All webhook-triggered email syncing fails when tokens expire
- Affects `syncSingleMessage()` (webhooks), `syncFolders()`, `initialSync()`, `processDeltaSync()`
- No error recovery - webhooks fail permanently for expired tokens

## Solution

### Architecture
1. **Add centralized helper method** `ensureFreshAccessToken(User $user)`
   - Checks token expiration with 5-minute buffer
   - Refreshes token if needed
   - **Fetches fresh connection from DB** (bypasses encrypted attribute cache)
   - Returns connection with guaranteed valid access token

2. **Replace all manual refresh logic** with single helper call
   - Eliminates code duplication (4 methods × 10 lines = 40 lines removed)
   - Consistent behavior across all sync operations
   - Proper logging of token refresh events

3. **Fix encrypted attribute caching issue**
   ```php
   // NEW correct approach
   $connection = $user->office365Connection()->firstOrFail();  // Fresh from DB
   ```

### Code Changes

**File:** `app/Services/EmailSyncService.php`

#### Change 1: Add Helper Method (after line 23)
```php
private function ensureFreshAccessToken(User $user): \App\Models\Office365Connection
{
    $connection = $user->office365Connection;
    if (! $connection || ! $connection->is_active) {
        throw new \Exception("No active Office365 connection found for user {$user->id}");
    }

    // Check if token is expired or about to expire (5-minute buffer)
    $expiresAt = $connection->token_expires_at;
    $isExpiredOrExpiringSoon = ! $expiresAt || $expiresAt->isPast() 
        || $expiresAt->diffInMinutes(now(), false) <= 5;

    if ($isExpiredOrExpiringSoon) {
        Log::info('Access token expired or expiring soon, refreshing', [
            'user_id' => $user->id,
            'expires_at' => $expiresAt?->toIso8601String(),
            'is_expired' => ! $expiresAt || $expiresAt->isPast(),
        ]);

        // Refresh the token
        $tokenData = $this->office365Service->refreshAccessToken($connection);
        $connection->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'],
            'token_expires_at' => $tokenData['expires_at'],
        ]);

        Log::info('Access token refreshed successfully', [
            'user_id' => $user->id,
            'new_expires_at' => $tokenData['expires_at']->toIso8601String(),
        ]);

        // CRITICAL: Fetch fresh connection to bypass encrypted attribute cache
        $connection = $user->office365Connection()->firstOrFail();
    }

    return $connection;
}
```

#### Change 2-5: Replace Manual Refresh Logic
Replace all occurrences of:
```php
$connection = $user->office365Connection;
if (! $connection || ! $connection->is_active) {
    throw new \Exception("No active Office365 connection found for user {$user->id}");
}

if ($connection->isTokenExpired()) {
    Log::info('Access token expired, refreshing', ['user_id' => $user->id]);
    $this->office365Service->refreshAccessToken($connection);
    $connection->refresh();
}
```

With:
```php
// Ensure we have a fresh, valid access token
$connection = $this->ensureFreshAccessToken($user);
```

**Methods to Update:**
- `syncSingleMessage()` - lines 464-474 (CRITICAL - used by webhooks)
- `syncFolders()` - lines 35-45
- `initialSync()` - lines 104-114 and 147-150 (retry logic)
- `processDeltaSync()` - lines 337-347 and 361-364 (retry logic)

### Implementation Guide

See `/tmp/COMPLETE_FIX_GUIDE.md` for step-by-step instructions.

## Testing

### 1. Syntax Check
```bash
php -l app/Services/EmailSyncService.php
```

### 2. Unit Test
```php
// In artisan tinker
$user = User::find(1);
$service = app(App\Services\EmailSyncService::class);

// Force token expiration
$user->office365Connection->update(['token_expires_at' => now()->subHour()]);

// This should auto-refresh the token
$service->syncSingleMessage($user, 'test-message-id', true);

// Check logs for "Access token refreshed successfully"
```

### 3. Integration Test
1. Wait for webhook notification to arrive after token expires (~ 1 hour)
2. Monitor logs: `tail -f storage/logs/laravel.log | grep "token"`
3. Verify no 401 errors occur
4. Verify email syncs successfully

### Expected Log Output
```
[2025-11-12 ...] Access token expired or expiring soon, refreshing {"user_id":1,"expires_at":"2025-11-12T10:00:00Z","is_expired":true}
[2025-11-12 ...] Access token refreshed successfully {"user_id":1,"new_expires_at":"2025-11-12T11:00:00Z"}
[2025-11-12 ...] Syncing single message {"user_id":1,"message_id":"AAMkAD...","is_webhook_sync":true}
[2025-11-12 ...] Single message synced {"user_id":1,"subject":"Test Email"}
```

## Rollback

Backup created at: `app/Services/EmailSyncService.php.backup`

To rollback:
```bash
cp app/Services/EmailSyncService.php.backup app/Services/EmailSyncService.php
```

## Long-Term Recommendations

### 1. Proactive Token Refresh (High Priority)
Create scheduled command to refresh tokens before expiration:

```php
// app/Console/Commands/RefreshExpiring TokensCommand.php
Office365Connection::where('is_active', true)
    ->where('token_expires_at', '<=', now()->addMinutes(10))
    ->get()
    ->each(function ($connection) {
        app(Office365Service::class)->refreshAccessToken($connection);
    });
```

Schedule hourly:
```php
// app/Console/Kernel.php
$schedule->command('oauth:refresh-tokens')->hourly();
```

### 2. Add Monitoring
- Track token refresh frequency per user
- Alert on repeated refresh failures (> 3)
- Monitor 401 error rates

### 3. Improve Token Expiry Handling
- Increase buffer time to 10-15 minutes for long-running operations
- Add retry logic for all Graph API calls (not just sync operations)
- Consider implementing circuit breaker pattern

## Impact

- **Criticality:** High - Fixes all webhook processing
- **Affected Users:** All users with expired tokens
- **Recovery:** Immediate - next webhook will succeed
- **Code Quality:** Improved (40 lines removed, better maintainability)

## References

- Microsoft Graph API Docs: https://learn.microsoft.com/en-us/graph/auth-v2-service
- Laravel Encrypted Casting: https://laravel.com/docs/12.x/eloquent-mutators#encrypted-casting
- OAuth 2.0 Token Refresh: https://oauth.net/2/grant-types/refresh-token/

---

**Author:** Claude Code (claude-sonnet-4-5)  
**Review Status:** Ready for implementation  
**Estimated Fix Time:** 10 minutes
