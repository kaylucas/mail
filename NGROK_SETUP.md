# ngrok Tunnel Setup for Microsoft OAuth Testing

This guide explains how to use ngrok to create a public tunnel to your local development environment, allowing Microsoft's OAuth servers to reach your application for authentication callbacks.

## Why ngrok?

- Microsoft OAuth requires a publicly accessible redirect URI
- Local URLs (http://mail.loc, http://localhost) are not accessible from the internet
- ngrok creates a secure tunnel from a public URL to your local application
- No changes to your application code or configuration are needed (except redirect URI)

## Prerequisites

- ngrok account with custom domain configured (aery.eu.ngrok.io)
- Local application running at http://mail.loc via Docker + Traefik
- Traefik proxy network configured and running

## Step 1: Verify Local Application

1. Ensure Docker containers are running:
   ```bash
   docker-compose ps
   ```

2. Verify Traefik is routing to mail.loc:
   ```bash
   curl -I http://mail.loc
   ```

3. Open browser to http://mail.loc and confirm the application loads

4. Check that http://localhost:5173 shows the frontend dev server

## Step 2: Configure ngrok Tunnel

1. **Install ngrok:**
   ```bash
   # macOS
   brew install ngrok

   # Or download from https://ngrok.com
   ```

2. **Authenticate:**
   ```bash
   ngrok authtoken YOUR_AUTH_TOKEN
   ```

3. **Start the tunnel with host header rewriting:**
   ```bash
   ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80
   ```

   Alternative if Traefik is on a different port:
   ```bash
   ngrok http --domain=aery.eu.ngrok.io --host-header=mail.loc 80
   ```

4. **Verify tunnel status** in ngrok dashboard or terminal output

5. **Test external access:** Open https://aery.eu.ngrok.io in a browser

6. You should see the same application as http://mail.loc

## Step 3: Configure Azure App Registration

Navigate to Azure Portal: https://portal.azure.com/#blade/Microsoft_AAD_IAM/ActiveDirectoryMenuBlade/RegisteredApps

### A. Create or Select App Registration

- If creating new: Click "New registration"
- Name: "Mail Application" (or your preference)
- Supported account types: "Accounts in any organizational directory (Any Azure AD directory - Multitenant) and personal Microsoft accounts"
- Click "Register"

### B. Configure Redirect URIs

1. Navigate to "Authentication" in the left sidebar
2. Under "Platform configurations", click "Add a platform" (or "Add URI" if platform exists)
3. Select "Web"
4. Add THREE redirect URIs:
   - `https://aery.eu.ngrok.io/auth/microsoft/callback` ← **Primary for OAuth via ngrok**
   - `http://mail.loc/auth/microsoft/callback` ← For direct local testing (won't work for OAuth)
   - `http://localhost:5173/auth/microsoft/callback` ← For frontend dev server (won't work for OAuth)
5. Click "Save"

**Why multiple URIs?** While only the ngrok URL will work for actual OAuth (Microsoft needs to reach it), having all three documented helps with local testing and debugging.

### C. Configure API Permissions

1. Navigate to "API permissions"
2. Click "Add a permission" → "Microsoft Graph" → "Delegated permissions"
3. Add the following permissions:
   - **OpenId permissions**:
     - `openid` - Sign users in
     - `profile` - View users' basic profile
     - `email` - View users' email address
     - `offline_access` - Maintain access to data (refresh tokens)
   - **User permissions**:
     - `User.Read` - Read user profile (REQUIRED for /me endpoint)
   - **Mail permissions**:
     - `Mail.Read` - Read user mail
4. Click "Add permissions"
5. If you have admin rights, click "Grant admin consent for [Organization]"
6. If not, users will consent on first login

**Important:** The `User.Read` scope is required for the Microsoft Graph API `/me` endpoint to fetch user profile information. Without it, you may receive a 403 Forbidden error.

### D. Create Client Secret

1. Navigate to "Certificates & secrets"
2. Under "Client secrets", click "New client secret"
3. Description: "Mail App Development Secret"
4. Expires: Choose duration (recommend 12-24 months for development)
5. Click "Add"
6. **IMMEDIATELY COPY THE SECRET VALUE** - it will only be shown once
7. Store it securely - you'll need it for the .env file

### E. Copy Application Credentials

1. Navigate to "Overview"
2. Copy "Application (client) ID" - this is your `OFFICE365_CLIENT_ID`
3. Copy "Directory (tenant) ID" - optional, can use "common" for multitenant

## Step 4: Update .env File

Your `.env` file must be configured to use the ngrok HTTPS URL. Update these values:

```env
# Application URL - MUST USE NGROK URL FOR OAUTH
APP_URL=https://aery.eu.ngrok.io

# Sanctum domains - ADD NGROK DOMAIN
SANCTUM_STATEFUL_DOMAINS=localhost:5173,mail.loc,localhost,127.0.0.1,aery.eu.ngrok.io

# CORS origins - ADD NGROK HTTPS URL
CORS_ALLOWED_ORIGINS=http://localhost:5173,https://aery.eu.ngrok.io

# Session - CRITICAL: Must be false for HTTP development
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=none
SESSION_DOMAIN=null

# Microsoft OAuth - ADD YOUR AZURE CREDENTIALS
OFFICE365_TENANT_ID=common
OFFICE365_CLIENT_ID=your-client-id-from-azure
OFFICE365_CLIENT_SECRET=your-client-secret-from-azure
OFFICE365_REDIRECT_URI=https://aery.eu.ngrok.io/auth/microsoft/callback
OFFICE365_SCOPES="openid,profile,email,offline_access,User.Read,Mail.Read"
```

### Critical Configuration Notes

#### Always Access at http://mail.loc

**Important**: Always access the application at `http://mail.loc`, NOT `http://localhost:5173`.

- `http://mail.loc` - Laravel serves the application (backend + frontend)
- `http://localhost:5173` - Vite dev server for hot-reload (development only, not for user access)

The Vite dev server provides hot-reload functionality, but the actual application should be accessed through Laravel at `http://mail.loc`. This ensures:
- Single session domain (no cookie conflicts)
- Consistent authentication state
- Proper routing and middleware execution
- Correct asset serving in development

#### Understanding SESSION_SECURE_COOKIE=false

**Why false even with HTTPS ngrok?**

This is the most common point of confusion. Here's the complete flow:

1. **OAuth callback** happens over HTTPS via ngrok ✓
2. **Session is created** and stored in the database ✓
3. **User is redirected** to `http://mail.loc/#/dashboard` (HTTP) ✓
4. **All subsequent requests** happen at `http://mail.loc` (HTTP)
5. **Browser checks cookie security**: If `SESSION_SECURE_COOKIE=true`, the cookie has the `Secure` flag
6. **Browser refuses to send** cookies marked `Secure` over HTTP connections
7. **Result**: Backend receives requests without session cookie → "Unauthenticated"

**The solution**: Set `SESSION_SECURE_COOKIE=false` so the session cookie can be transmitted over HTTP at `mail.loc`.

**Security note**: This is safe for local development because:
- The application runs locally (mail.loc resolves to 127.0.0.1)
- No sensitive data is transmitted over the internet without encryption
- OAuth tokens are still exchanged over HTTPS (ngrok)
- In production, you would set this to `true` when the entire stack is HTTPS

**Why APP_URL must be the ngrok HTTPS URL:**
- Laravel uses `APP_URL` to determine if the application is running over HTTPS
- When `APP_URL` is HTTP but requests come through HTTPS (via ngrok), session cookies don't work properly
- Browsers require `Secure` flag on cookies for HTTPS sites
- With `APP_URL=http://mail.loc` and `SESSION_SECURE_COOKIE=false`, cookies are created as HTTP-only
- When Microsoft redirects back via HTTPS, the browser won't send HTTP-only cookies
- This creates a new session, losing the OAuth state, causing "Invalid state parameter" error

## Step 5: Verify Trusted Proxy Configuration

The application is already configured to trust proxies like ngrok. This is **essential** for maintaining sessions through the OAuth flow.

**Why this matters:**
- ngrok forwards requests and adds headers like `X-Forwarded-Host`, `X-Forwarded-Proto`
- Laravel needs to trust these headers to know the original request came from `https://aery.eu.ngrok.io`
- Without trusted proxies, Laravel thinks all requests come from `http://mail.loc`
- This breaks session cookies and causes "Invalid state parameter" errors

**Configuration files:**
- `bootstrap/app.php` - Registers trusted proxy middleware with `$middleware->trustProxies(at: '*')`
- `app/Http/Middleware/TrustProxies.php` - Configures which headers to trust

**Verify configuration:**
```bash
# Check that TrustProxies middleware exists
cat app/Http/Middleware/TrustProxies.php

# Should show: protected $proxies = '*';
```

This configuration is already in place - no action needed!

## Step 6: Restart Application

```bash
# Install dependencies (if not already done)
docker-compose exec app composer install

# Restart to pick up new environment variables
docker-compose restart app

# Clear caches
docker-compose exec app php artisan config:clear
docker-compose exec app php artisan cache:clear
```

## Step 7: Test the OAuth Flow

1. **Open the application:** Navigate to http://mail.loc in your browser
   - **Important**: Use `http://mail.loc`, NOT `http://localhost:5173`
   - The Vite dev server at localhost:5173 is for hot-reload only
   - Accessing via localhost:5173 creates a different session and causes authentication issues

2. **Click "Sign in with Microsoft":** This should redirect you to Microsoft's login page

3. **Check the redirect URL:** In the browser address bar, you should see `login.microsoftonline.com` with a `redirect_uri` parameter containing `https://aery.eu.ngrok.io/auth/microsoft/callback`

4. **Sign in:** Enter your Microsoft credentials

5. **Grant consent:** If prompted, grant the requested permissions

6. **Callback:** Microsoft will redirect to `https://aery.eu.ngrok.io/auth/microsoft/callback?code=...`

7. **ngrok forwards:** The request goes through ngrok to `http://mail.loc/auth/microsoft/callback`

8. **Application processes:** Laravel exchanges the code for tokens and logs you in

9. **Final redirect:** You should be redirected to the dashboard at http://mail.loc/#/dashboard
   - Verify you're at `http://mail.loc`, not `localhost:5173`
   - Check browser DevTools → Application → Cookies
   - Verify session cookie exists and does NOT have the `Secure` flag

## Step 8: Verify Connection

- Dashboard should show "Connected to Microsoft 365"
- User profile information should be displayed
- Token expiration date should be shown

## Troubleshooting

#### Error: "Unauthenticated" after successful OAuth login

**Symptoms**:
- OAuth flow completes successfully
- Logs show "User authenticated successfully" and "Session established on local domain"
- User is redirected to dashboard
- Frontend shows "Unauthenticated" error when calling `/api/user`
- Browser DevTools shows session cookie is not being sent with API requests

**Cause 1: Wrong access URL**
You're accessing the app at `http://localhost:5173` instead of `http://mail.loc`. This creates different sessions and cookie domains.

**Solution**:
1. Always access the application at `http://mail.loc`
2. Close all browser tabs at `localhost:5173`
3. Clear browser cookies
4. Access `http://mail.loc` and try OAuth again

**Cause 2: SESSION_SECURE_COOKIE=true**
The session cookie is marked with the `Secure` flag, which prevents browsers from sending it over HTTP connections.

**Solution**:
1. Set `SESSION_SECURE_COOKIE=false` in `.env`
2. Restart the application: `docker-compose restart app`
3. Clear browser cookies and cache
4. Access `http://mail.loc` and try OAuth again

**Verification**:
1. Open browser DevTools → Application/Storage → Cookies
2. After successful login, check the session cookie (usually named `laravel-session`)
3. Verify the cookie domain is `mail.loc` (or empty for current domain)
4. Verify it does NOT have the `Secure` flag
5. Verify it has `SameSite=None` flag
6. Make an API request and verify the cookie is sent in the request headers

### Issue: "Redirect URI mismatch" error from Microsoft

**Cause:** The redirect URI in the OAuth request doesn't match Azure configuration

**Solution:**
- Verify `OFFICE365_REDIRECT_URI` in .env is `https://aery.eu.ngrok.io/auth/microsoft/callback`
- Check Azure App Registration has this exact URI (no trailing slash, correct protocol)
- Restart the app after changing .env: `docker-compose restart app`

### Issue: "This site can't be reached" when Microsoft redirects

**Cause:** ngrok tunnel is not running or not configured correctly

**Solution:**
- Check ngrok is running: Look for the ngrok terminal window
- Verify tunnel URL: Should show "Forwarding https://aery.eu.ngrok.io -> http://mail.loc:80"
- Test tunnel: Open https://aery.eu.ngrok.io in a browser - should show your app
- Check host header: Use `--host-header=rewrite` or `--host-header=mail.loc`

### Issue: "Invalid state parameter" error

**Cause:** Session not being maintained through ngrok tunnel

**Solution:**
- Verify `APP_URL=https://aery.eu.ngrok.io` in .env (not http://mail.loc)
- Verify `SESSION_SECURE_COOKIE=false` in .env (CRITICAL - app runs on HTTP at mail.loc)
- Verify trusted proxy configuration is in place (see Step 5)
- Run `composer install` to ensure Microsoft Graph SDK is installed
- Restart the application: `docker-compose restart app`
- Clear caches: `docker-compose exec app php artisan config:clear && php artisan cache:clear`

### Issue: "CSRF token mismatch" after callback

**Cause:** Session cookies not being maintained through the OAuth flow

**Solution:**
- Verify `SESSION_SAME_SITE=none` in .env
- Check browser is not blocking third-party cookies
- Clear browser cookies and try again
- Check that `SANCTUM_STATEFUL_DOMAINS` includes all relevant domains
- Ensure trusted proxy configuration is correct (see "Invalid state parameter" solution above)

### Issue: Application loads at aery.eu.ngrok.io but OAuth fails

**Cause:** Application is receiving requests with aery.eu.ngrok.io host header

**Solution:**
- Use `--host-header=rewrite` flag in ngrok command
- This makes the application think requests are coming to mail.loc
- Restart ngrok with: `ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80`

### Issue: "Invalid client secret" error

**Cause:** Client secret in .env doesn't match Azure

**Solution:**
- Verify `OFFICE365_CLIENT_SECRET` is correct (no extra spaces)
- If lost, generate a new secret in Azure and update .env
- Restart app after updating: `docker-compose restart app`

## Keeping ngrok Running

For continuous development:
1. Run ngrok in a separate terminal window/tab
2. Or use a terminal multiplexer like tmux/screen
3. Or run ngrok as a background service
4. ngrok will automatically reconnect if connection drops

## Security Notes

- The ngrok tunnel exposes your local application to the internet
- Only use for development/testing, not production
- Keep the tunnel URL private (don't share publicly)
- Monitor ngrok dashboard for unexpected traffic
- Stop the tunnel when not needed: Ctrl+C in ngrok terminal

## Alternative: ngrok Configuration File

Create `~/.ngrok2/ngrok.yml`:

```yaml
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

## OAuth Flow Diagram

```
┌──────────┐
│  User    │
└────┬─────┘
     │ 1. Visit http://mail.loc
     │    (NOT localhost:5173)
     ▼
┌──────────────────────┐
│  Frontend (Vite)     │
│  localhost:5173      │
└──────┬───────────────┘
       │ 2. Click "Sign in with Microsoft"
       │    GET http://mail.loc/auth/microsoft
       ▼
┌──────────────────────────────────────────────┐
│  Backend (Laravel)                           │
│  http://mail.loc                             │
│  - Generate OAuth URL                        │
│  - redirect_uri=https://aery.eu.ngrok.io/...  │
└──────┬───────────────────────────────────────┘
       │ 3. Redirect to Microsoft
       ▼
┌──────────────────────────────────────────────┐
│  Microsoft OAuth                             │
│  login.microsoftonline.com                   │
│  - User authenticates                        │
│  - User consents to permissions              │
└──────┬───────────────────────────────────────┘
       │ 4. Redirect to callback
       │    https://aery.eu.ngrok.io/auth/microsoft/callback?code=xxx
       ▼
┌──────────────────────────────────────────────┐
│  ngrok Tunnel                                │
│  aery.eu.ngrok.io                              │
│  - Forwards HTTPS → HTTP                     │
│  - Rewrites host header to mail.loc          │
└──────┬───────────────────────────────────────┘
       │ 5. Forward to local backend
       │    http://mail.loc/auth/microsoft/callback?code=xxx
       ▼
┌──────────────────────────────────────────────┐
│  Backend (Laravel)                           │
│  - Exchange code for tokens                  │
│  - Get user profile from Graph API           │
│  - Create/update User & Office365Connection  │
│  - Login user                                │
└──────┬───────────────────────────────────────┘
       │ 6. Redirect to dashboard
       │    http://mail.loc/#/dashboard
       ▼
┌──────────────────────┐
│  Frontend (Vite)     │
│  - Fetch /api/user   │
│  - Show dashboard    │
└──────────────────────┘
```

## Summary

### Local Development (with ngrok for OAuth)

**What changes:**
- Azure App Registration: Add `https://aery.eu.ngrok.io/auth/microsoft/callback` as redirect URI
- .env: Set `APP_URL=https://aery.eu.ngrok.io` (CRITICAL - not http://mail.loc)
- .env: Set `OFFICE365_REDIRECT_URI=https://aery.eu.ngrok.io/auth/microsoft/callback`
- .env: Set `SESSION_SECURE_COOKIE=false` (CRITICAL - app runs on HTTP at mail.loc)
- .env: Add ngrok domain to `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS`
- Run: `composer install` to ensure dependencies are installed

### Production Deployment (full HTTPS stack)

When deploying to production with a proper HTTPS domain:

**What changes:**
- .env: Set `APP_URL=https://mail.example.com` (your production domain)
- .env: Set `OFFICE365_REDIRECT_URI=https://mail.example.com/auth/microsoft/callback`
- .env: Set `SESSION_SECURE_COOKIE=true` (REQUIRED - entire stack is HTTPS)
- .env: Update `SANCTUM_STATEFUL_DOMAINS` and `CORS_ALLOWED_ORIGINS` with production domain
- Azure App Registration: Add production callback URI

**What stays the same:**
- Application continues to run at http://mail.loc internally
- Frontend continues to run at http://localhost:5173
- No code changes needed (after updating to Graph SDK v2.x)
- Traefik and Docker configuration unchanged

**How it works:**
- ngrok is a tunnel/proxy layer, not a deployment target
- Application thinks it's running over HTTPS (via APP_URL setting)
- ngrok forwards external HTTPS requests to local HTTP
- Session cookies have Secure flag, work properly through HTTPS
- Only used for OAuth callback - all other traffic is local

## Webhook Testing

This guide covers ngrok setup for **OAuth authentication only**. If you need to test **Microsoft Graph webhooks** (change notifications for incoming emails), see:

📖 **[docs/WEBHOOK_LOCAL_TESTING.md](docs/WEBHOOK_LOCAL_TESTING.md)**

### Quick Comparison

| Feature | OAuth (this guide) | Webhooks |
|---------|-------------------|----------|
| Purpose | User authentication | Real-time email notifications |
| ngrok usage | Callback URL | Notification endpoint |
| Configuration | APP_URL, OFFICE365_REDIRECT_URI | WEBHOOK_BASE_URL |
| Can use same tunnel? | Yes ✓ | Yes ✓ |

### Using ngrok for Both

Single tunnel for both OAuth and webhooks:

```bash
ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80
```

Configure both in `.env`:
```env
# OAuth
APP_URL=https://aery.eu.ngrok.io
OFFICE365_REDIRECT_URI=https://aery.eu.ngrok.io/auth/microsoft/callback

# Webhooks
WEBHOOK_BASE_URL=https://aery.eu.ngrok.io
```

**Different endpoints on same tunnel:**
- OAuth: `https://aery.eu.ngrok.io/auth/microsoft/callback`
- Webhooks: `https://aery.eu.ngrok.io/webhooks/microsoft/notifications`

**Restart application after configuring both:**
```bash
docker-compose restart app
docker-compose exec app php artisan config:clear
```

**For detailed webhook testing instructions**, including queue setup, subscription creation, and troubleshooting, see [docs/WEBHOOK_LOCAL_TESTING.md](docs/WEBHOOK_LOCAL_TESTING.md).
