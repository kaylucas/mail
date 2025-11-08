# Webhook Local Testing Guide

Complete guide for testing Microsoft Graph webhooks in your local development environment.

## Overview

Microsoft Graph webhooks enable real-time notifications when emails change (created, updated, deleted). Since Microsoft needs to send POST requests to your application, webhooks require a **publicly accessible HTTPS endpoint**. This guide shows you how to use ngrok to expose your local application for webhook testing.

### Why ngrok is Required

- Microsoft Graph only sends webhooks to HTTPS URLs
- Local URLs like `http://mail.loc` are not accessible from the internet
- ngrok creates a secure tunnel from a public HTTPS URL to your local application
- Same tunnel can serve both OAuth callbacks AND webhook notifications

## Prerequisites

Before starting, ensure you have:

- **ngrok account** with reserved domain (`aery.eu.ngrok.io`)
- **Application running** at `http://mail.loc` via Docker + Traefik
- **Valid Office365 connection** (authenticated via Microsoft OAuth)
- **Queue worker running** to process webhook notifications
- **Docker Desktop** and containers started

## Step-by-Step Testing Guide

### Step 1: Start ngrok Tunnel

Start the ngrok tunnel to expose your local application:

```bash
ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80
```

**Explanation of flags:**
- `--domain=aery.eu.ngrok.io`: Use your reserved ngrok domain (consistent URL)
- `--host-header=rewrite`: Rewrites `Host` header to `mail.loc` so Traefik routes correctly
- `mail.loc:80`: Target your local Traefik proxy on port 80

**Expected output:**
```
Session Status                online
Account                       Your Name (Plan: Free)
Version                       3.x.x
Region                        United States (us)
Latency                       -
Web Interface                 http://127.0.0.1:4040
Forwarding                    https://aery.eu.ngrok.io -> http://mail.loc:80
```

**Verify tunnel is working:**
```bash
curl -I https://aery.eu.ngrok.io
```

You should see a 200 response from your application.

### Step 2: Configure Environment Variables

Update your `.env` file with webhook configuration:

```env
# Webhook Configuration
WEBHOOK_BASE_URL=https://aery.eu.ngrok.io
WEBHOOK_SECRET_KEY=<generate-with-Str-random-32>
GRAPH_SUBSCRIPTION_EXPIRATION_MINUTES=10080
GRAPH_SUBSCRIPTION_RENEWAL_THRESHOLD_HOURS=12

# OAuth Configuration (if testing both)
APP_URL=https://aery.eu.ngrok.io
OFFICE365_REDIRECT_URI=https://aery.eu.ngrok.io/auth/microsoft/callback
```

**Generate secure webhook secret:**
```bash
docker-compose exec app php artisan tinker
```

Then in Tinker:
```php
Str::random(32)
// Output: "8f7d9e6c5b4a3f2e1d0c9b8a7f6e5d4c"
// Copy this value to WEBHOOK_SECRET_KEY
exit
```

### Step 3: Restart Application

Restart the application to load new environment variables:

```bash
# Restart app container
docker-compose restart app

# Clear configuration cache
docker-compose exec app php artisan config:clear

# Clear application cache
docker-compose exec app php artisan cache:clear

# Verify configuration
docker-compose exec app php artisan tinker
```

In Tinker, verify:
```php
config('services.microsoft_graph.webhook_base_url')
// Should output: "https://aery.eu.ngrok.io"

config('services.microsoft_graph.notification_url_path')
// Should output: "/webhooks/microsoft/notifications"
exit
```

### Step 4: Start Queue Worker

Start the queue worker to process webhook notifications asynchronously:

```bash
# Start queue worker in foreground (recommended for testing)
docker-compose exec app php artisan queue:work --queue=notifications,default --verbose

# Or run in background
docker-compose exec -d app php artisan queue:work --queue=notifications,default
```

