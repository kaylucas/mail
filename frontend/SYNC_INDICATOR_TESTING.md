# Email Sync Indicator - Testing Guide

## Overview

The EmailSyncIndicator component displays real-time email sync progress in a banner at the top of the dashboard. It polls the backend `/api/emails/sync/status` endpoint and updates the UI based on the current sync state.

## Component Location

- **Component**: `/frontend/src/components/EmailSyncIndicator.vue`
- **Integration**: `/frontend/src/pages/Dashboard.vue`

## Testing Checklist

### 1. New User Login (Initial Sync)

**Steps:**
1. Clear browser localStorage
2. Delete user's emails from database (or use fresh account)
3. Login via Microsoft OAuth
4. Observe the sync indicator appears automatically

**Expected Behavior:**
- [ ] Blue banner appears with "Syncing your emails..." message
- [ ] Spinner icon is visible and rotating
- [ ] Progress updates every 2-3 seconds (e.g., "8 folders · 42 messages")
- [ ] Banner remains visible while status is "syncing"

### 2. Sync Completion

**Steps:**
1. Wait for sync to complete (backend returns status: "completed")

**Expected Behavior:**
- [ ] Banner changes to green
- [ ] Success icon (CheckCircle) appears
- [ ] Shows "Sync complete! X emails synced" message
- [ ] Banner auto-dismisses after 5 seconds
- [ ] Banner can be manually closed with X button

### 3. Sync Progress Updates

**Steps:**
1. During an active sync, observe the progress updates

**Expected Behavior:**
- [ ] Progress shows "X folders · Y messages" or "Starting sync..."
- [ ] Updates every 2.5 seconds without page refresh
- [ ] Progress numbers increase over time

### 4. Page Refresh During Sync

**Steps:**
1. Start a sync
2. Refresh the page (F5 or Cmd+R) while sync is in progress
3. Observe indicator reappears

**Expected Behavior:**
- [ ] Indicator appears immediately after page load
- [ ] Shows current sync progress
- [ ] Resumes polling automatically
- [ ] Continues updating until sync completes

### 5. Multiple Tabs

**Steps:**
1. Open dashboard in two browser tabs
2. Start a sync in one tab

**Expected Behavior:**
- [ ] Both tabs show the sync indicator
- [ ] Both tabs poll independently (acceptable behavior)
- [ ] Closing indicator in one tab doesn't affect the other

### 6. Sync Failure

**Steps:**
1. Simulate a sync failure (backend returns status: "failed")
   - Option A: Disconnect network during sync
   - Option B: Revoke OAuth token
   - Option C: Mock the API response to return error

**Expected Behavior:**
- [ ] Banner changes to red
- [ ] Error icon (ExclamationCircle) appears
- [ ] Shows "Email sync failed" message
- [ ] Error details displayed if available
- [ ] "Retry" button is visible
- [ ] Polling stops

### 7. Retry After Failure

**Steps:**
1. Click "Retry" button on failed sync

**Expected Behavior:**
- [ ] Banner changes back to blue (syncing state)
- [ ] Polling resumes immediately
- [ ] Progress updates start appearing again

### 8. Manual Close Button

**Steps:**
1. During any state (syncing, completed, failed), click the X button

**Expected Behavior:**
- [ ] Banner disappears immediately
- [ ] Polling stops
- [ ] Banner does not reappear unless sync status changes

### 9. Long-Running Sync (5+ minutes)

**Steps:**
1. Start a sync that takes longer than 5 minutes
   - Wait or adjust `LONG_RUNNING_THRESHOLD` in code for testing

**Expected Behavior:**
- [ ] After 5 minutes, banner changes to yellow
- [ ] Clock icon appears
- [ ] Shows "This is taking longer than expected..." message
- [ ] Additional message: "We're still syncing your emails. This may take a while for large inboxes."
- [ ] Polling continues

### 10. Returning User (Already Has Emails)

**Steps:**
1. Login as a user who already has emails synced
2. Backend returns `{ "status": "idle", "has_emails": true }`

**Expected Behavior:**
- [ ] Sync indicator does NOT appear
- [ ] No polling occurs
- [ ] Dashboard loads normally without banner

### 11. Navigation Away from Dashboard

**Steps:**
1. Start a sync
2. Navigate to another page in the app
3. Navigate back to dashboard

**Expected Behavior:**
- [ ] Polling stops when component unmounts
- [ ] Indicator reappears when returning to dashboard
- [ ] Status is fetched again on mount

### 12. Network Error During Polling

**Steps:**
1. Start a sync
2. Disconnect network or block API requests
3. Wait for next poll attempt

