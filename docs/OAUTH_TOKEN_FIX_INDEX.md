# OAuth Token Expiration Fix - Documentation Index

## Quick Start

**Problem:** Webhooks fail with 401 "token is expired" error  
**Solution:** Fix Laravel encrypted attribute caching in token refresh  
**Time to Fix:** 10 minutes (or 2 minutes for emergency quick fix)

---

## Documentation Files

### 1. Overview & Analysis
**File:** [OAUTH_TOKEN_FIX_2025-11-12.md](./OAUTH_TOKEN_FIX_2025-11-12.md)  
**Purpose:** Complete root cause analysis, solution architecture, and testing plan  
**Read this if:** You want to understand WHY the fix works and WHAT the long-term implications are

**Contents:**
- Problem statement
- Root cause analysis (Laravel encrypted attribute caching)
- Solution architecture
- Testing procedures
- Long-term recommendations

---

### 2. Implementation Guide
**File:** [OAUTH_TOKEN_FIX_IMPLEMENTATION_GUIDE.md](./OAUTH_TOKEN_FIX_IMPLEMENTATION_GUIDE.md)  
**Purpose:** Step-by-step code changes with exact line numbers  
**Read this if:** You're ready to apply the fix right now

**Contents:**
- Quick reference (files, time estimate)
- EDIT 1: Add helper method
- EDIT 2-5: Update 4 sync methods
- Verification commands
- Testing checklist

---

### 3. Quick Fix (Emergency)
**File:** [QUICK_FIX_WEBHOOK_TOKEN_ISSUE.md](./QUICK_FIX_WEBHOOK_TOKEN_ISSUE.md)  
**Purpose:** Minimal 2-minute fix to get webhooks working immediately  
**Read this if:** You need webhooks working NOW and will apply full fix later

**Contents:**
- Single critical edit to `syncSingleMessage()` method
- Bypasses encrypted attribute cache issue for webhooks only
- Other sync methods still have old behavior (fix them later)

---

### 4. Solution Summary
**File:** [OAUTH_SOLUTION_SUMMARY.txt](./OAUTH_SOLUTION_SUMMARY.txt)  
**Purpose:** Executive summary with all key information in one place  
**Read this if:** You want a printable overview of the entire solution

**Contents:**
- Root cause analysis
- Solution overview
- Implementation steps
- Testing checklist  
- Files reference
- Impact assessment

---

### 5. Visual Diagram
**File:** [OAUTH_TOKEN_ISSUE_DIAGRAM.txt](./OAUTH_TOKEN_ISSUE_DIAGRAM.txt)  
**Purpose:** Visual explanation of the problem and solution with flow diagrams  
**Read this if:** You learn better with visual representations

**Contents:**
- Before/after flow diagrams
- Laravel encrypted attribute behavior explanation
- 5-minute buffer visualization
- Implementation pseudocode
- Testing examples

---

## Implementation Paths

### Path A: Complete Fix (Recommended)
**Time:** 10 minutes  
**Files:** 1 file to modify (`app/Services/EmailSyncService.php`)  
**Changes:** 5 edits (1 new method + 4 method updates)  

1. Read: [OAUTH_TOKEN_FIX_IMPLEMENTATION_GUIDE.md](./OAUTH_TOKEN_FIX_IMPLEMENTATION_GUIDE.md)
2. Apply all 5 edits
3. Test: `php -l app/Services/EmailSyncService.php`
4. Verify: Run full test suite

**Result:** All sync methods fixed, cleaner code, proper token handling

---

### Path B: Quick Fix + Complete Later
**Time:** 2 minutes now + 8 minutes later  
**Phase 1 (Emergency):**
1. Read: [QUICK_FIX_WEBHOOK_TOKEN_ISSUE.md](./QUICK_FIX_WEBHOOK_TOKEN_ISSUE.md)
2. Apply single edit to `syncSingleMessage()`
3. Test: Verify webhooks work

**Phase 2 (Later):**
1. Read: [OAUTH_TOKEN_FIX_IMPLEMENTATION_GUIDE.md](./OAUTH_TOKEN_FIX_IMPLEMENTATION_GUIDE.md)
2. Apply remaining 4 edits
3. Test: Full verification