**What this does:**
- Monitors `notifications` queue (webhook jobs) and `default` queue
- Processes `ProcessWebhookNotificationJob` jobs when notifications arrive
- `--verbose` flag shows detailed processing information

**Keep this terminal open** to monitor job processing in real-time.

### Step 5: Test Validation Endpoint

Before creating a subscription, test that Microsoft can reach your validation endpoint:

```bash
curl -X GET "https://aery.eu.ngrok.io/webhooks/microsoft/notifications?validationToken=test123"
```

**Expected response:**
```
test123
```

**Response format:**
- Content-Type: `text/plain`
- Status: `200 OK`
- Body: Exact validationToken value (no JSON, no quotes)

**If it fails:**
- Check ngrok tunnel is running
- Verify `https://aery.eu.ngrok.io` is accessible
- Check Laravel logs: `docker-compose logs -f app`

### Step 6: Create Webhook Subscription

Create a subscription using the API:

```bash
# Get your API token first
# Login via OAuth at http://localhost:5173 and check localStorage.getItem('token')

# Create subscription
curl -X POST http://mail.loc/api/subscriptions \
  -H "Authorization: Bearer YOUR_API_TOKEN_HERE" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{}'
```

**Expected response (201 Created):**
```json
{
  "message": "Subscription created successfully",
  "subscription": {
    "id": 1,
    "subscription_id": "7f392407-5ab0-4c60-9896-3fc9d51c6b68",
    "resource": "me/messages",
    "change_types": ["created", "updated", "deleted"],
    "expires_at": "2025-11-15T12:00:00.000000Z",
    "status": "active"
  }
}
```

**What happens behind the scenes:**

1. **API request received** → `SubscriptionController::store()`
2. **Subscription parameters prepared** → Resource: `me/messages`, Change types: `created,updated,deleted`
3. **Microsoft Graph API called** → `POST https://graph.microsoft.com/v1.0/subscriptions`
4. **Microsoft validates endpoint** → Sends GET with `validationToken` to your webhook URL
5. **Your app responds** → Returns validationToken as plain text (handled by `WebhookController::handleNotification()`)
6. **Microsoft confirms** → Returns subscription ID and expiration
7. **Database record created** → `graph_subscriptions` table with `clientState` secret
8. **Subscription active** → Ready to receive notifications

**Common errors:**

- **"No active Office365 connection found"** → Authenticate via Microsoft OAuth first
- **"Subscription validation timeout"** → ngrok tunnel down or validation endpoint not responding
- **409 Conflict** → Active subscription already exists (delete it first)

### Step 7: Verify Subscription in Database

Check that the subscription was created:

```bash
docker-compose exec app php artisan tinker
```

```php
// View all subscriptions
App\Models\GraphSubscription::with('user')->get();

// Check your subscription
$sub = App\Models\GraphSubscription::latest()->first();
echo "Subscription ID: " . $sub->subscription_id . "\n";
echo "Status: " . $sub->status . "\n";
echo "Expires: " . $sub->expires_at . "\n";
echo "Resource: " . $sub->resource . "\n";
echo "Change Types: " . implode(', ', $sub->change_types) . "\n";
exit
```

**Verify via API:**
```bash
curl -X GET http://mail.loc/api/subscriptions/current \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Step 8: Trigger Test Notification

Send yourself a test email to trigger a webhook notification:

**Method 1: Send email from another account**
1. Open Outlook or Gmail
2. Send email to your authenticated Microsoft account
3. Email arrives → Microsoft sends webhook POST to your app

**Method 2: Use Microsoft Graph API directly**
```bash
# Send email to yourself via Graph API
curl -X POST https://graph.microsoft.com/v1.0/me/sendMail \
  -H "Authorization: Bearer YOUR_OFFICE365_ACCESS_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "message": {
      "subject": "Webhook Test",
      "body": {
        "contentType": "Text",
        "content": "Testing webhook notifications"
      },
      "toRecipients": [
        {
          "emailAddress": {
            "address": "your-email@example.com"
          }
        }
      ]
    }
  }'
