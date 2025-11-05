# Runtime Fixes Applied

This document describes additional fixes applied during testing that weren't part of the original 9 verification comments.

## Issue 1: Missing Config Files in Dockerfile

**Error:**
```
failed to compute cache key: "/postcss.config.js": not found
```

**Root Cause:**
The project uses Tailwind CSS 4 via the `@tailwindcss/vite` plugin, which doesn't require separate `tailwind.config.js` or `postcss.config.js` files. Configuration is embedded in `vite.config.js`.

**Fix:**
Updated `docker/app/Dockerfile` stage 1 (node-builder):
```dockerfile
# Before
COPY vite.config.js tailwind.config.js postcss.config.js ./

# After
COPY vite.config.js ./
```

**Files Modified:**
- `docker/app/Dockerfile`

---

## Issue 2: Missing oniguruma Library for mbstring

**Error:**
```
configure: error: Package requirements (oniguruma) were not met:
Package 'oniguruma', required by 'virtual:world', not found
```

**Root Cause:**
The `mbstring` PHP extension requires the oniguruma library for multibyte regex support. The library wasn't installed in the system dependencies.

**Fix:**
Added `libonig-dev` to all three Dockerfiles:
```dockerfile
RUN apt-get update && apt-get install -y \
    ...
    libonig-dev \
    ...
```

**Files Modified:**
- `docker/app/Dockerfile`
- `docker/app/Dockerfile.dev`
- `docker/ci/Dockerfile`

**Impact:**
All Dockerfiles now include the complete set of dependencies for all PHP extensions.

---

## Issue 3: MariaDB Environment Variable Configuration

**Error:**
```
ERROR: Database is uninitialized and password option is not specified
You need to specify one of MARIADB_ROOT_PASSWORD, ...
```

**Root Cause:**
The MariaDB Docker image expects specific environment variable names:
- `MYSQL_ROOT_PASSWORD` - for the root user password
- `MYSQL_DATABASE` - for the database name
- `MYSQL_USER` / `MYSQL_PASSWORD` - for creating a custom (non-root) user

The original configuration tried to create a user named "root" using `MYSQL_USER`, which conflicts with the built-in root user.

**Fix:**
Updated `docker-compose.yml` to use only root user:
```yaml
# Before
environment:
  MYSQL_DATABASE: ${DB_DATABASE}
  MYSQL_USER: ${DB_USERNAME}
  MYSQL_PASSWORD: ${DB_PASSWORD}
  MYSQL_ROOT_PASSWORD: ${DB_PASSWORD}

# After
environment:
  MYSQL_DATABASE: ${DB_DATABASE:-mail}
  MYSQL_ROOT_PASSWORD: ${DB_PASSWORD:-password}
```

**Files Modified:**
- `docker-compose.yml`
- `.env` - Set `DB_PASSWORD=password` (was empty)

**Configuration:**
The application now connects as `root` user to MariaDB. For production, consider creating a dedicated user with limited privileges.

---

## Issue 4: Empty Database Password in .env

**Error:**
MariaDB failed to initialize due to missing password.

**Root Cause:**
The `.env` file had `DB_PASSWORD=` (empty value).

**Fix:**
Set a default password in `.env`:
```env
DB_PASSWORD=password
```

**Files Modified:**
- `.env`

**Security Note:**
This is suitable for local development only. Production deployments should use strong, unique passwords.

---

## Issue 5: Port 80 Already Allocated

**Error:**
```
Bind for 0.0.0.0:80 failed: port is already allocated
```

**Root Cause:**
Port 80 (HTTP) was already in use by another service on the host machine.

**Fix:**
Used the `APP_PORT` environment variable to run on a different port:
```bash
APP_PORT=8888 docker-compose up -d
```

**Documentation:**
Updated `DOCKER.md` to mention:
- Default port is 80
- Can be changed via `APP_PORT` environment variable
- Common alternatives: 8080, 8888

**No Files Modified:**
The functionality was already supported via `${APP_PORT:-80}` in `docker-compose.yml`.

---

## Verification Results

