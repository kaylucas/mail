# EmailInbox Component Documentation

A production-ready, high-performance Vue 3 email inbox component with virtual scrolling, infinite loading, and comprehensive filtering capabilities.

## Features

- **Virtual Scrolling**: Efficiently handles 1000+ emails by only rendering visible items
- **Infinite Scroll**: Automatically loads more emails as user scrolls near bottom
- **Advanced Filtering**: Search, folder filter, read/unread status, attachments
- **Smart Sorting**: Sort by date received, date sent, or subject (ascending/descending)
- **Multi-Select**: Select multiple emails for bulk actions
- **Optimistic Updates**: Instant UI feedback for mark read/unread actions
- **Responsive Design**: Mobile-first design with Tailwind CSS
- **Loading States**: Skeleton loaders and smooth transitions
- **Error Handling**: Graceful error displays with retry functionality
- **Accessibility**: Keyboard navigation and ARIA labels

## Installation

The component is already installed and configured. Dependencies:

```bash
cd frontend
pnpm install  # Already includes all required packages
```

## File Structure

```
frontend/src/
├── components/
│   ├── EmailInbox.vue          # Main inbox component
│   ├── EmailListItem.vue       # Individual email preview card
│   ├── EmailFilters.vue        # Filter/search bar component
│   └── EmailLoadingSkeleton.vue # Loading placeholder
├── composables/
│   ├── useEmails.js            # Email data fetching & state
│   └── useVirtualScroll.js     # Virtual scroll logic
├── utils/
│   └── dateFormat.js           # Date formatting utilities
└── pages/
    └── EmailsPage.vue          # Demo page with modal
```

## Usage

### Basic Usage

```vue
<template>
  <EmailInbox
    @email-selected="handleEmailClick"
    @mark-read="handleMarkRead"
  />
</template>

<script setup>
import EmailInbox from '@/components/EmailInbox.vue'

const handleEmailClick = (email) => {
  console.log('Email clicked:', email)
  // Navigate to detail page or open modal
}

const handleMarkRead = (emailId, isRead) => {
  console.log(`Email ${emailId} marked as ${isRead ? 'read' : 'unread'}`)
}
</script>
```

### Full Example with Modal (See EmailsPage.vue)

The `EmailsPage.vue` component demonstrates a complete implementation with:
- Email detail modal using Headless UI
- Refresh button
- Attachment display
- HTML email body rendering

## Component API

### EmailInbox Component

**Events:**
- `@email-selected` - Emitted when an email is clicked
  - Payload: `email` object
- `@mark-read` - Emitted when email read status changes
  - Payload: `emailId` (number), `isRead` (boolean)

### useEmails Composable

**State:**
```javascript
const {
  emails,              // ref<Array> - Current email list
  folders,             // ref<Array> - Available folders
  stats,               // ref<Object|null> - Email statistics
  loading,             // ref<Boolean> - Loading state
  error,               // ref<String|null> - Error message
  filters,             // ref<Object> - Active filters
  currentPage,         // ref<Number> - Current page number
  total,               // ref<Number> - Total email count

  // Computed
  hasActiveFilters,    // computed<Boolean> - Any filters active?
  isLoading,           // computed<Boolean> - Currently loading?
  hasError,            // computed<Boolean> - Has error?
  isEmpty,             // computed<Boolean> - No emails found?
  canLoadMore,         // computed<Boolean> - More pages available?

  // Methods
  fetchEmails,         // (append: boolean) => Promise<void>
  loadMore,            // () => Promise<void>
  refresh,             // () => Promise<void>
  fetchFolders,        // () => Promise<void>
  fetchStats,          // () => Promise<void>
  getEmail,            // (emailId: number) => Promise<Object>
  markAsRead,          // (emailId: number, isRead: boolean) => Promise<void>
  updateFilter,        // (key: string, value: any) => void
  updateSearch,        // (query: string) => void (debounced 300ms)
  clearFilters,        // () => void
  toggleSortOrder,     // () => void
  updateSortBy         // (sortBy: string) => void
} = useEmails()
```

**Filter Object:**
```javascript
{
  folder_id: null | number,
  is_read: null | boolean,
  has_attachments: null | boolean,
  search: string,
  sort_by: 'received_date_time' | 'sent_date_time' | 'subject',
  sort_order: 'asc' | 'desc'
}
```

### useVirtualScroll Composable

**Configuration:**
```javascript
const {
  containerRef,        // ref<HTMLElement> - Bind to scroll container
  scrollTop,           // ref<Number> - Current scroll position
  visibleRange,        // computed<Object> - { start, end, visibleItems }
  totalHeight,         // computed<Number> - Total height for scrolling
  offsetY,             // computed<Number> - Offset for positioning items
  isNearBottom,        // computed<Boolean> - User near bottom?

  // Methods
  handleScroll,        // (event: Event) => void
  scrollToTop,         // () => void
  scrollToItem,        // (index: number) => void
  updateViewportHeight // () => void
} = useVirtualScroll({
  items: emails,              // ref<Array>
  itemHeight: 120,            // number (pixels)
  buffer: 5,                  // number (items)
  containerHeight: null       // number | null (auto-detect)
})
```

