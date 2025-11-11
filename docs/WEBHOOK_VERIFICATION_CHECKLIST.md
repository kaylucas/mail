# Webhook Subscription Verification Checklist

This document provides a step-by-step checklist to verify that all webhook subscription fixes are working correctly after the recent updates.

## Summary of Fixes Applied

1. **Automatic Subscription Creation** - Subscriptions now created automatically on every login
2. **Type Coercion Fix** - Configuration properly casts expiration minutes to integer
3. **Validation Request Handling** - Fixed to handle both GET and POST validation requests from Microsoft
4. **Middleware Skip Logic** - Validation requests now properly skip payload validation
5. **Job Uniqueness** - Prevents duplicate subscription creation within 1-hour window

## Files Modified

- ✅ `app/Jobs/CreateUserSubscriptionJob.php` - Added `ShouldBeUnique` interface
- ✅ `app/Http/Controllers/MicrosoftAuthController.php` - Added automatic job dispatch
- ✅ `app/Console/Kernel.php` - Added scheduled maintenance tasks
- ✅ `app/Console/Commands/EnsureUserSubscriptionsCommand.php` - New recovery command
- ✅ `config/services.php` - Type casting for expiration minutes
- ✅ `app/Services/GraphSubscriptionService.php` - Validation method with type safety
- ✅ `app/Http/Controllers/WebhookController.php` - Fixed validation request detection
- ✅ `app/Http/Middleware/ValidateWebhookSignature.php` - Added validation token skip

## Pre-Verification Steps

### 1. Clear All Caches

```bash
# Clear Laravel caches
docker-compose exec app php artisan cache:clear
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan route:clear
docker-compose exec app php artisan view:clear

# Or restart containers to ensure clean state
docker-compose down
docker-compose up -d
```

### 2. Verify Configuration

```bash
# Check that expiration minutes is an integer
docker-compose exec app php artisan tinker --execute="
echo 'Expiration Minutes Type: ' . gettype(config('services.microsoft_graph.subscription_expiration_minutes')) . PHP_EOL;
echo 'Expiration Minutes Value: ' . config('services.microsoft_graph.subscription_expiration_minutes') . PHP_EOL;
echo 'Webhook Base URL: ' . config('services.microsoft_graph.webhook_base_url') . PHP_EOL;
echo 'Notification URL Path: ' . config('services.microsoft_graph.notification_url_path') . PHP_EOL;
"

# Expected output:
# Expiration Minutes Type: integer
# Expiration Minutes Value: 10080
# Webhook Base URL: https://aery.eu.ngrok.io (or your ngrok URL)
# Notification URL Path: /webhooks/microsoft/notifications
```

### 3. Verify Queue Worker is Running

```bash
# Check queue worker status
docker-compose exec app php artisan queue:listen --once

# Or check logs
docker-compose logs -f app | grep -i "processing"
```

### 4. Verify Scheduled Tasks are Registered

```bash
# List scheduled tasks
docker-compose exec app php artisan schedule:list

# Expected output should include:
# - subscriptions:renew --hours=24 (daily)
# - subscriptions:ensure (weekly, Sundays at 02:00)
```

## Test 1: Automatic Subscription Creation on Login

### Goal
Verify that logging in automatically creates a webhook subscription.

### Steps

1. **Clear existing subscriptions** (optional, for clean test):
```bash
docker-compose exec app php artisan tinker --execute="
\$user = App\\Models\\User::where('email', 'your-email@example.com')->first();
if (\$user && \$user->activeEmailSubscription) {
    \$subscription = \$user->activeEmailSubscription;
    \$service = app(App\\Services\\GraphSubscriptionService::class);
    \$service->deleteSubscription(\$subscription);
    echo 'Deleted existing subscription: ' . \$subscription->subscription_id . PHP_EOL;
} else {
    echo 'No active subscription found' . PHP_EOL;
}
"
```

2. **Start monitoring logs**:
```bash
# In one terminal, tail Laravel logs
docker-compose logs -f app | grep -i "subscription"

# In another terminal, monitor queue
docker-compose logs -f app | grep -i "CreateUserSubscriptionJob"
```

3. **Log in via Microsoft OAuth**:
   - Open browser: `http://localhost:5173`
   - Click "Sign in with Microsoft"
   - Complete OAuth flow

4. **Verify in logs**:
   - Look for: `"Subscription creation job dispatched"`
   - Look for: `"CreateUserSubscriptionJob started"`
   - Look for: `"Creating Microsoft Graph subscription"`
   - Look for: `"Successfully created Microsoft Graph subscription"`