After all fixes, the Docker setup is fully functional:

### ✅ Production Build
```bash
docker-compose build
# ✓ Build completes successfully
# ✓ Multi-stage build works (Node in stage 1 only)
# ✓ All PHP extensions compile correctly
# ✓ Frontend assets built and copied to final image
```

### ✅ Container Startup
```bash
docker-compose up -d
# ✓ MariaDB starts and passes health check
# ✓ App container starts successfully
# ✓ Apache runs and serves requests
```

### ✅ Multi-Stage Build Verification
```bash
docker-compose exec app which node
# ✓ Node not found (as expected - not in production image)

docker-compose exec app ls public/build/
# ✓ Built assets present (assets/ and manifest.json)
```

### ✅ Container Status
```
NAME             STATUS
mail-mariadb-1   Up (healthy)
mail-app-1       Up
```

---

## Summary of All Changes

### Original 9 Verification Comments: ✅ All Implemented
1. Fixed volume bind-mounts
2. Fixed pnpm-lock.yaml exclusion
3. Added $PHPIZE_DEPS
4. Added GD extension dependencies
5. Populated CI workflow
6. Removed Traefik from defaults
7. Fixed Apache config sed
8. Multi-stage build implementation
9. Verified PHP 8.4 compatibility

### Additional Runtime Fixes: ✅ All Applied
10. Removed non-existent config files from Dockerfile
11. Added libonig-dev for mbstring
12. Fixed MariaDB environment variables
13. Set database password in .env
14. Documented port configuration

---

## Testing Checklist

- [x] Docker build completes without errors
- [x] Node/pnpm not present in production image
- [x] Built assets present in public/build
- [x] MariaDB starts and is healthy
- [x] App container starts successfully
- [x] Apache serves requests
- [x] All PHP extensions loaded
- [x] Database connection works

---

## Files Modified in Runtime Fixes

1. `docker/app/Dockerfile` - Removed postcss.config.js/tailwind.config.js, added libonig-dev
2. `docker/app/Dockerfile.dev` - Added libonig-dev
3. `docker/ci/Dockerfile` - Added libonig-dev
4. `docker-compose.yml` - Fixed MariaDB environment variables
5. `.env` - Set DB_PASSWORD
6. `DOCKER.md` - Added environment variable documentation and notes

---

## Recommendations

1. **For Production:**
   - Change `DB_PASSWORD` to a strong, unique password
   - Consider creating a dedicated database user (not root)
   - Use `APP_ENV=production` and `APP_DEBUG=false`
   - Enable HTTPS via reverse proxy

2. **For Development:**
   - Use `docker-compose.dev.yml` for hot-reload
   - Monitor port conflicts (80, 8080, 8888)
   - Keep `.env` out of version control

3. **For CI/CD:**
   - GitHub Actions workflow is ready to use
   - Ensure secrets are configured for production deployments

---

## Session Cookie Authentication Fix (2025-10-28)

### Issue: "Unauthenticated" after successful OAuth login

**Problem**:
After successfully completing Microsoft OAuth authentication, users were redirected to the dashboard but received `{"message":"Unauthenticated."}` errors when the frontend called `/api/user`. The logs showed:
- OAuth redirect initiated ✓
- State validation successful ✓
- User authenticated successfully ✓
- Session established on local domain ✓
- But API calls returned 401 Unauthenticated ✗

**Root Cause**:
The `.env.example` had `SESSION_SECURE_COOKIE=true`, which marks session cookies with the `Secure` flag. Browsers only send cookies marked `Secure` over HTTPS connections. The application flow:

1. OAuth callback happens over HTTPS (ngrok) → session created ✓
2. User redirected to `http://mail.loc/#/dashboard` (HTTP) → session cookie set with `Secure` flag ✓
3. Frontend makes API call to `/api/user` at `http://mail.loc` (HTTP)
4. Browser checks cookie: has `Secure` flag, connection is HTTP
5. Browser refuses to send the cookie
6. Backend receives request without session cookie → "Unauthenticated"