**Expected Behavior:**
- [ ] Banner changes to red (failed state)
- [ ] Shows "Could not connect to server. Check your connection." message
- [ ] Polling stops
- [ ] Retry button allows resuming

### 13. Auto-Dismiss Timer

**Steps:**
1. Wait for sync to complete
2. Observe the 5-second auto-dismiss

**Expected Behavior:**
- [ ] Success banner shows for exactly 5 seconds
- [ ] Banner smoothly slides up and disappears
- [ ] No errors in console

### 14. Responsive Design

**Steps:**
1. View indicator on mobile (320px width)
2. View on tablet (768px width)
3. View on desktop (1440px+ width)

**Expected Behavior:**
- [ ] Banner is readable on all screen sizes
- [ ] Text doesn't overflow or wrap awkwardly
- [ ] Close button remains accessible
- [ ] Progress info is visible (may truncate gracefully)

## API Response Testing

### Mock Idle Response
```json
{
  "status": "idle",
  "message": "No sync in progress",
  "has_emails": false
}
```

### Mock Syncing Response
```json
{
  "status": "syncing",
  "message": "Email sync in progress",
  "job_id": "abc123",
  "started_at": "2025-11-06T10:30:00.000000Z",
  "progress": {
    "folders_synced": 8,
    "messages_synced": 42,
    "current_page": 3,
    "last_updated": "2025-11-06T10:31:00Z"
  }
}
```

### Mock Completed Response
```json
{
  "status": "completed",
  "message": "Email sync completed",
  "synced_count": 150,
  "last_sync_at": "2025-11-06T10:35:00.000000Z",
  "has_emails": true
}
```

### Mock Failed Response
```json
{
  "status": "failed",
  "message": "Email sync failed",
  "error": "Graph API request failed: 401",
  "failed_at": "2025-11-06 10:32:00"
}
```

## Manual Testing with Browser DevTools

### Simulate Different States

**Using Console:**
```javascript
// Access the component instance
const indicator = document.querySelector('[data-component="email-sync-indicator"]')

// Force syncing state
fetch('/api/emails/sync/status')
  .then(r => r.json())
  .then(console.log)
```

**Using Network Tab:**
1. Open Network tab in DevTools
2. Filter for `/api/emails/sync/status`
3. Right-click request → Block request URL (to simulate network error)
4. Or use "Throttling" to simulate slow network

**Using Local Storage:**
```javascript
// Check if token exists
localStorage.getItem('auth_token')

// Clear auth (logout)
localStorage.removeItem('auth_token')
```

## Edge Cases Handled

1. **User closes tab during sync** → On reopen, status is checked and polling resumes if still syncing
2. **Network error during polling** → Shows error message, stops polling, allows retry
3. **Multiple tabs open** → Each tab polls independently (acceptable, not using shared state)
4. **User navigates away** → Polling stops on component unmount
5. **Sync takes very long** → After 5 minutes, shows "taking longer than expected" message
6. **Completed sync auto-dismiss** → Banner hides after 5 seconds
7. **Manual close during any state** → Stops polling and hides banner
8. **Returning user with emails** → Banner never shows

## Performance Considerations

- **Poll Interval**: 2.5 seconds (configurable via `POLL_INTERVAL` constant)
- **Auto-dismiss Delay**: 5 seconds for completed state
- **Long Running Threshold**: 5 minutes (configurable via `LONG_RUNNING_THRESHOLD`)
- **Memory**: Polling is cleaned up on unmount to prevent memory leaks
- **Network**: Minimal payload (~500 bytes per request)

## Accessibility

- **ARIA Labels**: Close button has `aria-label="Close"`
- **Color Contrast**: Text colors meet WCAG AA standards
- **Focus States**: Close and Retry buttons have visible focus indicators
- **Screen Readers**: Status messages are announced as they change

## Browser Compatibility

Tested on:
- [ ] Chrome/Edge (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Mobile Safari (iOS)
- [ ] Chrome Mobile (Android)

## Known Limitations

1. **No Shared State**: Multiple tabs poll independently (not using WebSockets or SharedWorker)
2. **No Pause/Resume**: User cannot pause syncing from UI
3. **No Progress Bar**: Uses text-based progress instead of visual progress bar
4. **No Retry Count**: Failed syncs don't track number of retry attempts

## Future Enhancements

- Add progress bar visualization (0-100% complete)
- Add "Pause Sync" button for large imports
- Use WebSockets for real-time updates instead of polling
- Add sync history/logs page
- Show estimated time remaining based on progress rate
