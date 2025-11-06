# CSRF Token Mismatch - Final Fix (2025-11-06)

## Problem Summary

Bearer token authenticated requests to API endpoints were returning "CSRF token mismatch" errors despite having valid authentication tokens.

### Error Symptoms

```bash
curl 'http://mail.loc/api/emails/sync/initial' \
  -X 'POST' \
  -H 'Authorization: Bearer {token}' \
  -H 'X-Requested-With: XMLHttpRequest'
```

**Response:**
```json
{
    "message": "CSRF token mismatch.",
    "exception": "Symfony\\Component\\HttpKernel\\Exception\\HttpException"
}
```

**Stack Trace:**
```
Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::handle
Illuminate\Session\Middleware\StartSession::handle
```

## Root Cause

The `statefulApi()` middleware configuration in [bootstrap/app.php:16](../bootstrap/app.php#L16) was enabling Sanctum's `EnsureFrontendRequestsAreStateful` middleware. This middleware:

1. Checks if the request origin matches domains in `SANCTUM_STATEFUL_DOMAINS`
2. If matched, applies session-based authentication pipeline (cookies + CSRF)
3. CSRF validation runs BEFORE Bearer token authentication is checked
4. Requests fail because no CSRF token is present (correctly, for token auth)

### Configuration Conflict

The application was mixing two mutually exclusive authentication patterns:
- **Stateful Authentication**: Cookie-based sessions with CSRF protection (via `statefulApi()`)
- **Token-Based Authentication**: Bearer tokens in Authorization header (no CSRF needed)

## Solution

Two configuration changes were required to fix the CSRF token mismatch:

1. **Removed `statefulApi()` from [bootstrap/app.php](../bootstrap/app.php)**
2. **Disabled stateful domains in [config/sanctum.php](../config/sanctum.php)**

### Changes Made

#### File: `config/sanctum.php` (CRITICAL FIX)

**Before:**
```php
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost:5173,mail.loc,localhost,127.0.0.1')),
```

**After:**
```php
// DISABLED: This application uses pure token-based authentication (Bearer tokens)
// No stateful domains needed - empty array prevents EnsureFrontendRequestsAreStateful
// middleware from applying session-based CSRF validation.
'stateful' => [],
```

**Why this was needed:** Sanctum automatically applies `EnsureFrontendRequestsAreStateful` middleware when it detects requests from domains in the `stateful` array. This happens **even without `statefulApi()`** being called. By setting it to an empty array, we completely disable the stateful middleware.

#### File: `bootstrap/app.php`

**Before:**
```php
->withMiddleware(function (Middleware $middleware): void {
    $middleware->statefulApi();  // ❌ WRONG - enables session + CSRF

    $middleware->validateCsrfTokens(except: [
        'api/*',  // This runs too late in middleware pipeline
    ]);

    $middleware->trustProxies(at: '*');

    $middleware->api(append: [
        \App\Http\Middleware\RefreshOffice365Token::class,
    ]);
})
```

**After:**
```php
->withMiddleware(function (Middleware $middleware): void {
    // Token-based authentication using Bearer tokens (no statefulApi needed)
    // statefulApi() was removed because it enables session-based CSRF validation
    // which conflicts with pure Bearer token authentication

    $middleware->trustProxies(at: '*');

    $middleware->api(append: [
        \App\Http\Middleware\RefreshOffice365Token::class,
    ]);
})
```

### Why This Works

With `statefulApi()` removed:
1. `EnsureFrontendRequestsAreStateful` middleware is NOT applied to API routes
2. No session initialization or CSRF validation occurs
3. Sanctum checks for Bearer token in `Authorization` header
4. Token authentication succeeds without CSRF interference

## Testing & Verification

### Test 1: Email Sync Endpoint

```bash
curl 'http://mail.loc/api/emails/sync/initial' \
  -X 'POST' \
  -H 'Authorization: Bearer 2|rNyMxidMICnazPMsWR77PJkBCcOvGZW6bTBxxxWl2f6a9aa3' \
  -H 'X-Requested-With: XMLHttpRequest' \
  -i
```

**Result:**
```
HTTP/1.1 202 Accepted ✅
Content-Type: application/json

{
  "message": "Initial email sync started",
  "job_id": 3,
  "status_url": "http://mail.loc/api/emails/sync/status/3"
}
```

### Test 2: User Profile Endpoint

```bash
curl 'http://mail.loc/api/user' \
  -H 'Authorization: Bearer {token}' \
  -s | jq .
```

**Result:** ✅ Returns authenticated user with Office365 connection data

### Test 3: Settings Page "Sync Now" Button

**Frontend:** `http://localhost:5173/settings`
**Action:** Click "Sync Emails Now"
**Result:** ✅ Sync initiates without CSRF errors

## Architecture Documentation Updates

Updated [CLAUDE.md](../CLAUDE.md) to reflect pure token-based authentication:

1. Changed description from "stateful SPA authentication with session-based CSRF protection" to "pure token-based API authentication with Bearer tokens"
2. Added note: "statefulApi() is NOT used in bootstrap/app.php to avoid mixing stateful and token-based auth"
3. Emphasized: "No cookies or CSRF protection needed"

## Key Takeaways

### ✅ Use Token-Based Auth When:
- Frontend is a separate SPA (not served by Laravel)
- API consumed by mobile apps or external clients
- Cross-origin requests required
- Microservices architecture

### ❌ Avoid `statefulApi()` When:
- Using Bearer tokens for authentication
- No cookie-based sessions needed
- CSRF protection is unnecessary (tokens are CSRF-safe)

### When to Use `statefulApi()`:
- Laravel serves both frontend and backend (blade templates)
- Cookie-based session authentication desired
- Frontend and backend share same domain
- Traditional SPA with Laravel backend

## Related Files

- [bootstrap/app.php](../bootstrap/app.php) - Middleware configuration (FIXED)
- [frontend/src/axios.js](../frontend/src/axios.js) - Bearer token configuration
- [config/sanctum.php](../config/sanctum.php) - Sanctum configuration
- [CLAUDE.md](../CLAUDE.md) - Project architecture documentation

## Commands Used

```bash
# Clear Laravel caches after middleware changes
php artisan config:clear && php artisan route:clear && php artisan cache:clear
```

## Status

✅ **RESOLVED** - All API endpoints accept Bearer token authentication without CSRF errors
