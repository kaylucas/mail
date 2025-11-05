# EmailInbox Component - Quick Start Guide

## 5-Minute Setup

### 1. Verify Installation

All components are already created and installed. Verify the build works:

```bash
cd /Users/kaylucas/Projects/mail/frontend
pnpm run build
```

You should see:
```
✓ built in ~800ms
```

### 2. Start Development Server

```bash
# From the frontend directory
pnpm dev
```

Frontend will be available at: `http://localhost:5173`

### 3. Ensure Backend is Running

The component requires Laravel backend API endpoints. Start the backend:

```bash
# From project root in another terminal
cd /Users/kaylucas/Projects/mail

# Option 1: Docker (recommended)
docker-compose up

# Option 2: Laravel dev server
php artisan serve
```

Backend should be available at: `http://mail.loc`

### 4. Access the Email Inbox

Navigate to: **`http://localhost:5173/#/emails`**

Make sure you're authenticated first by logging in at `http://localhost:5173/`

## Using the Component

### Basic Integration

```vue
<!-- In any authenticated page -->
<template>
  <EmailInbox
    @email-selected="openEmailDetail"
    @mark-read="handleMarkRead"
  />
</template>

<script setup>
import EmailInbox from '@/components/EmailInbox.vue'

const openEmailDetail = (email) => {
  console.log('Selected email:', email)
  // Show modal, navigate to detail page, etc.
}

const handleMarkRead = (emailId, isRead) => {
  console.log(`Email ${emailId} marked as ${isRead ? 'read' : 'unread'}`)
}
</script>
```

### With Router

The component is already integrated at `/emails` route:

```javascript
// Already in router/index.js
{
  path: '/emails',
  name: 'Emails',
  component: EmailsPage,
  meta: { requiresAuth: true }
}
```

Navigate programmatically:
```javascript
import { useRouter } from 'vue-router'

const router = useRouter()
router.push('/emails')
```

Or with router-link:
```vue
<router-link to="/emails">View Emails</router-link>
```

## Required Backend API Endpoints

Create these endpoints in Laravel (or verify they exist):

### 1. List Emails
```php
// routes/api.php
Route::get('/emails', [EmailController::class, 'index'])->middleware('auth:sanctum');
```

Expected response:
```json
{
  "data": [
    {
      "id": 1,
      "subject": "Test Email",
      "from_name": "John Doe",
      "from_email": "john@example.com",
      "body_preview": "Email preview...",
      "received_date_time": "2025-01-05T10:30:00Z",
      "is_read": false,
      "has_attachments": false,
      "importance": "normal",
      "email_folder": {
        "id": 1,
        "display_name": "Inbox"
      }
    }
  ],
  "current_page": 1,
  "last_page": 5,
  "total": 234
}
```

### 2. Get Single Email
```php
Route::get('/emails/{id}', [EmailController::class, 'show'])->middleware('auth:sanctum');
```

### 3. Mark as Read/Unread
```php
Route::patch('/emails/{id}/read', [EmailController::class, 'updateReadStatus'])->middleware('auth:sanctum');
```

Expected request body:
```json
{
  "is_read": true
}
```

### 4. List Folders
```php
Route::get('/emails/folders', [EmailController::class, 'folders'])->middleware('auth:sanctum');
```

Expected response:
```json
{
  "data": [
    {
      "id": 1,
      "display_name": "Inbox",
      "unread_item_count": 5
    },
    {
      "id": 2,
      "display_name": "Sent Items",
      "unread_item_count": 0
    }
  ]
}
```

### 5. Email Stats (Optional)
```php
Route::get('/emails/stats', [EmailController::class, 'stats'])->middleware('auth:sanctum');
```

## Component Features

### Virtual Scrolling
- Handles 1000+ emails smoothly
- Only renders ~20-30 visible items
- Automatic height calculation

### Filters
- **Search**: Full-text search with 300ms debounce
- **Folder**: Filter by mail folder
- **Read Status**: All / Read / Unread
- **Attachments**: Show only emails with attachments
- **Clear Filters**: Reset all filters at once

### Sorting
- Sort by: Date Received (default), Date Sent, Subject
- Order: Ascending / Descending
- Click sort order button to toggle

### Actions
- **Click email**: Opens detail view (emits `email-selected` event)
- **Mark as read/unread**: Individual toggle button
- **Multi-select**: Check multiple emails, bulk mark as read/unread

### Loading States
- Skeleton loaders (not spinners)
- Inline loading for infinite scroll
- Error states with retry button

## Customization

### Adjust Item Height

If your emails render taller/shorter than expected:

```javascript
// In EmailInbox.vue, line ~180
const ITEM_HEIGHT = 120  // Change this value (in pixels)
```

Measure actual height:
1. Open browser DevTools
2. Inspect an email list item
3. Check the computed height
4. Update `ITEM_HEIGHT` to match

### Change Items Per Page

```javascript
// In useEmails.js composable
const perPage = ref(50)  // Change to 25, 75, 100, etc.
```