5. **Check database**:
```bash
docker-compose exec app php artisan tinker --execute="
\$user = App\\Models\\User::where('email', 'your-email@example.com')->first();
if (\$user && \$user->activeEmailSubscription) {
    \$sub = \$user->activeEmailSubscription;
    echo 'Subscription ID: ' . \$sub->subscription_id . PHP_EOL;
    echo 'Resource: ' . \$sub->resource . PHP_EOL;
    echo 'Status: ' . \$sub->status . PHP_EOL;
    echo 'Expires At: ' . \$sub->expires_at . PHP_EOL;
    echo 'Created At: ' . \$sub->created_at . PHP_EOL;
} else {
    echo 'ERROR: No active subscription found!' . PHP_EOL;
}
"
```

### Expected Result
✅ Subscription created automatically with:
- Status: `active`
- Resource: `me/messages`
- Expires in 7 days (10,080 minutes)

### Troubleshooting

**If no job is dispatched:**
- Check that queue worker is running
- Verify user doesn't already have active subscription
- Check logs for errors in `MicrosoftAuthController::callback()`

**If job fails:**
- Check token hasn't expired: `$user->office365Connection->isTokenExpired()`
- Verify ngrok URL is configured: `config('services.microsoft_graph.webhook_base_url')`
- Check Microsoft Graph API credentials are correct

## Test 2: Validation Request Handling (Critical Fix)

### Goal
Verify that Microsoft's validation requests (GET and POST) return plain text token.

### Steps

1. **Monitor webhook endpoint**:
```bash
# Watch ngrok requests
# Open ngrok dashboard: http://127.0.0.1:4040

# Or monitor Laravel logs
docker-compose logs -f app | grep -i "webhook"
```

2. **Trigger subscription creation** (from Test 1 or manually):
```bash
docker-compose exec app php artisan tinker --execute="
\$user = App\\Models\\User::first();
App\\Jobs\\CreateUserSubscriptionJob::dispatch(\$user);
echo 'Job dispatched for user: ' . \$user->id . PHP_EOL;
"
```

3. **Watch for validation request in logs**:
   - Look for: `"Webhook validation request received"`
   - Look for: `"Webhook validation successful"`
   - **CRITICAL**: Should NOT see `"Webhook notification failed - invalid payload structure"`

4. **Check ngrok dashboard** at `http://127.0.0.1:4040`:
   - Find POST request to `/webhooks/microsoft/notifications?validationToken=...`
   - Response status should be: `200 OK`
   - Response body should be: plain text token (not JSON)
   - Response Content-Type should be: `text/plain`

### Expected Result
✅ Validation request returns:
- Status: `200`
- Content-Type: `text/plain`
- Body: the validation token value (no quotes, no JSON)

### Troubleshooting

**If response is JSON** `{"status":"accepted"}`:
- ❌ FIX NOT APPLIED: Check that `WebhookController::handleNotification()` line 82 is:
  ```php
  if ($request->has('validationToken')) {
  ```
  Not:
  ```php
  if ($request->isMethod('get') && $request->has('validationToken')) {
  ```

**If middleware logs show "invalid payload structure"**:
- ❌ FIX NOT APPLIED: Check that `ValidateWebhookSignature::handle()` has validation token skip at lines 49-60
- Clear route cache: `php artisan route:clear`

**If validation fails with 400 error**:
- Check that routes in `web.php` use `Route::match(['get', 'post'])`
- Verify CSRF middleware is disabled for webhook routes

## Test 3: Webhook Notification Delivery

### Goal
Verify that webhook notifications are received and processed after subscription is created.

### Steps

1. **Ensure subscription is active** (from Test 1).

2. **Monitor logs**:
```bash
docker-compose logs -f app | grep -E "(Webhook notification|ProcessWebhookNotificationJob)"
```

3. **Send yourself a test email**:
   - Open Outlook Web: https://outlook.office.com
   - Send email to yourself from another account
   - Or move an email between folders

4. **Watch for webhook notification in logs**:
   - Look for: `"Webhook notification received"`
   - Look for: `"Processing notification"`
   - Look for: `"clientState validation successful"`
   - Look for: `"Notification stored successfully"`
   - Look for: `"ProcessWebhookNotificationJob dispatched"`

5. **Check webhook notifications table**:
```bash
docker-compose exec app php artisan tinker --execute="
\$count = App\\Models\\WebhookNotification::count();
echo 'Total webhook notifications: ' . \$count . PHP_EOL;

\$latest = App\\Models\\WebhookNotification::latest()->first();
if (\$latest) {
    echo 'Latest notification:' . PHP_EOL;
    echo '  Change Type: ' . \$latest->change_type . PHP_EOL;
    echo '  Created At: ' . \$latest->created_at . PHP_EOL;
    echo '  Subscription ID: ' . \$latest->subscription_id . PHP_EOL;
}
"
```

