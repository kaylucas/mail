import { ref, computed, watch } from 'vue'
import axios from '../axios'

/**
 * Email management composable
 * Handles fetching, filtering, sorting, and managing email state
 *
 * @returns {Object} Email state and methods
 */
export function useEmails() {
  // State
  const emails = ref([])
  const folders = ref([])
  const stats = ref(null)
  const loading = ref(false)
  const error = ref(null)
  const hasMore = ref(true)

  // Pagination
  const currentPage = ref(1)
  const perPage = ref(50)
  const total = ref(0)

  // Filters
  const filters = ref({
    folder_id: null,
    is_read: null,
    has_attachments: null,
    search: '',
    sort_by: 'received_date_time',
    sort_order: 'desc'
  })

  // Search debounce timer
  let searchDebounceTimer = null

  /**
   * Fetch emails from API
   * @param {boolean} append - Whether to append results or replace
   */
  const fetchEmails = async (append = false) => {
    try {
      loading.value = true
      error.value = null

      const params = {
        page: currentPage.value,
        per_page: perPage.value,
        ...filters.value
      }

      // Remove null/empty values
      Object.keys(params).forEach(key => {
        if (params[key] === null || params[key] === '' || params[key] === undefined) {
          delete params[key]
        }
      })

      const response = await axios.get('/api/emails', { params })

      if (append) {
        emails.value = [...emails.value, ...response.data.data]
      } else {
        emails.value = response.data.data
      }

      // Update pagination info
      total.value = response.data.total || response.data.data.length
      hasMore.value = response.data.current_page < response.data.last_page

    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch emails'
      console.error('Error fetching emails:', err)
    } finally {
      loading.value = false
    }
  }

  /**
   * Load next page (infinite scroll)
   */
  const loadMore = async () => {
    if (!hasMore.value || loading.value) return

    currentPage.value++
    await fetchEmails(true)
  }

  /**
   * Refresh email list (reset to page 1)
   */
  const refresh = async () => {
    currentPage.value = 1
    hasMore.value = true
    await fetchEmails(false)
  }

  /**
   * Fetch email folders
   */
  const fetchFolders = async () => {
    try {
      const response = await axios.get('/api/emails/folders')
      folders.value = response.data.data || response.data
    } catch (err) {
      console.error('Error fetching folders:', err)
    }
  }

  /**
   * Fetch email statistics
   */
  const fetchStats = async () => {
    try {
      const response = await axios.get('/api/emails/stats')
      stats.value = response.data
    } catch (err) {
      console.error('Error fetching stats:', err)
    }
  }

  /**
   * Get single email details
   * @param {number} emailId - Email ID
   */
  const getEmail = async (emailId) => {
    try {
      const response = await axios.get(`/api/emails/${emailId}`)
      return response.data
    } catch (err) {
      console.error('Error fetching email:', err)
      throw err
    }
  }

  /**
   * Mark email as read/unread
   * @param {number} emailId - Email ID
   * @param {boolean} isRead - Read status
   */
  const markAsRead = async (emailId, isRead = true) => {
    try {
      // Optimistic update
      const emailIndex = emails.value.findIndex(e => e.id === emailId)
      if (emailIndex !== -1) {
        const oldValue = emails.value[emailIndex].is_read
        emails.value[emailIndex].is_read = isRead

        try {
          await axios.patch(`/api/emails/${emailId}/read`, { is_read: isRead })
        } catch (err) {
          // Rollback on error
          emails.value[emailIndex].is_read = oldValue
          throw err
        }
      }
    } catch (err) {
      console.error('Error marking email as read:', err)
      throw err
    }
  }

  /**
   * Update filter value
   * @param {string} key - Filter key
   * @param {any} value - Filter value
   */
  const updateFilter = (key, value) => {
    filters.value[key] = value
    currentPage.value = 1
    hasMore.value = true
    fetchEmails(false)
  }

  /**
   * Update search query (debounced)
   * @param {string} query - Search query
   */
  const updateSearch = (query) => {
    clearTimeout(searchDebounceTimer)

    searchDebounceTimer = setTimeout(() => {
      filters.value.search = query
      currentPage.value = 1
      hasMore.value = true
      fetchEmails(false)
    }, 300)
  }

  /**
   * Clear all filters
   */
  const clearFilters = () => {
    filters.value = {
      folder_id: null,
      is_read: null,
      has_attachments: null,
      search: '',
      sort_by: 'received_date_time',
      sort_order: 'desc'
    }
    currentPage.value = 1
    hasMore.value = true
    fetchEmails(false)
  }

  /**
   * Toggle sort order
   */
  const toggleSortOrder = () => {
    filters.value.sort_order = filters.value.sort_order === 'asc' ? 'desc' : 'asc'
    currentPage.value = 1
    hasMore.value = true
    fetchEmails(false)
  }

  /**
   * Update sort field
   * @param {string} sortBy - Sort field
   */
  const updateSortBy = (sortBy) => {
    filters.value.sort_by = sortBy
    currentPage.value = 1
    hasMore.value = true
    fetchEmails(false)
  }

  // Computed
  const hasActiveFilters = computed(() => {
    return filters.value.folder_id !== null ||
      filters.value.is_read !== null ||
      filters.value.has_attachments !== null ||
      filters.value.search !== ''
  })

  const isLoading = computed(() => loading.value)
  const hasError = computed(() => error.value !== null)
  const isEmpty = computed(() => !loading.value && emails.value.length === 0)
  const canLoadMore = computed(() => hasMore.value && !loading.value)

  return {
    // State
    emails,
    folders,
    stats,
    loading,
    error,
    filters,
    currentPage,
    total,

    // Computed
    hasActiveFilters,
    isLoading,
    hasError,
    isEmpty,
    canLoadMore,

    // Methods
    fetchEmails,
    loadMore,
    refresh,
    fetchFolders,
    fetchStats,
    getEmail,
    markAsRead,
    updateFilter,
    updateSearch,
    clearFilters,
    toggleSortOrder,
    updateSortBy
  }
}