```

**What should happen:**

1. **Email arrives** in your Microsoft mailbox
2. **Microsoft detects change** (new message created)
3. **Webhook POST sent** to `https://aery.eu.ngrok.io/webhooks/microsoft/notifications`
4. **Your app receives notification** → `WebhookController::handleNotification()`
5. **Validation performed** → `clientState` checked against database
6. **Notification stored** → `webhook_notifications` table
7. **Job dispatched** → `ProcessWebhookNotificationJob` added to queue
8. **Queue worker processes** → Fetches email from Graph API, stores in database
9. **Email appears** in your application's email list

### Step 9: Monitor Webhook Traffic

Monitor the webhook flow in multiple places:

#### 9.1. Laravel Logs

Tail Laravel logs to see webhook processing:

```bash
# Option 1: Using pail (recommended)
docker-compose exec app php artisan pail

# Option 2: Using logs command
docker-compose logs -f app

# Option 3: Direct log file
docker-compose exec app tail -f storage/logs/laravel.log
```

**Look for these log entries:**

```
[INFO] Webhook notification received
  notification_count: 1
  request_ip: 3.x.x.x (Microsoft IP)

[INFO] Processing notification
  subscription_id: 7f392407-5ab0-4c60-9896-3fc9d51c6b68
  change_type: created
  resource: Users/{user-id}/Messages/{message-id}

[INFO] clientState validation successful
  subscription_id: 7f392407-5ab0-4c60-9896-3fc9d51c6b68

[INFO] Notification stored successfully
  notification_id: 1
  change_type: created

[INFO] ProcessWebhookNotificationJob dispatched
  notification_id: 1

[INFO] ProcessWebhookNotificationJob started
  notification_id: 1
  attempt: 1

[INFO] Syncing message from webhook notification
  message_id: AAMkAD...
  user_id: 1

[INFO] Message synced successfully from webhook
  email_id: 42
  subject: "Webhook Test"

[INFO] ProcessWebhookNotificationJob completed
  notification_id: 1
```

#### 9.2. ngrok Web Interface

Open ngrok's web interface to inspect HTTP traffic:

```bash
# Open in browser
open http://127.0.0.1:4040
```

**Features:**
- View all requests to your tunnel
- Inspect request/response headers
- Replay requests for debugging
- See timing information

**Look for POST requests to `/webhooks/microsoft/notifications`**

#### 9.3. Queue Worker Output

If running queue worker in foreground with `--verbose`:

```
[2025-11-08 12:00:00][1] Processing: App\Jobs\ProcessWebhookNotificationJob
[2025-11-08 12:00:01][1] Processed:  App\Jobs\ProcessWebhookNotificationJob
```

#### 9.4. Database Inspection

Check database tables:

```bash
docker-compose exec app php artisan tinker
```

```php
// Check webhook notifications
App\Models\WebhookNotification::latest()->take(5)->get();

// Check if notification was processed
$notification = App\Models\WebhookNotification::latest()->first();
echo "Processed at: " . $notification->processed_at . "\n";

// Check new emails
App\Models\Email::latest()->take(5)->get(['subject', 'from', 'created_at']);

exit
```

## Troubleshooting

### Subscription Validation Timeout

**Symptoms:**
- Subscription creation fails
- Error: "Subscription validation timeout" or similar from Microsoft

**Causes:**
1. ngrok tunnel not running
2. Validation endpoint not responding
3. Response format incorrect (must be plain text, not JSON)
4. Firewall blocking Microsoft IPs

**Solutions:**

1. **Verify ngrok tunnel:**
   ```bash
   curl -I https://aery.eu.ngrok.io
   ```

2. **Test validation endpoint directly:**
   ```bash
   curl -X GET "https://aery.eu.ngrok.io/webhooks/microsoft/notifications?validationToken=test123"
   # Should return: test123
   ```

