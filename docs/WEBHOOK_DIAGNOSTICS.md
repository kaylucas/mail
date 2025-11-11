# Webhook Diagnostics Guide

## Overview
This guide helps diagnose why Microsoft Graph webhooks are not receiving notifications. The webhook implementation is complete and correct - issues are typically configuration-related.

## Understanding the Resource Path

**Current Implementation: `me/messages`**

This resource path is **CORRECT** and subscribes to:
- ✅ Inbox messages
- ✅ Sent Items
- ✅ Drafts
- ✅ Deleted Items
- ✅ Junk Email
- ✅ All custom folders
- ✅ All subfolders

**You do NOT need to change the resource path.** The current implementation monitors the entire mailbox as intended.

**Alternative paths (NOT recommended for your use case):**
- `me/mailFolders('inbox')/messages` - Only inbox, excludes subfolders
- `me/mailFolders('{folderId}')/messages` - Specific folder only

Source: Microsoft Graph API documentation confirms `me/messages` covers all folders.

## Step 1: Check if Subscription Exists

### Via Database Query
```bash
# Connect to database
docker-compose exec mariadb mysql -u mail_user -psecret mail

# Check for active subscriptions
SELECT
    id,
    user_id,
    subscription_id,
    resource,
    status,
    expires_at,
    notification_url,
    created_at
FROM graph_subscriptions
WHERE status = 'active'
ORDER BY created_at DESC;

# Check for any subscriptions (including expired)
SELECT
    id,
    user_id,
    subscription_id,
    status,
    expires_at,
    failure_reason
FROM graph_subscriptions
ORDER BY created_at DESC
LIMIT 5;
```

**Expected Results:**
- ✅ **Active subscription found**: Proceed to Step 2
- ❌ **No subscription found**: Create subscription (see Step 6)
- ⚠️ **Expired subscription found**: Delete and recreate (see Step 6)
- ❌ **Failed subscription found**: Check `failure_reason`, fix issue, recreate

### Via API
```bash
# Get your auth token from browser localStorage or login response
TOKEN="your-token-here"

# Check current subscription
curl -X GET http://mail.loc/api/subscriptions/current \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json"
```

**Expected Responses:**
- ✅ **200 OK with subscription data**: Subscription exists and is active
- ❌ **404 Not Found**: No active subscription - create one

### Via Tinker
```bash
docker-compose exec app php artisan tinker

# Check user's active subscription
>>> $user = \App\Models\User::first();
>>> $subscription = $user->activeEmailSubscription;
>>> $subscription ? $subscription->toArray() : 'No active subscription';

# Check subscription status
>>> if ($subscription) {
...     echo "Status: {$subscription->status}\n";
...     echo "Expires: {$subscription->expires_at}\n";
...     echo "Resource: {$subscription->resource}\n";
...     echo "Notification URL: {$subscription->notification_url}\n";
... }
```

## Step 2: Verify ngrok Tunnel

### Check ngrok is Running
```bash
# In a separate terminal, verify ngrok process
ps aux | grep ngrok

# Expected output: ngrok process running
# If not running, start it:
ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80
```

### Verify ngrok URL is Accessible
```bash
# Test ngrok tunnel from external network
curl -I https://aery.eu.ngrok.io

# Expected: HTTP 200 OK or 302 redirect
# If connection refused: ngrok not running or wrong domain
```

### Check ngrok Web Interface
Open http://127.0.0.1:4040 in your browser to see:
- Active tunnel status
- Public URL (should match `WEBHOOK_BASE_URL`)
- Recent HTTP requests
- Request/response details

**Look for:**
- ✅ Tunnel status: "online"
- ✅ Forwarding: `https://aery.eu.ngrok.io -> http://mail.loc:80`
- ⚠️ Recent GET requests to `/webhooks/microsoft/notifications?validationToken=...`
- ⚠️ Recent POST requests to `/webhooks/microsoft/notifications`

## Step 3: Verify Environment Configuration

### Check .env File
```bash
docker-compose exec app cat .env | grep -E 'WEBHOOK|APP_URL|OFFICE365'
```

**Required Settings:**
```env
# Must match ngrok tunnel URL exactly
WEBHOOK_BASE_URL=https://aery.eu.ngrok.io

# Must be set (generate with Str::random(32))
WEBHOOK_SECRET_KEY=your-32-char-random-string

# Optional: defaults to 10080 (7 days)
GRAPH_SUBSCRIPTION_EXPIRATION_MINUTES=10080

# OAuth settings (should also use ngrok for consistency)
APP_URL=https://aery.eu.ngrok.io
OFFICE365_REDIRECT_URI=https://aery.eu.ngrok.io/auth/microsoft/callback
OFFICE365_SCOPES="openid,profile,email,offline_access,User.Read,Mail.Read"
```

