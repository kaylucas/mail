# CSRF Error Fix - Verification Guide

## Root Cause Summary

**Primary Issue:** `Dashboard.vue` was importing the raw `axios` package instead of the configured axios instance, resulting in API requests being sent **WITHOUT** the Bearer token.

**Secondary Issue:** Axios configuration did not explicitly disable CSRF-related features (`withCredentials`, `withXSRFToken`).

---

## Fixes Applied

### Fix #1: Corrected Dashboard.vue Axios Import
**File:** `/Users/kaylucas/Projects/mail/frontend/src/pages/Dashboard.vue`  
**Line:** 4

**Before:**
```javascript
import axios from 'axios'  // ❌ WRONG - unconfigured axios
```

**After:**
```javascript
import axios from '../axios'  // ✅ CORRECT - configured axios with Bearer token
```

### Fix #2: Enhanced Axios Configuration
**File:** `/Users/kaylucas/Projects/mail/frontend/src/axios.js`

**Added:**
1. Explicit CSRF protection disabling (lines 15-16):
   ```javascript
   axios.defaults.withCredentials = false
   axios.defaults.withXSRFToken = false
   ```

2. Request interceptor to always use fresh token from localStorage (lines 25-34):
   ```javascript
   axios.interceptors.request.use(
       config => {
           const currentToken = localStorage.getItem('auth_token')
           if (currentToken) {
               config.headers.Authorization = `Bearer ${currentToken}`
           }
           return config
       },
       error => Promise.reject(error)
   )
   ```

---

## Verification Steps

### Step 1: Verify Code Changes

```bash
# Check Dashboard.vue uses correct axios import
grep "import axios" /Users/kaylucas/Projects/mail/frontend/src/pages/Dashboard.vue
# Expected output: import axios from '../axios'

# Check axios.js has CSRF protection disabled
grep "withCredentials\|withXSRFToken" /Users/kaylucas/Projects/mail/frontend/src/axios.js
# Expected output:
# axios.defaults.withCredentials = false
# axios.defaults.withXSRFToken = false

# Verify all Vue components use correct axios import
grep -r "import axios from 'axios'" /Users/kaylucas/Projects/mail/frontend/src/ --include="*.vue"
# Expected output: NONE (should return empty)
```

### Step 2: Rebuild Frontend

```bash
cd /Users/kaylucas/Projects/mail/frontend
pnpm install  # Ensure dependencies are fresh
pnpm dev      # Start dev server
```

### Step 3: Test in Browser

1. **Open the application:** `http://localhost:5173`
2. **Login via Microsoft SSO** (if not already logged in)
3. **Open Browser DevTools (F12)** → Network tab
4. **Navigate to Dashboard** (`http://localhost:5173/#/dashboard`)
5. **Check network requests for `/api/user`:**
   - ✅ **Request Headers MUST include:** `Authorization: Bearer {token}`
   - ✅ **Request Headers MUST include:** `X-Requested-With: XMLHttpRequest`
   - ❌ **Request Headers MUST NOT include:** `Cookie: XSRF-TOKEN=...`
   - ❌ **Request Headers MUST NOT include:** `X-XSRF-TOKEN: ...`
   - ✅ **Response Status:** `200 OK` (NOT 419 CSRF Token Mismatch)

6. **Navigate to Settings** (`http://localhost:5173/#/settings`)
7. **Click "Sync Emails Now"**
8. **Check network request for `/api/emails/sync/initial`:**
   - ✅ **Request Headers:** `Authorization: Bearer {token}`
   - ✅ **Response Status:** `200 OK` or `429 Too Many Requests` (NOT 419)

### Step 4: Use Debug Tool (Optional)

```bash
# Open debug tool in browser
open http://localhost:5173/debug-auth.html
```

Click through the steps to verify:
1. Token exists in localStorage
2. API requests succeed with Bearer token
3. Headers are correctly configured

---

## Expected Results

### ✅ Success Indicators

1. **All API requests include Bearer token:**
   ```
   Authorization: Bearer 1|abc123def456...
   ```

2. **No CSRF token headers sent:**
   - No `Cookie: XSRF-TOKEN=...`
   - No `X-XSRF-TOKEN: ...`

3. **All API responses return 200 OK** (or other valid status codes like 429 for rate limiting)

4. **No 419 CSRF Token Mismatch errors**

5. **Settings page "Sync Emails Now" button works without errors**

### ❌ Failure Indicators

1. **419 CSRF Token Mismatch** - Means Bearer token is still not being sent
2. **401 Unauthorized** - Token is invalid or expired (re-authenticate)
3. **Missing Authorization header** - Axios configuration not loaded
4. **Cookie headers present** - CSRF protection not properly disabled

---

## Troubleshooting

### Issue: Still Getting 419 CSRF Errors

**Possible Causes:**
1. Browser cache - Hard refresh with `Ctrl+Shift+R` (Windows/Linux) or `Cmd+Shift+R` (Mac)
2. Vite dev server not restarted - Kill and restart `pnpm dev`
3. Token expired - Re-authenticate via Microsoft SSO
4. Token not in localStorage - Check browser console: `localStorage.getItem('auth_token')`

**Solution:**
```bash
# 1. Kill Vite dev server (Ctrl+C)
# 2. Clear browser cache and localStorage
# 3. Restart Vite
cd /Users/kaylucas/Projects/mail/frontend
pnpm dev
# 4. Re-authenticate via Microsoft SSO
```

### Issue: 401 Unauthorized

**Cause:** Token is expired or invalid.

**Solution:**
1. Go to `http://localhost:5173/#/`
2. Click "Sign in with Microsoft"
3. Complete OAuth flow
4. New token will be stored in localStorage

### Issue: Authorization Header Missing

**Cause:** Component is importing wrong axios or token not in localStorage.

**Solution:**
1. Verify import: `import axios from '../axios'` (NOT `import axios from 'axios'`)
2. Check token exists: Open browser console, run `localStorage.getItem('auth_token')`
3. Re-authenticate if token is missing

---

## Backend Configuration (Already Correct)

No backend changes needed. Backend is already configured correctly:

**File:** `/Users/kaylucas/Projects/mail/bootstrap/app.php` (Lines 18-23)
```php
$middleware->validateCsrfTokens(except: [
    'api/*',  // ✅ API routes excluded from CSRF validation
]);
```

**File:** `/Users/kaylucas/Projects/mail/config/cors.php`
```php
'supports_credentials' => false,  // ✅ CORS credentials disabled (required for token auth)
'allowed_origins' => ['http://localhost:5173'],  // ✅ Frontend origin allowed
```

---

## Summary

The CSRF errors were caused by **Dashboard.vue** using an unconfigured axios instance without Bearer tokens. All other components were working correctly. The fix ensures:

1. ✅ All components use the configured axios instance
2. ✅ All API requests include `Authorization: Bearer {token}`
3. ✅ CSRF protection is explicitly disabled for token-based auth
4. ✅ Tokens are refreshed from localStorage on every request
5. ✅ No cookies or CSRF tokens are sent to `/api/*` routes

**Impact:** Zero downtime, no database changes, no backend changes required.

**Testing:** Frontend-only testing required. Verify in browser DevTools that Authorization headers are present.