**Result:** Webhooks fixed immediately, complete fix applied when time permits

---

## Key Files to Modify

```
app/Services/EmailSyncService.php
  ├─ ADD: ensureFreshAccessToken() helper (line 24)
  ├─ EDIT: syncSingleMessage() (lines 464-474) [CRITICAL]
  ├─ EDIT: syncFolders() (lines 35-45)
  ├─ EDIT: initialSync() (lines 104-114, 147-150)
  └─ EDIT: processDeltaSync() (lines 337-347, 361-364)
```

**Backup:** `app/Services/EmailSyncService.php.backup`

---

## Testing Commands

### Syntax Check
```bash
php -l app/Services/EmailSyncService.php
```

### Run Tests
```bash
php artisan test
```

### Force Token Expiration (Manual Test)
```bash
php artisan tinker
```

```php
$user = User::find(1);
$user->office365Connection->update(['token_expires_at' => now()->subHour()]);
$service = app(App\Services\EmailSyncService::class);
$service->syncSingleMessage($user, 'test-message-id', true);
// Check logs for "Access token refreshed successfully"
```

### Monitor Logs
```bash
tail -f storage/logs/laravel.log | grep token
```

---

## Expected Log Output

After applying fix, successful token refresh logs:

```
[2025-11-12 ...] Access token expired or expiring soon, refreshing 
  {"user_id":1,"expires_at":"2025-11-12T10:00:00Z","is_expired":true}
  
[2025-11-12 ...] Access token refreshed successfully 
  {"user_id":1,"new_expires_at":"2025-11-12T11:00:00Z"}
  
[2025-11-12 ...] Syncing single message 
  {"user_id":1,"message_id":"AAMkAD...","is_webhook_sync":true}
  
[2025-11-12 ...] Single message synced 
  {"user_id":1,"subject":"Test Email"}
```

---

## Rollback

If issues occur:

```bash
cp app/Services/EmailSyncService.php.backup app/Services/EmailSyncService.php
```

---

## Related Files (No Changes Needed)

These files were analyzed but don't need modification:

- ✅ `app/Services/Office365Service.php` - Already has correct refresh logic
- ✅ `app/Models/Office365Connection.php` - Model is fine
- ✅ `app/Jobs/ProcessWebhookNotificationJob.php` - Calls sync correctly

---

## Long-Term Improvements (Optional)

After applying immediate fix, consider:

1. **Proactive Token Refresh** (High Priority)
   - Create scheduled command to refresh expiring tokens
   - Run hourly to catch tokens before they expire
   - Prevents 401 errors entirely

2. **Monitoring** (Medium Priority)
   - Track token refresh frequency per user
   - Alert on repeated failures
   - Monitor 401 error rates

3. **Enhanced Error Handling** (Low Priority)
   - Add retry logic to all Graph API calls
   - Implement circuit breaker pattern
   - Increase buffer time for long operations

See [OAUTH_TOKEN_FIX_2025-11-12.md](./OAUTH_TOKEN_FIX_2025-11-12.md) for implementation details.

---

## Support

**Questions about implementation?**  
→ See [OAUTH_TOKEN_FIX_IMPLEMENTATION_GUIDE.md](./OAUTH_TOKEN_FIX_IMPLEMENTATION_GUIDE.md)

**Want to understand the root cause?**  
→ See [OAUTH_TOKEN_FIX_2025-11-12.md](./OAUTH_TOKEN_FIX_2025-11-12.md)

**Need webhooks working NOW?**  
→ See [QUICK_FIX_WEBHOOK_TOKEN_ISSUE.md](./QUICK_FIX_WEBHOOK_TOKEN_ISSUE.md)

**Want visual explanation?**  
→ See [OAUTH_TOKEN_ISSUE_DIAGRAM.txt](./OAUTH_TOKEN_ISSUE_DIAGRAM.txt)

---

**Last Updated:** 2025-11-12  
**Status:** ✅ Solution Ready - Ready for Implementation  
**Priority:** 🔴 CRITICAL
