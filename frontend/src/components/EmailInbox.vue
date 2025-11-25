<template>
  <div class="flex flex-col h-screen bg-gray-50 dark:bg-gray-900 transition-colors">
    <!-- Filters bar -->
    <EmailFilters
      :folder="filters.folder_id"
      :is-read="filters.is_read"
      :has-attachments="filters.has_attachments"
      :search="filters.search"
      :sort-by="filters.sort_by"
      :sort-order="filters.sort_order"
      :folders="folders"
      :has-active-filters="hasActiveFilters"
      :show-folder-filter="!isViewMode"
      @update:folder="updateFilter('folder_id', $event)"
      @update:is-read="updateFilter('is_read', $event)"
      @update:has-attachments="updateFilter('has_attachments', $event)"
      @update:search="updateSearch"
      @update:sort-by="updateSortBy"
      @toggle-sort-order="toggleSortOrder"
      @clear-filters="clearFilters"
    />

    <!-- Email list container -->
    <div class="flex-1 overflow-hidden">
      <!-- Loading state (initial load) -->
      <div v-if="isLoading && emails.length === 0" class="h-full overflow-y-auto">
        <EmailLoadingSkeleton :count="10" />
      </div>

      <!-- Error state -->
      <div v-else-if="hasError" class="flex items-center justify-center h-full p-8">
        <div class="text-center">
          <ExclamationCircleIcon class="mx-auto h-12 w-12 text-red-500 mb-4" />
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">Failed to load emails</h3>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ error }}</p>
          <button
            @click="refresh"
            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            <ArrowPathIcon class="h-5 w-5" />
            Retry
          </button>
        </div>
      </div>

      <!-- Empty state -->
      <div v-else-if="isEmpty" class="flex items-center justify-center h-full p-8">
        <div class="text-center">
          <InboxIcon class="mx-auto h-16 w-16 text-gray-400 dark:text-gray-600 mb-4" />
          <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
            {{ hasActiveFilters ? 'No emails found' : 'Your inbox is empty' }}
          </h3>
          <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
            {{ hasActiveFilters
              ? 'Try adjusting your filters or search query'
              : 'When you receive emails, they will appear here'
            }}
          </p>
          <button
            v-if="hasActiveFilters"
            @click="clearFilters"
            class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
          >
            <XMarkIcon class="h-5 w-5" />
            Clear Filters
          </button>
        </div>
      </div>

      <!-- Virtual scroll container -->
      <div
        v-else
        ref="containerRef"
        @scroll="handleScroll"
        class="h-full overflow-y-auto"
      >
        <!-- Virtual scroll spacer -->
        <div :style="{ height: `${totalHeight}px`, position: 'relative' }">
          <!-- Visible items -->
          <div :style="{ transform: `translateY(${offsetY}px)` }">
            <EmailListItem
              v-for="(email, index) in visibleRange.visibleItems"
              :key="email.id"
              :email="email"
              :selected="selectedEmails.has(email.id)"
              :show-folder="!filters.folder_id"
              :show-attachments="false"
              @click="handleEmailClick"
              @toggle-select="toggleEmailSelect"
              @mark-read="handleMarkRead"
            />
          </div>
        </div>

        <!-- Loading indicator for infinite scroll -->
        <div v-if="isLoading && emails.length > 0" class="py-4">
          <div class="flex items-center justify-center gap-2 text-sm text-gray-600 dark:text-gray-400">
            <ArrowPathIcon class="h-5 w-5 animate-spin" />
            <span>Loading more emails...</span>
          </div>
        </div>

        <!-- End of list indicator -->
        <div v-if="!canLoadMore && emails.length > 0" class="py-4 text-center text-sm text-gray-500 dark:text-gray-500">
          No more emails to load
        </div>
      </div>
    </div>

    <!-- Selection toolbar (when emails are selected) -->
    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="transform translate-y-full opacity-0"
      enter-to-class="transform translate-y-0 opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="transform translate-y-0 opacity-100"
      leave-to-class="transform translate-y-full opacity-0"
    >
      <div
        v-if="selectedEmails.size > 0"
        class="fixed bottom-0 left-0 right-0 bg-blue-600 text-white shadow-lg p-4"
      >
        <div class="max-w-7xl mx-auto flex items-center justify-between">
          <div class="flex items-center gap-4">
            <span class="font-semibold">{{ selectedEmails.size }} selected</span>
            <button
              @click="clearSelection"
              class="text-sm underline hover:no-underline"
            >
              Clear selection
            </button>
          </div>
          <div class="flex items-center gap-2">
            <button
              @click="markSelectedAsRead(true)"
              class="inline-flex items-center gap-2 px-4 py-2 bg-white text-blue-600 rounded-lg hover:bg-gray-100 transition-colors"
            >
              <EnvelopeOpenIcon class="h-5 w-5" />
              Mark as read
            </button>
            <button
              @click="markSelectedAsRead(false)"
              class="inline-flex items-center gap-2 px-4 py-2 bg-white text-blue-600 rounded-lg hover:bg-gray-100 transition-colors"
            >
              <EnvelopeIcon class="h-5 w-5" />
              Mark as unread
            </button>
          </div>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import {
  ExclamationCircleIcon,
  InboxIcon,
  XMarkIcon,
  ArrowPathIcon,
  EnvelopeIcon,
  EnvelopeOpenIcon
} from '@heroicons/vue/24/outline'
import EmailFilters from './EmailFilters.vue'
import EmailListItem from './EmailListItem.vue'
import EmailLoadingSkeleton from './EmailLoadingSkeleton.vue'
import { useEmails } from '../composables/useEmails'
import { useViews } from '../composables/useViews'
import { useVirtualScroll } from '../composables/useVirtualScroll'

