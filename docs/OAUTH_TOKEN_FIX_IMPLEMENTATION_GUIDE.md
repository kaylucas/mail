# OAuth Token Fix - Manual Application Guide

## Quick Reference
- **File to Edit:** `app/Services/EmailSyncService.php`
- **Backup Created:** `app/Services/EmailSyncService.php.backup`
- **Total Changes:** 5 edits
- **Est. Time:** 10 minutes

---

## EDIT 1: Add Helper Method (after line 23)

**Location:** After the constructor closing brace, before `syncFolders()` method

**Insert this code after line 23:**

```php

    /**
     * Ensure the Office365 connection has a valid, non-expired access token.
     *
     * This method checks if the token is expired or about to expire (within 5 minutes),
     * and refreshes it if necessary. It properly handles Laravel's encrypted attribute
     * caching by fetching a fresh connection instance after the refresh.
     *
     * @param  User  $user  The user whose connection to refresh
     * @return \App\Models\Office365Connection Fresh connection with valid access token
     *
     * @throws \Exception If no active connection found or refresh fails
     */
    private function ensureFreshAccessToken(User $user): \App\Models\Office365Connection
    {
        $connection = $user->office365Connection;
        if (! $connection || ! $connection->is_active) {
            throw new \Exception("No active Office365 connection found for user {$user->id}");
        }

        // Check if token is expired or about to expire (5-minute buffer)
        $expiresAt = $connection->token_expires_at;
        $isExpiredOrExpiringSoon = ! $expiresAt || $expiresAt->isPast() || $expiresAt->diffInMinutes(now(), false) <= 5;

        if ($isExpiredOrExpiringSoon) {
            Log::info('Access token expired or expiring soon, refreshing', [
                'user_id' => $user->id,
                'connection_id' => $connection->id,
                'expires_at' => $expiresAt?->toIso8601String(),
                'is_expired' => ! $expiresAt || $expiresAt->isPast(),
                'minutes_until_expiry' => $expiresAt?->diffInMinutes(now(), false),
            ]);

            // Refresh the token
            $tokenData = $this->office365Service->refreshAccessToken($connection);

            // Update the connection with new tokens
            $connection->update([
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'],
                'token_expires_at' => $tokenData['expires_at'],
            ]);

            Log::info('Access token refreshed successfully', [
                'user_id' => $user->id,
                'connection_id' => $connection->id,
                'new_expires_at' => $tokenData['expires_at']->toIso8601String(),
            ]);

            // CRITICAL: Fetch fresh connection from database to get the new decrypted access_token
            // Using refresh() on the existing model doesn't always properly reload encrypted attributes
            $connection = $user->office365Connection()->firstOrFail();
        }

        return $connection;
    }
```

---

## EDIT 2: Fix syncSingleMessage() (lines 464-474) **[CRITICAL FOR WEBHOOKS]**

**Find these lines (464-474):**
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
            // Ensure we have a fresh, valid access token (with 5-minute buffer)
            $connection = $this->ensureFreshAccessToken($user);
```

---

## EDIT 3: Fix syncFolders() (lines 35-45)

**Find these lines (35-45):**
```php
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
```

**Replace with:**
```php
            // Ensure we have a fresh, valid access token
            $connection = $this->ensureFreshAccessToken($user);
```

---

## EDIT 4: Fix initialSync() (lines 104-114 AND lines 147-150)

### Part A (lines 104-114)
**Find:**
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
            // Ensure we have a fresh, valid access token
            $connection = $this->ensureFreshAccessToken($user);
```

### Part B (lines 147-150) - Fix retry logic
**Find:**
```php
                if ($response->status() === 401) {
                    Log::info('Token expired during sync, refreshing and retrying', ['user_id' => $user->id]);
                    $this->office365Service->refreshAccessToken($connection);
                    $connection->refresh();

                    $response = Http::withToken($connection->access_token)
                        ->timeout(30)
                        ->get($url);
                }
```

**Replace with:**
```php
                if ($response->status() === 401) {
                    Log::info('Token expired during sync, refreshing and retrying', ['user_id' => $user->id]);
                    $connection = $this->ensureFreshAccessToken($user);

                    $response = Http::withToken($connection->access_token)
                        ->timeout(30)
                        ->get($url);
                }
```

---

## EDIT 5: Fix processDeltaSync() (lines 337-347 AND lines 361-364)

### Part A (lines 337-347)
**Find:**
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
            // Ensure we have a fresh, valid access token
            $connection = $this->ensureFreshAccessToken($user);
```

### Part B (lines 361-364) - Fix retry logic
**Find:**
```php
                if ($response->status() === 401) {
                    Log::info('Token expired during delta sync, refreshing and retrying', ['user_id' => $user->id]);
                    $this->office365Service->refreshAccessToken($connection);
                    $connection->refresh();

                    $response = Http::withToken($connection->access_token)
                        ->timeout(30)
                        ->get($url);
                }
```

**Replace with:**
```php
                if ($response->status() === 401) {
                    Log::info('Token expired during delta sync, refreshing and retrying', ['user_id' => $user->id]);
                    $connection = $this->ensureFreshAccessToken($user);

                    $response = Http::withToken($connection->access_token)
                        ->timeout(30)
                        ->get($url);
                }
```

---

## Verification

After applying all edits:

1. **Check syntax:**
   ```bash
   php -l app/Services/EmailSyncService.php
   ```

2. **Search for old pattern** (should return 0 results):
   ```bash
   grep -n "office365Service->refreshAccessToken" app/Services/EmailSyncService.php
   ```

3. **Search for new pattern** (should return 1 result in helper method):
   ```bash
   grep -n "ensureFreshAccessToken" app/Services/EmailSyncService.php
   ```

4. **Run tests:**
   ```bash
   php artisan test
   ```

---

## Summary

- ✅ Added 1 helper method (`ensureFreshAccessToken`)
- ✅ Fixed 4 methods (`syncSingleMessage`, `syncFolders`, `initialSync`, `processDeltaSync`)
- ✅ Fixed 2 retry logic blocks
- ✅ Total: ~60 lines removed, ~55 lines added
- ✅ Net effect: Cleaner, more maintainable code

The most critical fix is EDIT 2 (`syncSingleMessage`) as this is what webhooks use.