Note: Backend should support `per_page` query parameter.

### Adjust Virtual Scroll Buffer

For smoother scrolling on slower devices:

```javascript
// In EmailInbox.vue
const { ... } = useVirtualScroll({
  items: emails,
  itemHeight: ITEM_HEIGHT,
  buffer: 10  // Increase from 5 (renders more off-screen items)
})
```

Trade-off: Higher buffer = smoother scroll, more memory usage

### Customize Avatar Colors

```javascript
// In EmailListItem.vue, avatarColor computed property
const colors = [
  'bg-blue-500',
  'bg-green-500',
  'bg-purple-500',
  // Add your custom Tailwind colors
]
```

### Change Debounce Delay

```javascript
// In useEmails.js, updateSearch function
searchDebounceTimer = setTimeout(() => {
  // ...
}, 300)  // Change to 200, 500, etc. (milliseconds)
```

## Troubleshooting

### Emails not loading
1. Check browser console for errors
2. Open Network tab, look for failed `/api/emails` requests
3. Verify backend is running at `http://mail.loc`
4. Check authentication token in localStorage: `localStorage.getItem('auth_token')`
5. Test API endpoint directly: `curl http://mail.loc/api/emails -H "Authorization: Bearer YOUR_TOKEN"`

### Virtual scroll jumping
- Ensure `ITEM_HEIGHT` matches actual rendered height
- Use browser DevTools to measure: `document.querySelector('.email-item').getBoundingClientRect().height`

### Search not working
- Check that backend supports `search` query parameter
- Verify debouncing is working (should wait 300ms after typing stops)
- Open Network tab to see API requests with `?search=query`

### Infinite scroll not loading more
- Check `canLoadMore` computed property value
- Verify `isNearBottom` is triggering
- Check backend pagination response includes `current_page` and `last_page`

### Styles not applying
- Ensure Tailwind CSS is properly configured
- Check `frontend/tailwind.config.js` exists
- Verify Vite is processing Tailwind: `@tailwindcss/vite` in `vite.config.js`

### 401 Unauthorized errors
- Token expired or invalid
- Re-authenticate at `http://localhost:5173/`
- Check axios interceptor in `frontend/src/axios.js`

## Testing

### Manual Test Checklist

1. Load `/emails` page - emails should load ✓
2. Scroll down - should smoothly scroll through large list ✓
3. Scroll near bottom - should auto-load next page ✓
4. Type in search box - should wait 300ms, then filter ✓
5. Select folder from dropdown - should filter by folder ✓
6. Click Read/Unread filters - should filter by status ✓
7. Click "Has Attachments" - should filter by attachments ✓
8. Click email - should emit event ✓
9. Click mark as read - should update immediately ✓
10. Check multiple emails - should show selection toolbar ✓
11. Bulk mark as read - should update all selected ✓
12. Clear filters - should reset all filters ✓

### Test with Large Dataset

To test virtual scrolling performance:

1. Seed database with 1000+ emails
2. Load `/emails` page
3. Open browser DevTools → Performance tab
4. Start recording
5. Scroll up and down rapidly
6. Stop recording
7. Check for frame drops (should stay above 30 FPS)

### Test Network Errors

1. Open DevTools → Network tab
2. Set throttling to "Slow 3G"
3. Verify loading states work correctly
4. Disable network
5. Click refresh
6. Verify error state displays
7. Enable network
8. Click retry button
9. Verify emails load

## Performance Tips

### For 10,000+ Emails
- Increase `per_page` to 100 (fewer requests)
- Consider server-side search (ElasticSearch, etc.)
- Add pagination instead of infinite scroll
- Cache folder list (already implemented)

### For Slow Devices
- Reduce `buffer` to 3 (less rendering)
- Increase `itemHeight` (less items visible)
- Disable animations (remove transition classes)

### For Slow Network
- Reduce `per_page` to 25 (faster initial load)
- Add service worker caching
- Implement optimistic offline support

## Next Steps

1. **Test with real data**: Seed database, test all features
2. **Customize styling**: Adjust colors, spacing to match design
3. **Add features**: Implement archive, delete, star, etc.
4. **Optimize backend**: Add database indices, caching
5. **Add analytics**: Track user interactions
6. **Write tests**: Unit tests for composables, E2E for flows

## Resources

- **Full Documentation**: `EMAIL_INBOX_README.md`
- **Architecture Diagram**: `ARCHITECTURE_DIAGRAM.md`
- **Component Summary**: `COMPONENT_SUMMARY.md`
- **Vue 3 Docs**: https://vuejs.org/
- **Tailwind Docs**: https://tailwindcss.com/
- **Headless UI**: https://headlessui.com/

## Support

Need help? Check:

1. Browser console errors
2. Network tab in DevTools
3. Laravel logs: `storage/logs/laravel.log`
4. Documentation files listed above

---

**You're all set!** 🚀

Visit `http://localhost:5173/#/emails` to see your email inbox in action.