3. **Check Laravel logs:**
   ```bash
   docker-compose logs -f app | grep "Webhook validation"
   ```

4. **Verify route exists:**
   ```bash
   docker-compose exec app php artisan route:list | grep webhooks
   ```

### No Notifications Received

**Symptoms:**
- Subscription created successfully
- Emails arrive but no webhook POST received
- No logs in Laravel or ngrok

**Causes:**
1. Subscription expired
2. Microsoft can't reach your endpoint
3. ngrok tunnel disconnected
4. Subscription was deleted

**Solutions:**

1. **Check subscription status:**
   ```bash
   curl -X GET http://mail.loc/api/subscriptions/current \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```

2. **Verify subscription in Microsoft:**
   ```bash
   docker-compose exec app php artisan tinker
   ```
   ```php
   $sub = App\Models\GraphSubscription::latest()->first();
   $service = app(\App\Services\GraphSubscriptionService::class);
   $status = $service->getSubscriptionStatus($sub->subscription_id);
   print_r($status);
   exit
   ```

3. **Check ngrok is still running:**
   ```bash
   curl -I https://aery.eu.ngrok.io
   ```

4. **Recreate subscription:**
   ```bash
   # Delete old subscription
   curl -X DELETE http://mail.loc/api/subscriptions \
     -H "Authorization: Bearer YOUR_TOKEN"

   # Create new subscription
   curl -X POST http://mail.loc/api/subscriptions \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```

### clientState Validation Failed

**Symptoms:**
- Webhook POST received
- Log shows: "SECURITY WARNING: clientState validation failed"
- Notification not processed

**Causes:**
1. Database record has different `client_state` than notification
2. Subscription was recreated but old notifications still arriving
3. Multiple subscriptions with same resource

**Solutions:**

1. **Check clientState in database:**
   ```bash
   docker-compose exec app php artisan tinker
   ```
   ```php
   $sub = App\Models\GraphSubscription::latest()->first();
   echo "Client State: " . $sub->client_state . "\n";
   exit
   ```

2. **Delete and recreate subscription:**
   ```bash
   curl -X DELETE http://mail.loc/api/subscriptions \
     -H "Authorization: Bearer YOUR_TOKEN"

   # Wait 30 seconds for Microsoft to process deletion

   curl -X POST http://mail.loc/api/subscriptions \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```

3. **Check for duplicate subscriptions:**
   ```bash
   docker-compose exec app php artisan tinker
   ```
   ```php
   App\Models\GraphSubscription::where('status', 'active')->get();
   exit
   ```

### Emails Not Syncing After Notification

**Symptoms:**
- Webhook POST received successfully
- Notification stored in database
- Job dispatched but email not appearing

**Causes:**
1. Queue worker not running
2. Job failing silently
3. Office365 token expired
4. Message deleted before sync

**Solutions:**

1. **Check queue worker is running:**
   ```bash
   docker-compose exec app php artisan queue:work --queue=notifications,default --verbose
   ```

2. **Check failed jobs:**
   ```bash
   docker-compose exec app php artisan tinker
   ```
   ```php
   DB::table('failed_jobs')->latest()->first();
   exit
   ```

3. **Check job logs:**
   ```bash
   docker-compose logs -f app | grep "ProcessWebhookNotificationJob"
   ```

4. **Process notification manually:**
   ```bash
   docker-compose exec app php artisan tinker
   ```
   ```php
   $notification = App\Models\WebhookNotification::latest()->first();
   \App\Jobs\ProcessWebhookNotificationJob::dispatch($notification);
   exit
   ```

5. **Refresh Office365 token:**
   ```bash
   curl -X GET http://mail.loc/api/office365/connections \
     -H "Authorization: Bearer YOUR_TOKEN"
   # Check if token_expires_at is in the past
   ```

### ngrok Tunnel Keeps Disconnecting

**Symptoms:**
- ngrok tunnel drops after a few minutes
- Webhooks stop working intermittently