**Common Mistakes:**
- ❌ `WEBHOOK_BASE_URL=http://mail.loc` (not publicly accessible)
- ❌ `WEBHOOK_BASE_URL=http://aery.eu.ngrok.io` (HTTP instead of HTTPS)
- ❌ `WEBHOOK_BASE_URL` has trailing slash (causes double slashes in URL)
- ❌ `WEBHOOK_SECRET_KEY` not set (subscription creation fails)
- ❌ `Mail.Read` not in `OFFICE365_SCOPES` (permission denied)

### Verify Configuration is Loaded
```bash
# Check config cache
docker-compose exec app php artisan config:show services.microsoft_graph

# Expected output:
services.microsoft_graph.webhook_base_url => "https://aery.eu.ngrok.io"
services.microsoft_graph.notification_url_path => "/webhooks/microsoft/notifications"
services.microsoft_graph.subscription_expiration_minutes => 10080

# If values are wrong, clear cache and restart:
docker-compose exec app php artisan config:clear
docker-compose restart app
```

## Step 4: Test Validation Endpoint Manually

### Test from External Network
```bash
# Simulate Microsoft's validation request
curl -X GET "https://aery.eu.ngrok.io/webhooks/microsoft/notifications?validationToken=test123" \
  -H "Content-Type: text/plain" \
  -v

# Expected response:
# HTTP/1.1 200 OK
# Content-Type: text/plain
# Body: test123 (plain text, no quotes, no JSON)
```

**Success Indicators:**
- ✅ Status: 200 OK
- ✅ Content-Type: text/plain
- ✅ Body: `test123` (exact match, no formatting)
- ✅ Response time: < 1 second

**Failure Indicators:**
- ❌ Connection refused: ngrok not running
- ❌ 404 Not Found: Route not registered
- ❌ 500 Internal Server Error: Check Laravel logs
- ❌ Response is JSON: Wrong content type (Microsoft will reject)
- ❌ Response has quotes: Wrong format (Microsoft will reject)

### Check Laravel Logs
```bash
# Watch logs in real-time
docker-compose exec app tail -f storage/logs/laravel.log | grep -i webhook

# Look for:
# "Webhook validation request received"
# "Webhook validation successful"

# If you see errors, investigate the stack trace
```

## Step 5: Verify Queue Worker is Running

### Check Queue Worker Status
```bash
# Check if queue worker is running
docker-compose exec app ps aux | grep "queue:work"

# Expected: Process running with "queue:work" command
```

### Start Queue Worker (if not running)
```bash
# Start in foreground (for testing)
docker-compose exec app php artisan queue:work --queue=notifications,default --verbose

# Or start in background (for production)
docker-compose exec -d app php artisan queue:work --queue=notifications,default
```