## Utility Functions

### Date Formatting (dateFormat.js)

```javascript
import {
  formatRelativeTime,    // (dateString) => '2 hours ago'
  formatFullDateTime,    // (dateString) => 'Monday, January 5, 2025, 10:30 AM'
  getInitials,           // (name) => 'JD'
  formatFileSize,        // (bytes) => '1.2 MB'
  truncateText           // (text, maxLength) => 'Truncated...'
} from '@/utils/dateFormat'
```

## Performance Optimizations

1. **Virtual Scrolling**
   - Only renders ~10-20 visible items at a time
   - Configurable buffer for smooth scrolling
   - Automatic height calculation

2. **Debounced Search**
   - 300ms debounce on search input
   - Prevents excessive API calls

3. **Optimistic UI Updates**
   - Mark read/unread updates immediately in UI
   - Rolls back on API failure

4. **Infinite Scroll**
   - Automatically loads next page when 10 items from bottom
   - Prevents loading if already fetching

5. **Skeleton Loading**
   - Shows skeleton loaders instead of spinners
   - Better perceived performance

## Backend API Requirements

The component expects these Laravel API endpoints:

```
GET /api/emails
  Query params:
    - folder_id: number
    - is_read: boolean
    - has_attachments: boolean
    - search: string
    - per_page: number (max 100)
    - page: number
    - sort_by: 'received_date_time' | 'sent_date_time' | 'subject'
    - sort_order: 'asc' | 'desc'

  Response:
    {
      data: [...emails],
      current_page: 1,
      last_page: 10,
      total: 500
    }

GET /api/emails/{id}
  Response: { ...email }

PATCH /api/emails/{id}/read
  Body: { is_read: boolean }
  Response: { ...email }

GET /api/emails/folders
  Response: { data: [...folders] }

GET /api/emails/stats
  Response: { ...stats }
```

## Customization

### Adjusting Virtual Scroll Item Height

If your email items have different heights, update the `ITEM_HEIGHT` constant:

```javascript
// In EmailInbox.vue
const ITEM_HEIGHT = 150 // Change from default 120
```

### Changing Buffer Size

Increase buffer for smoother scrolling on slower devices:

```javascript
const { ... } = useVirtualScroll({
  items: emails,
  itemHeight: ITEM_HEIGHT,
  buffer: 10 // Increase from default 5
})
```

### Custom Color Scheme

Avatar colors are generated based on email hash. To customize:

```javascript
// In EmailListItem.vue, update avatarColor computed property
const colors = [
  'bg-blue-500',
  'bg-green-500',
  // Add your custom colors
]
```

## Styling

The component uses **Tailwind CSS 4** exclusively. No custom CSS is required except for the prose styles in EmailsPage.vue for rendering email bodies.

### Key Style Classes

- Email list items: 80-120px height (responsive)
- Mobile-first responsive design
- Smooth transitions on hover/interactions
- Focus states for accessibility

## Browser Support

- Chrome 90+
- Firefox 88+
- Safari 14+
- Edge 90+

Requires:
- ES2020+ features
- CSS Grid & Flexbox
- Intersection Observer (for future enhancements)

## Accessibility

- Semantic HTML elements
- ARIA labels on interactive elements
- Keyboard navigation support
- Screen reader friendly
- Focus visible states

## Troubleshooting

### Virtual scroll items jumping

Ensure `itemHeight` matches actual rendered height. Use browser DevTools to measure:

```javascript
// Measure actual height
const item = document.querySelector('.email-item')
console.log(item.getBoundingClientRect().height)
```

### Search not debouncing

Check that `updateSearch` is being called, not `updateFilter('search', ...)`:

```javascript
// Correct
updateSearch(query)

// Incorrect (no debounce)
updateFilter('search', query)
```

### Emails not loading

1. Check network tab for API errors
2. Verify axios configuration in `frontend/src/axios.js`
3. Check auth token in localStorage
4. Verify CORS settings in Laravel

### Performance issues

1. Reduce `buffer` size in useVirtualScroll
2. Increase `per_page` to 100 (reduces requests)
3. Check for console errors/warnings
4. Disable any browser extensions

## Testing

To test the component:

1. Navigate to `http://localhost:5173/#/emails`
2. Ensure you're authenticated
3. Backend API must be running at `http://mail.loc`

```bash
# Start frontend
cd frontend
pnpm dev

# Start backend (in another terminal)
php artisan serve
# OR use Docker
docker-compose up
```

## Future Enhancements

Potential features to add:

- [ ] Drag-and-drop to folders
- [ ] Keyboard shortcuts (j/k navigation)
- [ ] Email threading/conversations
- [ ] Star/flag emails
- [ ] Archive/delete actions
- [ ] Offline support with service workers
- [ ] Real-time updates via WebSockets
- [ ] Advanced search with filters
- [ ] Email composition
- [ ] Attachment preview/download

## License

Part of the mail.loc Laravel application.

## Support

For issues or questions, check:
1. Console errors in browser DevTools
2. Network tab for API failures
3. Laravel logs for backend errors
4. This documentation for API requirements
