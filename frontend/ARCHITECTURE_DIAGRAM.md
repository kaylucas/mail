# EmailInbox Architecture & Data Flow

## Component Hierarchy

```
EmailsPage.vue (Demo Page)
    │
    └── EmailInbox.vue (Main Container)
            │
            ├── EmailFilters.vue (Filter Bar)
            │       │
            │       └── Emits: update:folder, update:isRead,
            │                   update:hasAttachments, update:search,
            │                   update:sortBy, toggle-sort-order,
            │                   clear-filters
            │
            ├── EmailLoadingSkeleton.vue (Loading State)
            │
            └── EmailListItem.vue × N (Virtual Scrolled Items)
                    │
                    └── Emits: click, toggle-select, mark-read
```

## Data Flow Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                         User Actions                         │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                      EmailInbox.vue                          │
│  ┌───────────────────────────────────────────────────────┐  │
│  │              useEmails() Composable                    │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │  State Management                               │  │  │
│  │  │  - emails: ref([])                              │  │  │
│  │  │  - filters: ref({ search, folder_id, ... })    │  │  │
│  │  │  - loading: ref(false)                          │  │  │
│  │  │  - error: ref(null)                             │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  │                        │                               │  │
│  │                        ▼                               │  │
│  │  ┌─────────────────────────────────────────────────┐  │  │
│  │  │  API Methods (via axios)                        │  │  │
│  │  │  - fetchEmails()                                │  │  │
│  │  │  - fetchFolders()                               │  │  │
│  │  │  - markAsRead()                                 │  │  │
│  │  │  - loadMore() (infinite scroll)                │  │  │
│  │  └─────────────────────────────────────────────────┘  │  │
│  └───────────────────────────────────────────────────────┘  │
│                              │                               │
│                              ▼                               │
│  ┌───────────────────────────────────────────────────────┐  │
│  │         useVirtualScroll() Composable                  │  │
│  │  - Calculates visible range from emails array         │  │
│  │  - Returns visibleRange.visibleItems (10-30 items)    │  │
│  │  - Handles scroll events and positioning              │  │
│  └───────────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│                  EmailFilters Component                      │
│  - Displays current filter state                            │
│  - Emits filter changes to parent                           │
└─────────────────────────────────────────────────────────────┘
                              │
                              ▼
┌─────────────────────────────────────────────────────────────┐
│              EmailListItem × visibleItems.length             │
│  - Renders individual email preview                         │
│  - Uses utils/dateFormat.js for formatting                  │
│  - Emits click events to parent                             │
└─────────────────────────────────────────────────────────────┘
```

## State Management Flow

### 1. Initial Load

```
User navigates to /emails
        │
        ▼
EmailInbox.vue → onMounted()
        │
        ├─→ fetchFolders() ──→ GET /api/emails/folders
        │                             │
        │                             ▼
        │                      folders.value = [...]
        │
        └─→ fetchEmails() ───→ GET /api/emails?page=1&per_page=50
                                      │
                                      ▼
                               emails.value = [...]
                                      │
                                      ▼
                          useVirtualScroll calculates visible items
                                      │
                                      ▼
                          Renders EmailListItem components
```

### 2. Filter Change

```
User types in search box
        │
        ▼
EmailFilters.vue → handleSearchInput()
        │
        ▼
Debounce 300ms
        │
        ▼
Emit 'update:search' event
        │
        ▼
EmailInbox.vue → updateSearch(query)
        │
        ▼
useEmails() → updateSearch()
        │
        ├─→ filters.value.search = query
        ├─→ currentPage.value = 1
        └─→ fetchEmails(false) ──→ GET /api/emails?search=query&page=1
                                          │
                                          ▼
                                   emails.value = [...new results]
                                          │
                                          ▼
                               Virtual scroll recalculates
                                          │
                                          ▼
                                 UI updates with new items
```

### 3. Infinite Scroll

```
User scrolls near bottom
        │
        ▼
useVirtualScroll() → handleScroll(event)
        │
        ▼
Calculate scrollTop and isNearBottom
        │
        ▼
isNearBottom = true (watcher triggers)
        │
        ▼
EmailInbox.vue → watch(isNearBottom)
        │
        ▼
Check: canLoadMore && !loading
        │
        ▼
useEmails() → loadMore()
        │
        ├─→ currentPage.value++
        └─→ fetchEmails(true) ──→ GET /api/emails?page=2
                                          │
                                          ▼
                                   emails.value = [...old, ...new]
                                          │
                                          ▼
                               Virtual scroll recalculates
                                          │
                                          ▼
                                 Seamless scroll continues
