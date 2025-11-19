# OAuth Token Expiration Fix - Implementation Checklist

## Pre-Implementation

- [ ] Read START HERE: `docs/OAUTH_TOKEN_FIX_INDEX.md`
- [ ] Choose implementation path:
  - [ ] Complete Fix (10 min) - Recommended
  - [ ] Quick Fix (2 min) - Emergency only
- [ ] Verify backup exists: `app/Services/EmailSyncService.php.backup`

## Implementation (Complete Fix)

### Step 1: Add Helper Method
- [ ] Open `app/Services/EmailSyncService.php`
- [ ] Go to line 23 (after constructor)
- [ ] Insert `ensureFreshAccessToken()` method
- [ ] Code from: `docs/OAUTH_TOKEN_FIX_IMPLEMENTATION_GUIDE.md` → EDIT 1

### Step 2: Fix syncSingleMessage() [CRITICAL - Webhooks]
- [ ] Find lines 464-474
- [ ] Replace token check code with helper call
- [ ] Code from: EDIT 2

### Step 3: Fix syncFolders()
- [ ] Find lines 35-45
- [ ] Replace token check code with helper call  
- [ ] Code from: EDIT 3

### Step 4: Fix initialSync()
- [ ] Part A: Find lines 104-114, replace with helper call
- [ ] Part B: Find lines 147-150 (retry logic), update to use helper
- [ ] Code from: EDIT 4

### Step 5: Fix processDeltaSync()
- [ ] Part A: Find lines 337-347, replace with helper call
- [ ] Part B: Find lines 361-364 (retry logic), update to use helper
- [ ] Code from: EDIT 5

## Verification

- [ ] Syntax check: `php -l app/Services/EmailSyncService.php`
- [ ] Search for old pattern: `grep -n "office365Service->refreshAccessToken" app/Services/EmailSyncService.php`
  - Expected: 1 result (in new helper method only)
- [ ] Search for new pattern: `grep -n "ensureFreshAccessToken" app/Services/EmailSyncService.php`
  - Expected: 5+ results (helper + 4 method calls)
- [ ] Run test suite: `php artisan test`

## Testing

### Manual Token Expiration Test
- [ ] Open tinker: `php artisan tinker`
- [ ] Force expiration:
  ```php
  $user = User::find(1);
  $user->office365Connection->update(['token_expires_at' => now()->subHour()]);
  ```
- [ ] Trigger sync:
  ```php
  $service = app(App\Services\EmailSyncService::class);
  $service->syncSingleMessage($user, 'test-message-id', true);
  ```
- [ ] Check logs: `tail -f storage/logs/laravel.log | grep token`
- [ ] Expected logs:
  - [ ] "Access token expired or expiring soon, refreshing"
  - [ ] "Access token refreshed successfully"
  - [ ] "Single message synced"

### Integration Test
- [ ] Wait for next webhook notification (or trigger manually)
- [ ] Monitor logs for token refresh
- [ ] Verify no 401 errors occur
- [ ] Verify email syncs successfully

## Post-Implementation

- [ ] Monitor logs for 24 hours
- [ ] Verify no regression in other features
- [ ] Check webhook processing success rate
- [ ] Document any issues encountered

## Rollback (If Needed)

- [ ] Stop application/queue workers
- [ ] Run: `cp app/Services/EmailSyncService.php.backup app/Services/EmailSyncService.php`
- [ ] Verify syntax: `php -l app/Services/EmailSyncService.php`
- [ ] Restart application/queue workers
- [ ] Document reason for rollback

## Optional Improvements (After Stable)

- [ ] Add proactive token refresh command
  - [ ] Create `app/Console/Commands/RefreshOAuthTokensCommand.php`
  - [ ] Schedule hourly in `app/Console/Kernel.php`
  - [ ] Test command execution
  
- [ ] Add monitoring
  - [ ] Track token refresh frequency
  - [ ] Alert on repeated failures (>3)
  - [ ] Monitor 401 error rates
  
- [ ] Enhanced error handling
  - [ ] Add retry logic to all Graph API calls
  - [ ] Implement circuit breaker pattern
  - [ ] Increase buffer time for long operations

## Documentation

- [ ] Update team wiki/docs with this fix
- [ ] Share fix details with team
- [ ] Add notes about Laravel encrypted attribute behavior
- [ ] Document lessons learned

## Sign-Off

- [ ] Implementation complete
- [ ] Testing passed
- [ ] No regressions found
- [ ] Monitoring in place
- [ ] Team notified

**Implemented by:** _______________  
**Date:** _______________  
**Reviewed by:** _______________  
**Status:** ⬜ Pending  ⬜ In Progress  ⬜ Complete  ⬜ Rolled Back

---

## Notes

Use this space to document any issues, observations, or deviations from the plan:

```
[Add notes here]
```

---

**Reference:** `docs/OAUTH_TOKEN_FIX_INDEX.md`
