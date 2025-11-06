# CSRF Token Mismatch - Complete Resolution

## Issue Timeline

### Initial Problem
After implementing authentication fixes and Settings page, the "Sync Emails Now" button returned:
```json
{"message": "CSRF token mismatch."}
```

### First Fix Attempt ❌
**Action:** Added CSRF exclusion for `api/*` routes in bootstrap/app.php
**Result:** FAILED - Error persisted

### Second Fix Attempt ❌
**Action:** Removed `statefulApi()` from bootstrap/app.php
**Result:** FAILED - Error still occurred

### Final Fix ✅
**Action:** Set `'stateful' => []` in config/sanctum.php
**Result:** SUCCESS - All CSRF errors resolved

## Root Cause Analysis

Laravel Sanctum's `EnsureFrontendRequestsAreStateful` middleware was being applied because:

1. **Sanctum checks the `stateful` array** in config/sanctum.php
2. **If request origin matches** any domain in the stateful array, Sanctum applies:
   - `StartSession` middleware (initializes cookie sessions)
   - `EncryptCookies` middleware
   - `ValidateCsrfToken` middleware (checks for CSRF token)
3. **This happens automatically** - it doesn't require `statefulApi()` to be called
4. **CSRF validation runs BEFORE** Bearer token authentication is checked

### The Configuration Trap

```php
// config/sanctum.php (BEFORE)
'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', 'localhost:5173,mail.loc'))
```

The default value included `localhost:5173`, which is our frontend dev server. When the frontend made API requests, Sanctum detected the origin and automatically applied stateful middleware.

## Complete Solution

### File 1: `/Users/kaylucas/Projects/mail/config/sanctum.php`

```php
/*
 | DISABLED: This application uses pure token-based authentication (Bearer tokens)
 | No stateful domains needed - empty array prevents EnsureFrontendRequestsAreStateful
 | middleware from applying session-based CSRF validation.
 */
'stateful' => [],
```

### File 2: `/Users/kaylucas/Projects/mail/bootstrap/app.php`

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

## Verification Results

All API endpoints now work correctly with Bearer token authentication:

### Test 1: User Profile Endpoint
```bash
curl http://mail.loc/api/user \
  -H "Authorization: Bearer {token}"
```
**Result:** ✅ Returns user data with Office365 connection

### Test 2: Email Sync Endpoint
```bash
curl -X POST http://mail.loc/api/emails/sync/initial \
  -H "Authorization: Bearer {token}"
```
**Result:** ✅ HTTP 202 Accepted - Sync job started

### Test 3: Logout Endpoint
```bash
curl -X POST http://mail.loc/api/logout \
  -H "Authorization: Bearer {token}"
```
**Result:** ✅ HTTP 200 - Token revoked successfully

### Test 4: Settings Page "Sync Now" Button
**Location:** `http://localhost:5173/settings`
**Result:** ✅ No CSRF errors, sync starts successfully

## Why This Configuration Works

### Without Stateful Domains

1. Request from `http://localhost:5173` arrives at Laravel API
2. Sanctum checks `config('sanctum.stateful')` → finds empty array
3. `EnsureFrontendRequestsAreStateful` middleware is NOT applied
4. No session initialization or CSRF validation occurs
5. Sanctum checks for `Authorization: Bearer {token}` header
6. Token authentication succeeds
7. Request processed normally

### Middleware Pipeline (Correct)

```
Request → TrustProxies → HandleCors → ValidatePathEncoding
    → Sanctum (checks Bearer token) → RefreshOffice365Token
    → Controller
```

### Middleware Pipeline (Before Fix - Incorrect)

```
Request → TrustProxies → HandleCors → ValidatePathEncoding
    → EnsureFrontendRequestsAreStateful (triggered by stateful domains)
        → StartSession → EncryptCookies → ValidateCsrfToken ❌ FAILS HERE
```

## Key Architectural Concepts

### Stateful Authentication (Cookie-Based)
- Used when frontend and backend share the same domain
- Requires CSRF protection (tokens in cookies)
- Laravel serves both frontend (Blade) and API
- Example: Traditional Laravel app with session auth