### Expected Result
✅ Notifications processed successfully:
- Webhook notification saved to `webhook_notifications` table
- Job dispatched: `ProcessWebhookNotificationJob`
- No errors in logs

### Troubleshooting

**If no notifications received:**
- Check ngrok dashboard for incoming requests
- Verify subscription is active in Microsoft Graph
- Try manual test: `POST` request to webhook endpoint with sample payload
- Check that firewall/ngrok isn't blocking requests

**If clientState validation fails:**
- Check that `client_state` in database matches what Microsoft sends
- Verify subscription was created correctly

## Test 4: Manual Recovery Command

### Goal
Verify that the `subscriptions:ensure` command can recover missing subscriptions.

### Steps

1. **Delete a user's subscription**:
```bash
docker-compose exec app php artisan tinker --execute="
\$user = App\\Models\\User::first();
if (\$user->activeEmailSubscription) {
    \$service = app(App\\Services\\GraphSubscriptionService::class);
    \$service->deleteSubscription(\$user->activeEmailSubscription);
    echo 'Deleted subscription for user: ' . \$user->id . PHP_EOL;
}
"
```

2. **Run ensure command in dry-run mode**:
```bash
docker-compose exec app php artisan subscriptions:ensure --dry-run

# Expected output:
# Checking subscriptions for all users...
# User 1 (user@example.com): ✗ No subscription, creating...
#   → Dry run: Job would be dispatched
# Summary: 1 total, 0 already subscribed, 1 jobs dispatched, 0 errors
```

3. **Run ensure command to actually create**:
```bash
docker-compose exec app php artisan subscriptions:ensure

# Expected output:
# User 1 (user@example.com): ✗ No subscription, creating...
#   → Job dispatched: CreateUserSubscriptionJob
```

4. **Verify subscription was created** (use same check as Test 1).

### Expected Result
✅ Command successfully:
- Detects users without subscriptions
- Dispatches `CreateUserSubscriptionJob`
- Creates subscriptions in Microsoft Graph
- Updates database records

## Test 5: Scheduled Maintenance

### Goal
Verify that scheduled tasks are working correctly.

### Steps

1. **Test renewal command**:
```bash
# This won't renew unless subscriptions are expiring within 24 hours
docker-compose exec app php artisan subscriptions:renew --hours=24

# To force test, temporarily set subscription expiration to past
docker-compose exec app php artisan tinker --execute="
\$sub = App\\Models\\GraphSubscription::first();
if (\$sub) {
    \$sub->expires_at = now()->addHours(12); // Expires in 12 hours
    \$sub->save();
    echo 'Set subscription to expire in 12 hours' . PHP_EOL;
}
"

# Now run renewal again
docker-compose exec app php artisan subscriptions:renew --hours=24
```

2. **Test ensure command** (from Test 4).

3. **Verify scheduled tasks**:
```bash
# List scheduled tasks
docker-compose exec app php artisan schedule:list

# Expected output:
# 0 0 * * * subscriptions:renew --hours=24
# 0 2 * * 0 subscriptions:ensure
```

### Expected Result
✅ Scheduled tasks configured:
- Daily renewal at midnight
- Weekly ensure on Sundays at 2am
- Both use `withoutOverlapping()` and `onOneServer()`

## Test 6: Job Uniqueness (Prevent Duplicates)

### Goal
Verify that multiple rapid logins don't create duplicate subscriptions.

### Steps

1. **Dispatch job multiple times rapidly**:
```bash
docker-compose exec app php artisan tinker --execute="
\$user = App\\Models\\User::first();

// Delete existing subscription first
if (\$user->activeEmailSubscription) {
    \$service = app(App\\Services\\GraphSubscriptionService::class);
    \$service->deleteSubscription(\$user->activeEmailSubscription);
}

// Dispatch job 5 times rapidly
for (\$i = 1; \$i <= 5; \$i++) {
    App\\Jobs\\CreateUserSubscriptionJob::dispatch(\$user);
    echo 'Dispatched job #' . \$i . PHP_EOL;
}
"
```

2. **Monitor queue processing**:
```bash
docker-compose logs -f app | grep "CreateUserSubscriptionJob"

# Expected: Only ONE job processes
# Other jobs are skipped due to uniqueness lock
```

3. **Check that only one subscription was created**:
```bash
docker-compose exec app php artisan tinker --execute="
\$user = App\\Models\\User::first();
\$count = App\\Models\\GraphSubscription::where('user_id', \$user->id)->count();
echo 'Subscriptions for user: ' . \$count . PHP_EOL;
// Expected: 1
"
```

### Expected Result
✅ Only ONE subscription created despite 5 job dispatches
- Uniqueness lock prevents duplicates for 1 hour
- Logs show: `"User already has active email subscription, skipping"`