const props = defineProps({
  viewId: {
    type: [String, Number],
    default: null
  }
})

const emit = defineEmits(['email-selected', 'mark-read'])
const { getViewEmails } = useViews()
const isViewMode = computed(() => !!props.viewId)

// Email management
const {
  emails,
  folders,
  loading,
  error,
  filters,
  hasActiveFilters,
  isLoading,
  hasError,
  isEmpty,
  canLoadMore,
  fetchEmails,
  loadMore,
  refresh,
  fetchFolders,
  markAsRead,
  updateFilter,
  updateSearch,
  clearFilters,
  toggleSortOrder,
  updateSortBy,
  setCustomFetcher
} = useEmails()

// Virtual scrolling
const ITEM_HEIGHT = 120 // Approximate height of each email item
const {
  containerRef,
  visibleRange,
  totalHeight,
  offsetY,
  isNearBottom,
  handleScroll
} = useVirtualScroll({
  items: emails,
  itemHeight: ITEM_HEIGHT,
  buffer: 5
})

// Multi-select state
const selectedEmails = ref(new Set())

// Configure custom fetcher when view changes
const configureFetcher = () => {
  if (props.viewId) {
    setCustomFetcher(({ page, perPage, filters: currentFilters }) => {
      const params = {
        page,
        per_page: perPage,
        sort_by: currentFilters.sort_by,
        sort_order: currentFilters.sort_order
      }

      if (currentFilters.is_read !== null && currentFilters.is_read !== undefined) {
        params.is_read = currentFilters.is_read
      }

      if (currentFilters.has_attachments !== null && currentFilters.has_attachments !== undefined) {
        params.has_attachments = currentFilters.has_attachments
      }

      if (currentFilters.search) {
        params.search = currentFilters.search
      }

      return getViewEmails(props.viewId, params)
    })
  } else {
    setCustomFetcher(null)
  }
}

watch(() => props.viewId, async (newVal, oldVal) => {
  configureFetcher()
  if (newVal) {
    updateFilter('folder_id', null)
  }
  await refresh()
})

// Watch for near bottom to trigger infinite scroll
watch(isNearBottom, (nearBottom) => {
  if (nearBottom && canLoadMore.value) {
    loadMore()
  }
})

// Methods
const handleEmailClick = (email) => {
  // Mark as read on click if unread
  if (!email.is_read) {
    markAsRead(email.id, true).catch(console.error)
  }

  emit('email-selected', email)
}

const handleMarkRead = async (emailId, isRead) => {
  try {
    await markAsRead(emailId, isRead)
    emit('mark-read', emailId, isRead)
  } catch (err) {
    console.error('Failed to mark email as read:', err)
  }
}

const toggleEmailSelect = (emailId) => {
  if (selectedEmails.value.has(emailId)) {
    selectedEmails.value.delete(emailId)
  } else {
    selectedEmails.value.add(emailId)
  }
  // Trigger reactivity
  selectedEmails.value = new Set(selectedEmails.value)
}

const clearSelection = () => {
  selectedEmails.value.clear()
}

const markSelectedAsRead = async (isRead) => {
  const promises = Array.from(selectedEmails.value).map(emailId =>
    markAsRead(emailId, isRead)
  )

  try {
    await Promise.all(promises)
    clearSelection()
  } catch (err) {
    console.error('Failed to mark emails as read:', err)
  }
}

// Initialize
onMounted(async () => {
  configureFetcher()
  await fetchFolders()
  await fetchEmails()
})
</script>