**Causes:**
1. Free ngrok plan has connection limits
2. Network instability
3. ngrok client outdated

**Solutions:**

1. **Keep ngrok running in dedicated terminal:**
   ```bash
   # Don't run ngrok in background, keep terminal open
   ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80
   ```

2. **Upgrade ngrok plan** for more stability and features

3. **Use ngrok config file for auto-reconnect:**
   ```yaml
   # ~/.ngrok2/ngrok.yml
   version: "2"
   authtoken: YOUR_AUTH_TOKEN
   tunnels:
     mail:
       proto: http
       domain: aery.eu.ngrok.io
       addr: mail.loc:80
       host_header: rewrite
   ```

   Then start with: `ngrok start mail`

4. **Monitor ngrok status:**
   - Check ngrok terminal for disconnect messages
   - View ngrok dashboard: http://127.0.0.1:4040

### OAuth Callback vs Webhook Confusion

**Important:** OAuth and webhooks are **different features** using the same ngrok tunnel.

| Feature | Purpose | Flow |
|---------|---------|------|
| **OAuth** | User authentication | User → Microsoft → ngrok → Laravel → Redirect to frontend |
| **Webhooks** | Real-time notifications | Email change → Microsoft → ngrok → Laravel → Queue job |

**OAuth endpoints:**
- `GET /auth/microsoft` - Initiates OAuth
- `GET /auth/microsoft/callback` - Receives OAuth callback

**Webhook endpoints:**
- `GET /webhooks/microsoft/notifications?validationToken=...` - Subscription validation
- `POST /webhooks/microsoft/notifications` - Change notifications
- `POST /webhooks/microsoft/lifecycle` - Lifecycle events

**Both use same tunnel but different paths:**
```
https://aery.eu.ngrok.io/auth/microsoft/callback  ← OAuth
https://aery.eu.ngrok.io/webhooks/microsoft/notifications  ← Webhooks
```

## Testing Checklist

Use this checklist to verify your webhook setup is working:

- [ ] ngrok tunnel running and accessible
- [ ] `.env` updated with `WEBHOOK_BASE_URL` and `WEBHOOK_SECRET_KEY`
- [ ] Application restarted and caches cleared
- [ ] Queue worker running on `notifications` queue
- [ ] Validation endpoint returns plain text token
- [ ] Subscription created successfully via API
- [ ] Subscription shows `status: "active"` in database
- [ ] Test email sent to trigger notification
- [ ] Webhook POST visible in ngrok web interface
- [ ] Laravel logs show "Webhook notification received"
- [ ] Laravel logs show "clientState validation successful"
- [ ] Laravel logs show "ProcessWebhookNotificationJob dispatched"
- [ ] Queue worker processes job successfully
- [ ] Email appears in database and frontend
- [ ] Subscription expiration tracked correctly

## Advanced: Webhook Replay for Testing

Sometimes you need to replay a webhook notification for debugging. Here's how:

### Method 1: ngrok Web Interface Replay

1. Open ngrok web interface: http://127.0.0.1:4040
2. Find the POST request to `/webhooks/microsoft/notifications`
3. Click "Replay" button
4. Check Laravel logs to see if notification processed

### Method 2: Manual curl Replay

1. **Capture webhook payload from ngrok:**
   - Copy JSON payload from ngrok web interface

2. **Replay with curl:**
   ```bash
   curl -X POST https://aery.eu.ngrok.io/webhooks/microsoft/notifications \
     -H "Content-Type: application/json" \
     -d '{
       "value": [
         {
           "subscriptionId": "your-subscription-id",
           "clientState": "your-client-state",
           "changeType": "created",
           "resource": "Users/{user-id}/Messages/{message-id}",
           "resourceData": {
             "@odata.type": "#Microsoft.Graph.Message",
             "@odata.id": "Users/{user-id}/Messages/{message-id}",
             "id": "{message-id}"
           },
           "tenantId": "{tenant-id}"
         }
       ]
     }'
   ```