```

### 4. Mark as Read (Optimistic Update)

```
User clicks "Mark as read" button
        │
        ▼
EmailListItem.vue → toggleReadStatus()
        │
        ▼
Emit 'mark-read' event
        │
        ▼
EmailInbox.vue → handleMarkRead(emailId, isRead)
        │
        ▼
useEmails() → markAsRead(emailId, true)
        │
        ├─→ Find email in emails.value array
        ├─→ Save old value (for rollback)
        ├─→ emails[i].is_read = true (OPTIMISTIC UPDATE)
        │                 │
        │                 ▼
        │          UI updates immediately! ⚡
        │
        └─→ PATCH /api/emails/{id}/read { is_read: true }
                    │
                    ├─→ Success: Keep optimistic update
                    │
                    └─→ Error: Rollback to old value
```

## Virtual Scrolling Mechanics

```
Scroll Container (100vh height)
│
├─── Total Height Spacer (totalHeight = emailCount × itemHeight)
│    │
│    └─── Positioned Container (translateY = offsetY)
│         │
│         └─── Visible Items (only 20-30 rendered)
│              ├── Item at index 10 (offsetY = 1200px)
│              ├── Item at index 11
│              ├── Item at index 12
│              │   ...
│              └── Item at index 29

As user scrolls:
    scrollTop changes
        │
        ▼
    Calculate visible range:
        start = floor(scrollTop / itemHeight) - buffer
        end = ceil((scrollTop + viewportHeight) / itemHeight) + buffer
        │
        ▼
    Update visibleItems = emails.slice(start, end)
        │
        ▼
    Update offsetY = start × itemHeight
        │
        ▼
    Re-render only visible items with translateY(offsetY)
```

## API Response Structure

### GET /api/emails

```javascript
{
  data: [
    {
      id: 1,
      message_id: "AAMkAD...",
      subject: "Meeting Tomorrow",
      from_name: "John Doe",
      from_email: "john@example.com",
      to_recipients: [
        { name: "Jane Smith", email: "jane@example.com" }
      ],
      body_preview: "Hi Jane, don't forget about...",
      body_content: "<html>...",
      body_content_type: "html",
      received_date_time: "2025-01-05T10:30:00Z",
      sent_date_time: "2025-01-05T10:29:00Z",
      is_read: false,
      is_draft: false,
      has_attachments: true,
      importance: "normal",
      email_folder: {
        id: 1,
        display_name: "Inbox",
        unread_item_count: 5
      },
      attachments: [
        {
          id: 1,
          name: "document.pdf",
          content_type: "application/pdf",
          size: 12345,
          is_inline: false
        }
      ]
    },
    // ... more emails
  ],
  current_page: 1,
  last_page: 10,
  per_page: 50,
  total: 487
}
```

## Event Flow

### EmailFilters Events

```javascript
// Filter change
EmailFilters → emit('update:folder', folderId)
                      │
                      ▼
              EmailInbox → updateFilter('folder_id', folderId)
                                  │
                                  ▼
                          useEmails() → fetchEmails()

// Search
EmailFilters → emit('update:search', query)
                      │
                      ▼
              EmailInbox → updateSearch(query) // Debounced

// Clear all
EmailFilters → emit('clear-filters')
                      │
                      ▼
              EmailInbox → clearFilters()
```

### EmailListItem Events

```javascript
// Email click
EmailListItem → emit('click', email)
                      │
                      ▼
              EmailInbox → handleEmailClick(email)
                                  │
                                  ▼
                          emit('email-selected', email)
                                  │
                                  ▼
                          EmailsPage → Open modal with email details

// Mark read
EmailListItem → emit('mark-read', emailId, isRead)
                      │
                      ▼
              EmailInbox → handleMarkRead(emailId, isRead)
                                  │
                                  ▼
                          useEmails() → markAsRead() // Optimistic update

// Multi-select
EmailListItem → emit('toggle-select', emailId)
                      │
                      ▼
              EmailInbox → toggleEmailSelect(emailId)
                                  │
                                  ▼
                          selectedEmails.value.add(emailId)
                                  │
                                  ▼
                          Show selection toolbar
```

## Performance Optimizations

### 1. Virtual Scrolling
```
Without Virtual Scroll:     With Virtual Scroll:
  1000 emails                  1000 emails
     ↓                            ↓
  1000 DOM nodes              ~20 DOM nodes
     ↓                            ↓
  Slow rendering              Fast rendering
  High memory usage           Low memory usage