## Quick Reference Commands

### Check User Subscription Status
```bash
docker-compose exec app php artisan tinker --execute="
\$user = App\\Models\\User::where('email', 'your-email@example.com')->first();
if (\$user->activeEmailSubscription) {
    echo 'HAS SUBSCRIPTION' . PHP_EOL;
    echo '  ID: ' . \$user->activeEmailSubscription->subscription_id . PHP_EOL;
    echo '  Status: ' . \$user->activeEmailSubscription->status . PHP_EOL;
    echo '  Expires: ' . \$user->activeEmailSubscription->expires_at . PHP_EOL;
} else {
    echo 'NO SUBSCRIPTION' . PHP_EOL;
}
"
```

### Force Create Subscription
```bash
docker-compose exec app php artisan tinker --execute="
\$user = App\\Models\\User::where('email', 'your-email@example.com')->first();
App\\Jobs\\CreateUserSubscriptionJob::dispatch(\$user);
echo 'Job dispatched' . PHP_EOL;
"
```

### Delete Subscription
```bash
docker-compose exec app php artisan tinker --execute="
\$user = App\\Models\\User::where('email', 'your-email@example.com')->first();
if (\$user->activeEmailSubscription) {
    \$service = app(App\\Services\\GraphSubscriptionService::class);
    \$service->deleteSubscription(\$user->activeEmailSubscription);
    echo 'Deleted' . PHP_EOL;
}
"
```

### Monitor Live Webhook Traffic
```bash
# Watch ngrok dashboard
open http://127.0.0.1:4040

# Or tail logs
docker-compose logs -f app | grep -i webhook
```

### Check Queue Status
```bash
# Process one job
docker-compose exec app php artisan queue:work --once

# Check failed jobs
docker-compose exec app php artisan queue:failed

# Retry failed job
docker-compose exec app php artisan queue:retry {job-id}
```

## Success Criteria

All tests should pass with these results:

- ✅ **Test 1**: Subscription automatically created on login
- ✅ **Test 2**: Validation requests return plain text token (not JSON)
- ✅ **Test 3**: Webhook notifications received and processed
- ✅ **Test 4**: Manual recovery command works
- ✅ **Test 5**: Scheduled tasks configured correctly
- ✅ **Test 6**: Job uniqueness prevents duplicates

## If All Tests Pass

Congratulations! Your webhook subscription system is working correctly:

1. ✅ Automatic subscription creation on every login
2. ✅ Proper validation request handling (GET and POST)
3. ✅ Real-time webhook notifications processing
4. ✅ Scheduled maintenance (renewal + ensure)
5. ✅ Duplicate prevention with job uniqueness
6. ✅ Type-safe configuration with proper casting

## Next Steps

1. **Monitor Production**: Keep an eye on logs for the first few days
2. **Set Up Alerts**: Configure alerts for failed jobs or expired subscriptions
3. **Document Issues**: Report any edge cases discovered during testing
4. **Test at Scale**: Verify behavior with multiple concurrent users

## Common Issues Reference

### Issue: "ValidationError: {"status":"accepted"}"
**Cause**: Controller returning JSON instead of plain text for validation requests
**Fix**: Applied in `WebhookController.php` line 82 - removed `isMethod('get')` check

### Issue: Carbon type error with addMinutes()
**Cause**: Environment variable returned as string, not integer
**Fix**: Applied in `config/services.php` line 61 - explicit `(int)` cast

### Issue: Duplicate subscriptions created
**Cause**: Multiple logins dispatching jobs without deduplication
**Fix**: Applied in `CreateUserSubscriptionJob.php` - added `ShouldBeUnique` interface

### Issue: Middleware blocking validation requests
**Cause**: Middleware trying to parse validation requests as notification payloads
**Fix**: Applied in `ValidateWebhookSignature.php` lines 49-60 - early return for validation tokens

## Support

If you encounter issues not covered here:
1. Check logs: `docker-compose logs -f app | grep -i error`
2. Review ngrok dashboard: `http://127.0.0.1:4040`
3. Refer to `docs/WEBHOOK_DIAGNOSTICS.md` for detailed troubleshooting
4. Check Microsoft Graph API status: https://status.dev.microsoft.com/

---

**Document Version**: 1.0
**Last Updated**: 2025-11-11
**Related Docs**:
- [WEBHOOK_DIAGNOSTICS.md](./WEBHOOK_DIAGNOSTICS.md)
- [WEBHOOK_LOCAL_TESTING.md](./WEBHOOK_LOCAL_TESTING.md)
- [WEBHOOK_TESTING_CHECKLIST.md](./WEBHOOK_TESTING_CHECKLIST.md)