### Method 3: Database-Driven Replay

Re-process a stored notification:

```bash
docker-compose exec app php artisan tinker
```

```php
// Find notification
$notification = App\Models\WebhookNotification::find(1);

// Reset processed_at to reprocess
$notification->update(['processed_at' => null]);

// Dispatch job
\App\Jobs\ProcessWebhookNotificationJob::dispatch($notification);

exit
```

## Production Deployment Notes

When deploying to production, webhook setup differs:

### Production Configuration

```env
# Use your production domain (not ngrok)
WEBHOOK_BASE_URL=https://mail.example.com

# Generate new production secret
WEBHOOK_SECRET_KEY=<production-secret-32-chars>

# Keep standard values
GRAPH_SUBSCRIPTION_EXPIRATION_MINUTES=10080
GRAPH_SUBSCRIPTION_RENEWAL_THRESHOLD_HOURS=12
```

### Production Differences

| Aspect | Local (ngrok) | Production |
|--------|---------------|------------|
| Webhook URL | `https://aery.eu.ngrok.io` | `https://mail.example.com` |
| HTTPS | ngrok terminates SSL | Server/CDN terminates SSL |
| Stability | Tunnel can disconnect | Always available |
| Renewal | Manual restart needed | Automatic via scheduled job |
| Monitoring | ngrok web interface | Application monitoring tools |

### Production Checklist

- [ ] Domain has valid SSL certificate
- [ ] Webhook endpoints publicly accessible
- [ ] Queue workers running as systemd service or supervisor
- [ ] Scheduled job configured for subscription renewal
- [ ] Error monitoring configured (Sentry, etc.)
- [ ] Rate limiting configured for webhook endpoints
- [ ] Database backups include `graph_subscriptions` and `webhook_notifications`

## Related Files

### Controllers
- `app/Http/Controllers/WebhookController.php` - Handles GET validation and POST notifications
- `app/Http/Controllers/SubscriptionController.php` - API endpoints for subscription management

### Services
- `app/Services/GraphSubscriptionService.php` - Subscription create/renew/delete logic
- `app/Services/Office365Service.php` - Microsoft Graph API communication
- `app/Services/EmailSyncService.php` - Email sync logic

### Jobs
- `app/Jobs/ProcessWebhookNotificationJob.php` - Async webhook processing
- `app/Jobs/CreateUserSubscriptionJob.php` - Auto-creates subscription after OAuth

### Models
- `app/Models/GraphSubscription.php` - Subscription database model
- `app/Models/WebhookNotification.php` - Notification storage model

### Middleware
- `app/Http/Middleware/ValidateWebhookSignature.php` - clientState validation

### Configuration
- `config/services.php` - Webhook configuration (lines 46-72)
- `.env.example` - Environment variable examples (lines 110-136)

### Routes
- `routes/web.php` - Webhook routes (lines 20-28)
- `routes/api.php` - Subscription API routes (lines 38-44)

### Documentation
- `NGROK_SETUP.md` - OAuth-focused ngrok setup
- `docs/WEBHOOK_TESTING_CHECKLIST.md` - Quick reference checklist

## Next Steps

After successfully testing webhooks locally:

1. **Test different change types:**
   - Create email (send new email)
   - Update email (mark as read in Outlook)
   - Delete email (delete in Outlook)

2. **Test subscription renewal:**
   ```bash
   curl -X POST http://mail.loc/api/subscriptions/renew \
     -H "Authorization: Bearer YOUR_TOKEN"
   ```

3. **Test subscription lifecycle:**
   - Let subscription expire
   - Verify auto-renewal (if scheduled job configured)
   - Test recreation after deletion

4. **Monitor production deployment:**
   - Configure error monitoring
   - Set up alerts for failed jobs
   - Monitor subscription expiration

5. **Scale considerations:**
   - Multiple queue workers for high volume
   - Redis queue driver for better performance
   - Dedicated queue for webhook processing
