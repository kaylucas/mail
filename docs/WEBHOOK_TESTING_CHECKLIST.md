# Webhook Testing Checklist

Quick-start checklist for testing Microsoft Graph webhooks locally. For detailed explanations, see [WEBHOOK_LOCAL_TESTING.md](WEBHOOK_LOCAL_TESTING.md).

## 5-Minute Setup

### 1. Start ngrok
```bash
ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80
```

Verify: Open https://aery.eu.ngrok.io in browser - should show your app.

### 2. Configure Environment

Generate secret key:
```bash
docker-compose exec app php artisan tinker
```
```php
Str::random(32)  // Copy this value
exit
```

Update `.env`:
```env
WEBHOOK_BASE_URL=https://aery.eu.ngrok.io
WEBHOOK_SECRET_KEY=<paste-generated-secret-here>
```

### 3. Restart Application
```bash
docker-compose restart app
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
```

### 4. Start Queue Worker
```bash
docker-compose exec app php artisan queue:work --queue=notifications,default --verbose
```

Keep this terminal open to watch job processing.

### 5. Test Validation Endpoint
```bash
curl -X GET "https://aery.eu.ngrok.io/webhooks/microsoft/notifications?validationToken=test123"
```

Expected: `test123` (plain text, no JSON)

### 6. Create Subscription
```bash
curl -X POST http://mail.loc/api/subscriptions \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{}'
```

Expected: `201 Created` with subscription details.

### 7. Verify Subscription
```bash
docker-compose exec app php artisan tinker
```
```php
App\Models\GraphSubscription::latest()->first();
exit
```

Should show active subscription with expiration date.

### 8. Test Notification

Send yourself an email. Watch these terminals:
- **Queue worker**: Should show job processing
- **Laravel logs**: `docker-compose exec app php artisan pail`
- **ngrok**: http://127.0.0.1:4040 shows POST request

Check email arrived:
```bash
docker-compose exec app php artisan tinker
```
```php
App\Models\Email::latest()->first();
exit
```

## Verification Commands

### Check Webhook Configuration
```bash
docker-compose exec app php artisan tinker
```
```php
config('services.microsoft_graph.webhook_base_url')
// Should be: "https://aery.eu.ngrok.io"

config('services.microsoft_graph.notification_url_path')
// Should be: "/webhooks/microsoft/notifications"
exit
```

### Check Active Subscription
```bash
curl -X GET http://mail.loc/api/subscriptions/current \
  -H "Authorization: Bearer YOUR_TOKEN" | jq
```

### Check Recent Notifications
```bash
docker-compose exec app php artisan tinker
```
```php
// Last 5 notifications
App\Models\WebhookNotification::latest()->take(5)->get(['id', 'change_type', 'processed_at', 'created_at']);

// Unprocessed notifications
App\Models\WebhookNotification::whereNull('processed_at')->count();

// Last synced email
App\Models\Email::latest()->first(['subject', 'from', 'created_at']);
exit
```

### Check Queue Status
```bash
docker-compose exec app php artisan tinker
```
```php
// Pending jobs
DB::table('jobs')->count();

// Failed jobs
DB::table('failed_jobs')->latest()->first();
exit
```

### Check ngrok Traffic
```bash
# Open ngrok web interface
open http://127.0.0.1:4040

# Look for POST to /webhooks/microsoft/notifications
```

## Quick Troubleshooting

### Validation Fails
```bash
# Test validation endpoint
curl -X GET "https://aery.eu.ngrok.io/webhooks/microsoft/notifications?validationToken=test123"

# Should return: test123 (plain text)

# Check route exists
docker-compose exec app php artisan route:list | grep webhooks
```

### No Notifications Received
```bash
# Check subscription status
curl -X GET http://mail.loc/api/subscriptions/current \
  -H "Authorization: Bearer YOUR_TOKEN"

# Verify ngrok is running
curl -I https://aery.eu.ngrok.io

# Check subscription in database
docker-compose exec app php artisan tinker
```
```php
$sub = App\Models\GraphSubscription::latest()->first();
echo "Status: " . $sub->status . "\n";
echo "Expires: " . $sub->expires_at . "\n";
exit
```

### Queue Not Processing
```bash
# Check queue worker is running
docker-compose exec app php artisan queue:work --queue=notifications,default --verbose

# Process jobs manually
docker-compose exec app php artisan queue:work --once

# Check for failed jobs
docker-compose exec app php artisan tinker
```
```php
DB::table('failed_jobs')->latest()->first();
exit
```

### clientState Validation Failed
```bash
# Check logs for security warnings
docker-compose logs -f app | grep "clientState"

# Delete and recreate subscription
curl -X DELETE http://mail.loc/api/subscriptions \
  -H "Authorization: Bearer YOUR_TOKEN"

# Wait 30 seconds, then recreate
curl -X POST http://mail.loc/api/subscriptions \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### Emails Not Syncing
```bash
# Check Office365 token validity
curl -X GET http://mail.loc/api/office365/connections \
  -H "Authorization: Bearer YOUR_TOKEN"