```

### 2. Debounced Search
```
User types "meeting":
  m → Wait 300ms
  e → Reset timer, wait 300ms
  e → Reset timer, wait 300ms
  t → Reset timer, wait 300ms
  i → Reset timer, wait 300ms
  n → Reset timer, wait 300ms
  g → Reset timer, wait 300ms
  [300ms passes]
  → Single API call for "meeting"

Result: 1 API call instead of 7
```

### 3. Optimistic Updates
```
Standard Flow:                 Optimistic Flow:
  User clicks button             User clicks button
       ↓                              ↓
  API request                    UI updates immediately ⚡
       ↓                              ↓
  Wait 200-500ms                API request in background
       ↓                              ↓
  API response                  API response (confirm/rollback)
       ↓
  UI updates

Perceived latency: ~300ms      Perceived latency: 0ms
```

## Error Handling Flow

```
API Request
    │
    ├─→ Success (200-299)
    │       │
    │       └─→ Update state
    │               │
    │               └─→ Render UI
    │
    ├─→ Unauthorized (401)
    │       │
    │       ├─→ Clear auth token
    │       └─→ Redirect to login
    │
    ├─→ Client Error (400-499)
    │       │
    │       └─→ Display error message
    │               │
    │               └─→ Show retry button
    │
    └─→ Server Error (500-599) or Network Error
            │
            └─→ Display error message
                    │
                    └─→ Show retry button
```

## Reactivity Chain

```
User Action
    │
    ▼
Update ref() or reactive()
    │
    ▼
Vue's Reactivity System
    │
    ▼
Computed properties recalculate
    │
    ▼
Components re-render (minimal, thanks to Virtual DOM)
    │
    ▼
DOM updates
    │
    ▼
User sees change
```

Example:
```javascript
// User updates search filter
filters.value.search = 'meeting'  // ref update triggers reactivity
    ↓
hasActiveFilters computed property recalculates
    ↓
EmailFilters component re-renders (shows active state)
    ↓
fetchEmails() called
    ↓
emails.value = [...new data]  // ref update triggers reactivity
    ↓
visibleRange computed property recalculates (in useVirtualScroll)
    ↓
EmailInbox component re-renders
    ↓
EmailListItem components re-render (only visible ones!)
    ↓
DOM updates with new email list
```

## File Dependencies Graph

```
EmailsPage.vue
    ├── EmailInbox.vue
    │   ├── EmailFilters.vue
    │   │   ├── @headlessui/vue (Menu, MenuButton, MenuItems)
    │   │   └── @heroicons/vue (icons)
    │   ├── EmailListItem.vue
    │   │   ├── @heroicons/vue (icons)
    │   │   └── utils/dateFormat.js
    │   ├── EmailLoadingSkeleton.vue
    │   ├── composables/useEmails.js
    │   │   └── axios.js
    │   └── composables/useVirtualScroll.js
    ├── @headlessui/vue (Dialog, TransitionRoot)
    ├── @heroicons/vue (icons)
    └── utils/dateFormat.js
```

## Memory Management

### Efficient Memory Usage

```
Total emails in backend: 10,000
    │
    ▼
Fetched in memory: 50-500 (paginated)
    │
    ▼
Virtual scroll renders: 20-30 (visible + buffer)
    │
    ▼
Actual DOM nodes: 20-30

Memory footprint: ~10KB for visible items
    vs
Memory footprint without virtual scroll: ~5MB for all fetched items
```

### Garbage Collection

```
User scrolls down
    │
    ▼
Old items are removed from DOM (but stay in emails array)
    │
    ▼
Browser garbage collects unused DOM nodes
    │
    ▼
Memory stays constant regardless of scroll position
```

## Summary

The architecture follows these principles:

1. **Separation of Concerns**: UI components, business logic (composables), and utilities are cleanly separated
2. **Unidirectional Data Flow**: Props down, events up
3. **Reactive State Management**: Vue's reactivity system with refs and computed properties
4. **Performance First**: Virtual scrolling, debouncing, optimistic updates
5. **Error Resilience**: Comprehensive error handling and fallbacks
6. **Accessibility**: Semantic HTML, ARIA labels, keyboard navigation
7. **Maintainability**: Clear file structure, consistent naming, comprehensive documentation

The result is a production-ready email inbox that can handle large datasets efficiently while providing an excellent user experience.
