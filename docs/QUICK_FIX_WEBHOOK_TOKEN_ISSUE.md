# QUICK FIX: Webhook Token Expiration

**Priority:** CRITICAL  
**Time to Fix:** 2 minutes (critical path only)

## Problem
Webhooks fail with: `401 - InvalidAuthenticationToken - token is expired`

## Minimum Viable Fix

If you need webhooks working IMMEDIATELY, apply only this one change:

### File: `app/Services/EmailSyncService.php`

**Find lines 464-474:**
```php
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
```

**Replace with:**
```php
$connection = $user->office365Connection;
if (! $connection || ! $connection->is_active) {
    throw new \Exception("No active Office365 connection found for user {$user->id}");
}

// Check token expiration with 5-minute buffer
if (! $connection->token_expires_at || 
    $connection->token_expires_at->isPast() || 
    $connection->token_expires_at->diffInMinutes(now(), false) <= 5) {
    
    Log::info('Access token expired, refreshing', ['user_id' => $user->id]);
    $tokenData = $this->office365Service->refreshAccessToken($connection);
    $connection->update([
        'access_token' => $tokenData['access_token'],
        'refresh_token' => $tokenData['refresh_token'],
        'token_expires_at' => $tokenData['expires_at'],
    ]);
    
    // CRITICAL: Fetch fresh connection to bypass encrypted attribute cache
    $connection = $user->office365Connection()->firstOrFail();
}
```

## Test
```bash
php -l app/Services/EmailSyncService.php
```

## Complete Fix
For the full, proper solution see:
- `docs/OAUTH_TOKEN_FIX_IMPLEMENTATION_GUIDE.md` - Step-by-step instructions
- `docs/OAUTH_TOKEN_FIX_2025-11-12.md` - Complete analysis and solution

The complete fix adds a helper method and updates 4 methods. This quick fix gets webhooks working immediately.