# Manually process notification
docker-compose exec app php artisan tinker
```
```php
$notification = App\Models\WebhookNotification::latest()->first();
\App\Jobs\ProcessWebhookNotificationJob::dispatch($notification);
exit
```

## Success Indicators

Your webhook setup is working correctly when you see:

### Laravel Logs
```
[INFO] Webhook validation request received
[INFO] Webhook validation successful
[INFO] Webhook notification received
[INFO] Processing notification
[INFO] clientState validation successful
[INFO] Notification stored successfully
[INFO] ProcessWebhookNotificationJob dispatched
[INFO] ProcessWebhookNotificationJob started
[INFO] Syncing message from webhook notification
[INFO] Message synced successfully from webhook
[INFO] ProcessWebhookNotificationJob completed
```

### Queue Worker Output
```
[2025-11-08 12:00:00][1] Processing: App\Jobs\ProcessWebhookNotificationJob
[2025-11-08 12:00:01][1] Processed:  App\Jobs\ProcessWebhookNotificationJob
```

### ngrok Web Interface
- POST requests to `/webhooks/microsoft/notifications`
- Status: 202 Accepted
- Response time: < 1 second

### Database State
```bash
docker-compose exec app php artisan tinker
```
```php
// Active subscription
$sub = App\Models\GraphSubscription::where('status', 'active')->first();
// Should exist and not be expired

// Processed notifications
App\Models\WebhookNotification::whereNotNull('processed_at')->count();
// Should increase with each email

// Synced emails
App\Models\Email::count();
// Should increase with each notification
exit
```

### Frontend
- Emails appear instantly after sending
- No manual refresh needed
- Email count updates automatically

## Common Mistakes

### Do NOT Do This

- ❌ Access app at `http://localhost:5173` during webhook testing - use `http://mail.loc`
- ❌ Set `WEBHOOK_BASE_URL=http://mail.loc` - must be public HTTPS URL
- ❌ Forget to restart app after changing `.env`
- ❌ Run queue worker in background without monitoring
- ❌ Test without ngrok tunnel running
- ❌ Use JSON response for validation - must be plain text
- ❌ Reuse same `clientState` for multiple subscriptions
- ❌ Create multiple active subscriptions for same resource
- ❌ Expect instant results without queue worker running

### Do This Instead

- ✅ Access app at `http://mail.loc` for consistent session
- ✅ Set `WEBHOOK_BASE_URL=https://aery.eu.ngrok.io` (ngrok URL)
- ✅ Restart app: `docker-compose restart app && docker-compose exec app php artisan config:clear`
- ✅ Run queue worker with `--verbose` in foreground during testing
- ✅ Start ngrok first, then create subscription
- ✅ Return plain text for validation endpoint
- ✅ Let system generate unique `clientState` for each subscription
- ✅ Delete old subscription before creating new one
- ✅ Monitor queue worker, Laravel logs, and ngrok web interface
- ✅ Be patient - notifications may take 1-2 seconds to arrive

## Environment Switching

### Testing Webhooks Only
```env
WEBHOOK_BASE_URL=https://aery.eu.ngrok.io
APP_URL=http://mail.loc
OFFICE365_REDIRECT_URI=http://mail.loc/auth/microsoft/callback
SESSION_SECURE_COOKIE=false
```

### Testing OAuth + Webhooks
```env
WEBHOOK_BASE_URL=https://aery.eu.ngrok.io
APP_URL=https://aery.eu.ngrok.io
OFFICE365_REDIRECT_URI=https://aery.eu.ngrok.io/auth/microsoft/callback
SESSION_SECURE_COOKIE=false
```

### Production
```env
WEBHOOK_BASE_URL=https://mail.example.com
APP_URL=https://mail.example.com
OFFICE365_REDIRECT_URI=https://mail.example.com/auth/microsoft/callback
SESSION_SECURE_COOKIE=true
```

Remember: **Always restart app and clear caches** after changing environment variables!

## Quick Links

- **ngrok Web Interface**: http://127.0.0.1:4040
- **Application Frontend**: http://localhost:5173
- **Application Backend**: http://mail.loc
- **API Documentation**: [routes/api.php](/routes/api.php)
- **Detailed Guide**: [WEBHOOK_LOCAL_TESTING.md](WEBHOOK_LOCAL_TESTING.md)
- **ngrok Setup**: [NGROK_SETUP.md](/NGROK_SETUP.md)

## One-Line Helpers

```bash
# Check if ngrok is running
curl -I https://aery.eu.ngrok.io 2>/dev/null | head -n1

# Test validation endpoint
curl "https://aery.eu.ngrok.io/webhooks/microsoft/notifications?validationToken=test" 2>/dev/null

# Get current subscription status
curl -s http://mail.loc/api/subscriptions/current -H "Authorization: Bearer $TOKEN" | jq '.subscription.status'

# Count pending webhook jobs
docker-compose exec app php -r "require 'vendor/autoload.php'; \$app = require_once 'bootstrap/app.php'; \$app->make('db'); echo DB::table('jobs')->where('queue', 'notifications')->count() . PHP_EOL;"

# Watch Laravel logs live
docker-compose exec app php artisan pail --filter=webhook

# Clear everything and start fresh
docker-compose restart app && docker-compose exec app php artisan config:clear && docker-compose exec app php artisan cache:clear
```

## Time Estimates

- **Initial setup**: 5 minutes
- **Create subscription**: 30 seconds
- **First test email**: 1-2 minutes (including sending email)
- **Notification processing**: 1-2 seconds
- **Troubleshooting common issue**: 5-10 minutes
- **Full webhook flow verification**: 10 minutes

## Need Help?

If you're stuck after following this checklist:

1. Review detailed guide: [WEBHOOK_LOCAL_TESTING.md](WEBHOOK_LOCAL_TESTING.md)
2. Check Laravel logs: `docker-compose exec app php artisan pail`
3. Check ngrok traffic: http://127.0.0.1:4040
4. Verify all success indicators above
5. Review common mistakes section
6. Try deleting and recreating subscription

Still stuck? Check these files for implementation details:
- `app/Http/Controllers/WebhookController.php`
- `app/Services/GraphSubscriptionService.php`
- `app/Jobs/ProcessWebhookNotificationJob.php`
