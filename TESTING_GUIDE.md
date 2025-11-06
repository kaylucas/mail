# Authentication Persistence - Testing Guide

## Quick Testing Steps

### Test 1: Basic Login and Refresh (PRIMARY TEST)

1. **Clear existing auth:**
   ```bash
   # Open browser DevTools → Console
   localStorage.clear()
   ```

2. **Login:**
   - Navigate to `http://localhost:5173`
   - Click "Sign in with Microsoft"
   - Complete OAuth flow
   - Should redirect to dashboard

3. **Verify authentication:**
   - Check browser DevTools → Application → Local Storage
   - Should see `auth_token` with a Bearer token value

4. **Test refresh (THE FIX):**
   - Press `F5` or `Cmd+R` to refresh page
   - **Expected**: Should STAY on dashboard (logged in)
   - **Previous bug**: Would redirect to login

5. **Test navigation:**
   - Navigate to "Emails" page
   - Navigate back to "Dashboard"
   - **Expected**: Should stay logged in, fast navigation

### Test 2: Token Expiration

1. **Simulate expired token:**
   ```bash
   # In browser DevTools → Console
   localStorage.setItem('auth_token', 'invalid_token_123')
   location.reload()
   ```

2. **Expected behavior:**
   - Page loads
   - Router validates token
   - API returns 401
   - Token cleared from localStorage
   - Redirected to login page with error message

### Test 3: Logout

1. **Login** (follow Test 1 steps 1-2)

2. **Logout:**
   - Click "Logout" button in dashboard header
   - **Expected**: Should redirect to login page

3. **Verify cleanup:**
   - Check DevTools → Application → Local Storage
   - `auth_token` should be removed

4. **Test refresh after logout:**
   - Press `F5` to refresh
   - **Expected**: Should stay on login page

### Test 4: Multiple Navigations (Performance Test)

1. **Login** (follow Test 1 steps 1-2)

2. **Open DevTools Network tab:**
   - Filter: `user` (to see `/api/user` calls)
   - Clear existing requests

3. **Navigate rapidly:**
   - Dashboard → Emails → Dashboard → Emails (repeat 5 times)

4. **Check network requests:**
   - **Expected**: Should see ZERO `/api/user` requests
   - **Previous bug**: Would see 10+ requests

5. **Refresh page:**
   - Press `F5`
   - Navigate: Dashboard → Emails → Dashboard

6. **Check network again:**
   - **Expected**: Should see only 1 `/api/user` request (on refresh)
   - Subsequent navigations should make zero requests

### Test 5: OAuth Callback

1. **Clear existing auth:**
   ```bash
   localStorage.clear()
   ```

2. **Start OAuth flow:**
   - Navigate to `http://localhost:5173`
   - Click "Sign in with Microsoft"

3. **Watch network tab during callback:**
   - Should see 1 request to `/api/user` (token validation)
   - Then redirects to dashboard

4. **Verify no duplicate requests:**
   - Router should use cached validation result
   - Dashboard should load immediately without extra API call

## Expected Results Summary

| Test | Before Fix | After Fix |
|------|-----------|-----------|
| Login → Refresh | ❌ Logged out | ✅ Stays logged in |
| Login → Navigate → Refresh | ❌ Logged out | ✅ Stays logged in |
| API calls per navigation | 1+ | 0 (cached) |
| API calls on refresh | 1 | 1 (revalidates) |
| Invalid token | ✅ Clears and redirects | ✅ Clears and redirects |
| Logout | ✅ Works | ✅ Works |

## Debug Commands

### Check Token in Browser Console

```javascript
// Check if token exists
console.log('Token:', localStorage.getItem('auth_token'))

// Check axios header
console.log('Axios header:', axios.defaults.headers.common['Authorization'])

// Manually validate token
fetch('/api/user', {
  headers: {
    'Authorization': `Bearer ${localStorage.getItem('auth_token')}`
  }
})
  .then(r => r.json())
  .then(console.log)
  .catch(console.error)
```

### Check Backend Logs

```bash
# Terminal 1: Watch Laravel logs
cd /Users/kaylucas/Projects/mail
php artisan pail

# Should see:
# - POST /api/logout → when user logs out
# - GET /api/user → only on page refresh (not on every navigation)
```

### Clear Everything and Start Fresh

```javascript
// In browser console
localStorage.clear()
sessionStorage.clear()
location.href = '/'
```

## Common Issues

### Issue: Still getting logged out on refresh

**Diagnosis:**
1. Check DevTools → Console for errors
2. Check DevTools → Network tab for failed `/api/user` requests
3. Check token exists: `localStorage.getItem('auth_token')`

**Solutions:**
- Make sure frontend dev server is running: `cd frontend && pnpm dev`
- Make sure backend is running: `docker-compose up` or `php artisan serve`
- Clear browser cache and cookies
- Try in incognito mode

### Issue: Token invalid error

**Diagnosis:**
- Backend rejecting token
- Token expired

**Solutions:**
- Re-login via Microsoft OAuth
- Check backend is running at `http://mail.loc`
- Check `.env` has correct `SANCTUM_STATEFUL_DOMAINS`

### Issue: CORS errors

**Diagnosis:**
- Backend not configured for frontend origin

**Solutions:**
- Check `config/cors.php` has `http://localhost:5173` in `allowed_origins`
- Check `.env` has `CORS_ALLOWED_ORIGINS=http://localhost:5173`
- Restart backend after changing config

## Success Criteria

All tests pass when:

✅ **Primary Fix**: User stays logged in after page refresh
✅ **Performance**: Zero API calls on navigation (except first load)
✅ **Security**: Invalid tokens are detected and cleared
✅ **UX**: Fast navigation, no loading spinners between pages
✅ **Logout**: Works correctly and clears all state

## Manual Verification Checklist

- [ ] Login works via Microsoft OAuth
- [ ] Dashboard loads with user data
- [ ] **Refresh page → stays logged in** (PRIMARY TEST)
- [ ] Navigation between pages is instant
- [ ] Network tab shows only 1 `/api/user` call on refresh
- [ ] Network tab shows 0 `/api/user` calls on navigation
- [ ] Logout clears token and redirects to login
- [ ] Invalid token redirects to login with error
- [ ] Close tab → reopen → stays logged in (if token still valid)

## Automated Testing (Future)

```bash
# TODO: Add E2E tests with Playwright/Cypress
cd frontend
pnpm test:e2e  # Not implemented yet

# Test cases to automate:
# - Login flow
# - Refresh persistence
# - Token expiration handling
# - Logout flow
```

---

**Ready to Test**: The authentication persistence fix is ready for testing. Follow Test 1 first to verify the primary fix.