### Token-Based Authentication (Bearer Tokens)
- Used when frontend is separate SPA or mobile app
- No CSRF protection needed (tokens in Authorization header)
- Cross-origin requests supported
- Example: Our application (Vue SPA + Laravel API)

### Why They're Mutually Exclusive

| Feature | Stateful (Cookies) | Token-Based (Bearer) |
|---------|-------------------|---------------------|
| Authentication | Session cookies | Authorization header |
| CSRF Protection | Required | Not needed |
| Cross-Origin | Limited | Full support |
| Frontend Location | Same domain | Any domain |
| Mobile Apps | Not supported | Fully supported |

## Configuration Reference

### Environment Variables
No changes needed - we intentionally avoid setting `SANCTUM_STATEFUL_DOMAINS` in .env

### Sanctum Configuration (config/sanctum.php)
```php
return [
    'stateful' => [],  // ✅ Empty array disables stateful middleware
    'guard' => ['web'],
    'expiration' => null,
    'middleware' => [
        'authenticate_session' => AuthenticateSession::class,
        'encrypt_cookies' => EncryptCookies::class,
        'validate_csrf_token' => ValidateCsrfToken::class,
    ],
];
```

### CORS Configuration (config/cors.php)
```php
'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173')),
```

### Frontend Axios Configuration (frontend/src/axios.js)
```javascript
// CRITICAL: Disable CSRF for token-based authentication
axios.defaults.withCredentials = false
axios.defaults.withXSRFToken = false

// Add Bearer token to all requests
const token = localStorage.getItem('auth_token')
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
}
```

## Documentation Updates

Updated the following files to reflect this architecture:

1. **[CLAUDE.md](../CLAUDE.md)** - Added warning about stateful domains
2. **[CSRF_FIX_FINAL.md](CSRF_FIX_FINAL.md)** - Complete root cause analysis
3. **[config/sanctum.php](../config/sanctum.php)** - Inline documentation

## Common Pitfalls to Avoid

### ❌ DON'T: Mix Stateful and Token Authentication
```php
// BAD - causes CSRF errors
'stateful' => ['localhost:5173'],  // ❌ Wrong for Bearer tokens
->withMiddleware(fn($m) => $m->statefulApi())  // ❌ Wrong
```

### ✅ DO: Use Pure Token Authentication
```php
// GOOD - pure token-based auth
'stateful' => [],  // ✅ Correct
->withMiddleware(fn($m) => $m->api(...))  // ✅ Correct
```

### ❌ DON'T: Enable CSRF for API Routes
```php
// BAD - token auth doesn't need CSRF
$middleware->validateCsrfTokens(except: ['api/*']);  // ❌ Unnecessary
```

### ✅ DO: Let Sanctum Handle Token Authentication
```php
// GOOD - Sanctum checks Bearer tokens automatically
// No explicit CSRF configuration needed
```

## Commands Used

```bash
# Clear configuration cache after changes
php artisan config:clear && php artisan cache:clear

# Verify routes are correct
php artisan route:list --path=api

# Test endpoint with Bearer token
curl http://mail.loc/api/user \
  -H "Authorization: Bearer {token}"
```

## Related Files

- [config/sanctum.php](../config/sanctum.php) - Sanctum configuration (stateful domains)
- [bootstrap/app.php](../bootstrap/app.php) - Middleware configuration
- [frontend/src/axios.js](../frontend/src/axios.js) - Axios Bearer token setup
- [frontend/src/pages/Settings.vue](../frontend/src/pages/Settings.vue) - Settings page with sync button
- [CLAUDE.md](../CLAUDE.md) - Architecture documentation

## Resolution Status

✅ **COMPLETELY RESOLVED**

- All API endpoints accept Bearer token authentication
- No CSRF errors on any route
- Settings page "Sync Now" button works correctly
- Frontend and backend communicate seamlessly
- Rate limiting working correctly
- CORS configured properly

## Date: 2025-11-06
## Author: Claude Code Agent