**Additional Confusion**:
The documentation incorrectly suggested accessing the application at `http://localhost:5173` (Vite dev server). This created additional session inconsistencies:
- Accessing via `localhost:5173` creates a session with domain `localhost`
- Accessing via `mail.loc` creates a session with domain `mail.loc`
- These are different sessions with different cookies
- Users were being redirected between the two, losing authentication state

**Correct Architecture**:
- **Application access**: Always `http://mail.loc` (Laravel serves both backend and frontend)
- **Vite dev server**: `http://localhost:5173` (for hot-reload only, not for user access)
- **OAuth callback**: `https://aery.eu.ngrok.io/auth/microsoft/callback` (HTTPS via ngrok)

**Solution**:

1. **Changed `SESSION_SECURE_COOKIE` to `false`** in `.env.example`:
   - Allows session cookies to be transmitted over HTTP
   - Required because the application runs at `http://mail.loc` (HTTP)
   - Safe for local development (traffic is local only)
   - OAuth tokens still exchanged over HTTPS (ngrok)

2. **Updated documentation** to clarify correct access URL:
   - Always access at `http://mail.loc`, never `localhost:5173`
   - Vite dev server is for hot-reload only
   - Single domain = single session = no cookie conflicts

3. **No code changes needed**:
   - `MicrosoftAuthController` already redirects to `http://mail.loc/#/dashboard` (correct)
   - No need for `FRONTEND_URL` variable (frontend served by Laravel at same domain)
   - Existing session handling logic is correct

**Files Modified**:
- `.env.example` - Changed `SESSION_SECURE_COOKIE=true` to `false`, updated comments
- `NGROK_SETUP.md` - Clarified correct access URL and session cookie configuration
- `README.md` - Updated to emphasize `http://mail.loc` as the only access URL
- `RUNTIME_FIXES.md` - This documentation

**Testing**:
1. Set environment variables:
   ```env
   APP_URL=https://aery.eu.ngrok.io
   SESSION_SECURE_COOKIE=false
   SESSION_SAME_SITE=none
   SESSION_DOMAIN=null
   ```

2. Restart application: `docker-compose restart app`

3. Clear browser cookies

4. Start ngrok: `ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80`

5. Start Vite dev server: `pnpm dev` (for hot-reload, not for access)

6. Test OAuth flow:
   - Visit `http://mail.loc` (NOT localhost:5173)
   - Click "Sign in with Microsoft"
   - Complete OAuth authentication
   - Verify redirect to `http://mail.loc/#/dashboard`
   - Verify dashboard loads user data (no "Unauthenticated" error)
   - Check browser DevTools → Cookies: session cookie should NOT have `Secure` flag
   - Verify cookie domain is `mail.loc` or empty (current domain)

7. Verify API calls:
   - Open browser DevTools → Network tab
   - Observe `/api/user` request
   - Verify session cookie is sent in request headers
   - Verify response is 200 OK with user data

**Production Considerations**:
In production with full HTTPS:
- Set `SESSION_SECURE_COOKIE=true`
- Set `APP_URL` to production HTTPS URL
- Ensure entire stack uses HTTPS
- No need for ngrok or separate Vite dev server

**Key Takeaway**:
The issue was not about cross-domain authentication (localhost:5173 ↔ mail.loc), but about HTTP vs HTTPS cookie security. The application should always be accessed at a single domain (`http://mail.loc`) during local development, with `SESSION_SECURE_COOKIE=false` to allow HTTP cookie transmission.

**References**:
- MDN: Set-Cookie Secure flag: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#secure
- Laravel Sanctum SPA Authentication: https://laravel.com/docs/sanctum#spa-authentication
- SameSite cookie attribute: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie/SameSite

## Microsoft OAuth Fixes (2025-10-27)

### Issue 1: Class "Microsoft\Graph\Graph" not found

**Problem:**
The application was using Microsoft Graph SDK v1.x API syntax (`new Graph()`, `createRequest()`, `setReturnType()`) but `composer.json` specified v2.x (`"microsoft/microsoft-graph": "^2.49"`). The v2.x SDK has completely different classes and API structure.