**Important:** The `ProcessWebhookNotificationJob` uses the `notifications` queue (see [app/Jobs/ProcessWebhookNotificationJob.php:45](app/Jobs/ProcessWebhookNotificationJob.php#L45)). You MUST include `--queue=notifications,default` or notifications won't be processed.

### Check Queue Status
```bash
# Check for pending jobs
docker-compose exec app php artisan queue:monitor

# Check for failed jobs
docker-compose exec app php artisan queue:failed

# If failed jobs exist, inspect them:
docker-compose exec app php artisan queue:failed --verbose
```

## Step 6: Create or Recreate Subscription

### Delete Existing Subscription (if needed)
```bash
TOKEN="your-token-here"

# Delete current subscription
curl -X DELETE http://mail.loc/api/subscriptions \
  -H "Authorization: Bearer $TOKEN"

# Expected: 204 No Content
```

### Create New Subscription
```bash
TOKEN="your-token-here"

# Create subscription with default settings (me/messages, all change types)
curl -X POST http://mail.loc/api/subscriptions \
  -H "Authorization: Bearer $TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'

# Expected response (201 Created):
{
  "message": "Subscription created successfully",
  "subscription": {
    "id": 1,
    "subscription_id": "abc-123-def-456",
    "resource": "me/messages",
    "change_types": ["created", "updated", "deleted"],
    "expires_at": "2024-12-01T10:00:00Z",
    "status": "active"
  }
}
```

**What Happens During Creation:**
1. Laravel calls Microsoft Graph API to create subscription
2. Microsoft sends GET request to `https://aery.eu.ngrok.io/webhooks/microsoft/notifications?validationToken=...`
3. `WebhookController::handleNotification()` receives request
4. Controller returns validationToken as plain text
5. Microsoft validates response and creates subscription
6. Microsoft returns subscription ID
7. Laravel stores subscription in `graph_subscriptions` table

**Common Errors:**

**Error: "Subscription validation request timed out"**
- Cause: Microsoft couldn't reach validation endpoint or response took >10 seconds
- Solution: Verify ngrok is running, test validation endpoint manually (Step 4)

**Error: "No active Office365 connection found"**
- Cause: User not authenticated with Microsoft OAuth
- Solution: Log in via Microsoft OAuth first

**Error: "Failed to create Graph subscription: Forbidden"**
- Cause: Missing `Mail.Read` permission in OAuth scopes
- Solution: Add `Mail.Read` to `OFFICE365_SCOPES`, re-authenticate

**Error: "409 Conflict - Active subscription already exists"**
- Cause: Subscription already exists for this user
- Solution: Delete existing subscription first, or wait for expiration

## Step 7: Test Webhook Notifications

### Send Test Email
1. Send an email to your Office365 account (the one authenticated in the app)
2. Wait 5-10 seconds for notification
3. Check if email appears in the application

### Monitor Webhook Traffic

**Watch ngrok Web Interface:**
- Open http://127.0.0.1:4040
- Look for POST requests to `/webhooks/microsoft/notifications`
- Click on request to see payload details
- Verify `subscriptionId` matches your subscription
- Verify `clientState` is present
- Verify `changeType` is "created"

**Watch Laravel Logs:**
```bash
docker-compose exec app tail -f storage/logs/laravel.log | grep -E 'Webhook|notification'

# Look for:
# "Webhook notification received"
# "Processing notification"
# "clientState validation successful"
# "Notification stored successfully"
# "ProcessWebhookNotificationJob dispatched"
```

**Watch Queue Worker:**
```bash
# If running in foreground, you'll see:
# [timestamp] Processing: App\Jobs\ProcessWebhookNotificationJob
# [timestamp] Processed: App\Jobs\ProcessWebhookNotificationJob
```

**Check Database:**
```bash
docker-compose exec mariadb mysql -u mail_user -psecret mail

# Check webhook notifications received
SELECT
    id,
    subscription_id,
    change_type,
    resource,
    created_at
FROM webhook_notifications
ORDER BY created_at DESC
LIMIT 10;

# Check if emails were synced
SELECT
    id,
    subject,
    from_email,
    received_date_time,
    created_at
FROM emails
ORDER BY created_at DESC
LIMIT 10;
```

## Step 8: Common Issues and Solutions

### Issue: Subscription Created But No Notifications

**Possible Causes:**
1. **ngrok tunnel restarted with different URL**
   - Solution: Delete and recreate subscription with new URL
   - Prevention: Use ngrok reserved domain (aery.eu.ngrok.io)

2. **Subscription expired**
   - Check: `SELECT expires_at FROM graph_subscriptions WHERE status='active';`
   - Solution: Renew or recreate subscription
   - Note: Maximum lifetime is 7 days, must renew regularly

3. **Queue worker not running**
   - Check: `ps aux | grep queue:work`
   - Solution: Start queue worker (Step 5)

4. **Firewall blocking Microsoft's servers**
   - Check: ngrok web interface shows no POST requests
   - Solution: Verify ngrok tunnel is accessible from internet

5. **clientState validation failing**
   - Check logs for: "SECURITY WARNING: clientState validation failed"
   - Cause: Subscription recreated with different `WEBHOOK_SECRET_KEY`
   - Solution: Delete old subscription, create new one

### Issue: Validation Endpoint Returns 500 Error

**Check Laravel Logs:**
```bash
docker-compose exec app tail -100 storage/logs/laravel.log | grep -A 20 "Webhook validation"
```

**Common Causes:**
- Database connection error
- Missing middleware
- PHP error in controller

**Solution:** Fix error in logs, restart application

### Issue: Notifications Received But Emails Not Syncing

**Check Failed Jobs:**
```bash
docker-compose exec app php artisan queue:failed

# If failed jobs exist, inspect details:
docker-compose exec app php artisan queue:failed --verbose

# Common errors:
# - Token expired: Refresh Office365 token
# - Message not found: Message was deleted before sync
# - Permission denied: Missing Mail.Read scope
```

**Check Job Logs:**
```bash
docker-compose exec app tail -f storage/logs/laravel.log | grep ProcessWebhookNotificationJob

# Look for errors in job execution
```

**Retry Failed Jobs:**
```bash
# Retry all failed jobs
docker-compose exec app php artisan queue:retry all

# Retry specific job
docker-compose exec app php artisan queue:retry {job-id}
```

## Step 9: Verify Subscription with Microsoft

### Check Subscription Status via API
```bash
TOKEN="your-token-here"

# Get current subscription details
curl -X GET http://mail.loc/api/subscriptions/current \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" | jq

# Check response:
{
  "subscription": {
    "id": 1,
    "subscription_id": "abc-123-def-456",
    "resource": "me/messages",
    "change_types": ["created", "updated", "deleted"],
    "expires_at": "2024-12-01T10:00:00Z",
    "last_renewed_at": null,
    "status": "active",
    "is_expiring_soon": false,
    "time_until_expiration": "6 days"
  },
  "connection": {
    "is_active": true,
    "is_token_expired": false
  }
}
```

**Verify:**
- ✅ `status`: "active"
- ✅ `resource`: "me/messages" (covers all folders)
- ✅ `change_types`: ["created", "updated", "deleted"]
- ✅ `expires_at`: Future date
- ✅ `connection.is_active`: true
- ✅ `connection.is_token_expired`: false

### Query Microsoft Graph Directly
```bash
# Get access token from database
docker-compose exec app php artisan tinker
>>> $connection = \App\Models\Office365Connection::first();
>>> $token = $connection->access_token;
>>> echo $token;

# Query Microsoft Graph API directly
curl -X GET "https://graph.microsoft.com/v1.0/subscriptions" \
  -H "Authorization: Bearer {access-token}" \
  -H "Accept: application/json" | jq

# Verify your subscription appears in the list
# Check expirationDateTime, resource, notificationUrl
```

## Step 10: Enable Debug Logging

### Increase Log Verbosity
```bash
# Edit .env
LOG_LEVEL=debug

# Restart application
docker-compose restart app
```

### Add Custom Logging (Temporary)
If you need more detailed logs, you can temporarily add logging to:
- [app/Http/Controllers/WebhookController.php](app/Http/Controllers/WebhookController.php) - Log all incoming requests
- [app/Jobs/ProcessWebhookNotificationJob.php](app/Jobs/ProcessWebhookNotificationJob.php) - Log job execution details
- [app/Services/EmailSyncService.php](app/Services/EmailSyncService.php) - Log email sync operations

### Monitor All Logs
```bash
# Watch all logs in real-time
docker-compose logs -f app

# Filter for webhook-related logs
docker-compose logs -f app | grep -i webhook
```

## Summary Checklist

✅ **Configuration:**
- [ ] ngrok tunnel running at `https://aery.eu.ngrok.io`
- [ ] `WEBHOOK_BASE_URL=https://aery.eu.ngrok.io` in `.env`
- [ ] `WEBHOOK_SECRET_KEY` set in `.env`
- [ ] `Mail.Read` in `OFFICE365_SCOPES`
- [ ] Application restarted after config changes

✅ **Subscription:**
- [ ] Active subscription exists in database
- [ ] Subscription status is "active"
- [ ] Subscription not expired
- [ ] Resource is `me/messages` (covers all folders)
- [ ] Change types include "created", "updated", "deleted"

✅ **Endpoints:**
- [ ] Validation endpoint returns 200 OK with plain text
- [ ] Validation response time < 10 seconds
- [ ] ngrok web interface shows incoming requests

✅ **Queue:**
- [ ] Queue worker running with `--queue=notifications,default`
- [ ] No failed jobs in queue
- [ ] Jobs processing successfully

✅ **Testing:**
- [ ] Test email sent to Office365 account
- [ ] Webhook POST request received (check ngrok)
- [ ] Notification stored in `webhook_notifications` table
- [ ] Email synced to `emails` table
- [ ] Email visible in frontend

## Next Steps

If you've completed all steps and webhooks still aren't working:

1. **Collect Diagnostic Information:**
   - Subscription details from database
   - Recent Laravel logs (last 100 lines)
   - ngrok request history
   - Failed job details
   - Environment configuration

2. **Check Microsoft Graph Service Health:**
   - Visit https://status.cloud.microsoft/
   - Check for outages or issues with Microsoft Graph API

3. **Verify OAuth Permissions:**
   - Log out and re-authenticate
   - Ensure consent screen shows `Mail.Read` permission
   - Check Azure AD app registration has correct API permissions

4. **Test with Minimal Configuration:**
   - Create fresh subscription with default settings
   - Use simple test email
   - Monitor all logs simultaneously

## Additional Resources

- **Microsoft Graph Documentation:** https://learn.microsoft.com/en-us/graph/webhooks
- **Subscription Resource Type:** https://learn.microsoft.com/en-us/graph/api/resources/subscription
- **Change Notifications Overview:** https://learn.microsoft.com/en-us/graph/change-notifications-overview
- **Webhook Validation:** https://learn.microsoft.com/en-us/graph/change-notifications-delivery-webhooks
- **Local Testing Guide:** [docs/WEBHOOK_LOCAL_TESTING.md](docs/WEBHOOK_LOCAL_TESTING.md)
- **Quick Start Checklist:** [docs/WEBHOOK_TESTING_CHECKLIST.md](docs/WEBHOOK_TESTING_CHECKLIST.md)