**Root Cause:**
- Code in `app/Services/Office365Service.php` was written for v1.x API
- Composer dependency specified v2.x
- The `Graph` class doesn't exist in v2.x (replaced by `GraphServiceClient`)
- Dependencies may not have been installed (`vendor/` directory missing packages)

**Solution:**
1. Updated `Office365Service.php` to use v2.x API:
   - Replaced `new Graph()` with `GraphServiceClient::createWithAuthenticationProvider()`
   - Updated `getUserProfile()` to use `$graphServiceClient->me()->get()->wait()`
   - Updated `getUserPhoto()` to use `$graphServiceClient->me()->photo()->content()->get()->wait()`
   - Changed from `createRequest()` pattern to fluent builder pattern
   - Updated exception handling from generic `Exception` to `ApiException`

2. Ran `composer install` to ensure all dependencies are installed

**Files Modified:**
- `app/Services/Office365Service.php`

**References:**
- Microsoft Graph PHP SDK v2.x documentation: https://github.com/microsoftgraph/msgraph-sdk-php
- Migration guide: https://github.com/microsoftgraph/msgraph-sdk-php/blob/main/UPGRADING.md

### Issue 2: Invalid state parameter / Session not persisting through OAuth callback

**Problem:**
When users authenticated with Microsoft OAuth through ngrok, they received "Invalid state parameter" error. Logs showed the session ID changed between the redirect and callback, causing the cached OAuth state to be lost.

**Root Cause:**
- `.env` had `APP_URL=http://mail.loc` and `SESSION_SECURE_COOKIE=false`
- ngrok tunnel uses HTTPS: `https://aery.eu.ngrok.io`
- When Laravel thinks it's running over HTTP but requests come through HTTPS:
  - Session cookies are created without the `Secure` flag
  - Browsers won't send non-secure cookies over HTTPS connections
  - OAuth callback creates a new session, losing the state parameter
  - State validation fails because the state was stored in the original session

**Solution:**
1. Updated `.env.example` and `NGROK_SETUP.md` to specify:
   ```env
   APP_URL=https://aery.eu.ngrok.io
   SESSION_SECURE_COOKIE=true
   SANCTUM_STATEFUL_DOMAINS=localhost:5173,mail.loc,localhost,127.0.0.1,aery.eu.ngrok.io
   CORS_ALLOWED_ORIGINS=http://localhost:5173,https://aery.eu.ngrok.io
   ```

2. Added clear documentation explaining:
   - Why `APP_URL` must be the ngrok HTTPS URL (not just the redirect URI)
   - Why `SESSION_SECURE_COOKIE=true` is required for HTTPS
   - How to switch between ngrok mode and local development mode

3. Updated troubleshooting documentation in `README.md` and `NGROK_SETUP.md`

**Files Modified:**
- `.env.example`
- `NGROK_SETUP.md`
- `README.md`
- `RUNTIME_FIXES.md` (this file)

**Why TrustProxies wasn't enough:**
While `TrustProxies` middleware was correctly configured to trust ngrok's forwarded headers, Laravel still needs `APP_URL` to be HTTPS and `SESSION_SECURE_COOKIE=true` to properly handle secure cookies. The middleware trusts the headers, but the application configuration determines cookie behavior.

**Testing:**
1. Set environment variables as documented
2. Run `composer install`
3. Restart application: `docker-compose restart app`
4. Clear caches: `docker-compose exec app php artisan config:clear && php artisan cache:clear`
5. Start ngrok: `ngrok http --domain=aery.eu.ngrok.io --host-header=rewrite mail.loc:80`
6. Test OAuth flow: Visit http://localhost:5173, click "Sign in with Microsoft"
7. Verify session cookies have `Secure; SameSite=None` flags in browser DevTools
8. Confirm successful authentication and redirect to dashboard

**References:**
- Laravel session configuration: https://laravel.com/docs/session
- SameSite cookie requirements: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie/SameSite
- ngrok with Laravel: https://ngrok.com/docs/guides/frameworks/laravel/
