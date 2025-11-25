import { ref, computed } from 'vue'
import axios from '../axios'

const viewsState = ref([])
const loadingState = ref(false)
const errorState = ref(null)

export function useViews() {
  const fetchViews = async () => {
    try {
      loadingState.value = true
      errorState.value = null

      const response = await axios.get('/api/email-views')
      viewsState.value = response.data.data || response.data

      return viewsState.value
    } catch (err) {
      errorState.value = err.response?.data?.message || 'Failed to fetch email views'
      console.error('Error fetching email views:', err)
      throw err
    } finally {
      loadingState.value = false
    }
  }

  const createView = async (viewData) => {
    try {
      loadingState.value = true
      errorState.value = null

      const response = await axios.post('/api/email-views', viewData)
      const newView = response.data.data || response.data
      viewsState.value = [...viewsState.value, newView]
      return newView
    } catch (err) {
      errorState.value = err.response?.data?.message || 'Failed to create email view'
      console.error('Error creating email view:', err)
      throw err
    } finally {
      loadingState.value = false
    }
  }

  const updateView = async (id, viewData) => {
    try {
      loadingState.value = true
      errorState.value = null

      const response = await axios.put(`/api/email-views/${id}`, viewData)
      const updatedView = response.data.data || response.data
      const index = viewsState.value.findIndex(view => view.id === id)
      if (index !== -1) {
        viewsState.value[index] = updatedView
      }
      return updatedView
    } catch (err) {
      errorState.value = err.response?.data?.message || 'Failed to update email view'
      console.error('Error updating email view:', err)
      throw err
    } finally {
      loadingState.value = false
    }
  }

  const deleteView = async (id) => {
    try {
      loadingState.value = true
      errorState.value = null

      await axios.delete(`/api/email-views/${id}`)
      viewsState.value = viewsState.value.filter(view => view.id !== id)
    } catch (err) {
      errorState.value = err.response?.data?.message || 'Failed to delete email view'
      console.error('Error deleting email view:', err)
      throw err
    } finally {
      loadingState.value = false
    }
  }

  const toggleView = async (id) => {
    const index = viewsState.value.findIndex(view => view.id === id)
    if (index === -1) return

    const previousValue = viewsState.value[index].is_visible
    viewsState.value[index].is_visible = !previousValue

    try {
      const response = await axios.patch(`/api/email-views/${id}/toggle`)
      const updatedView = response.data.data || response.data
      viewsState.value[index] = { ...viewsState.value[index], ...updatedView }
      return updatedView
    } catch (err) {
      viewsState.value[index].is_visible = previousValue
      errorState.value = err.response?.data?.message || 'Failed to toggle email view'
      console.error('Error toggling email view:', err)
      throw err
    }
  }

  const getViewEmails = async (viewId, params = {}) => {
    try {
      const response = await axios.get(`/api/email-views/${viewId}/emails`, { params })
      return response.data
    } catch (err) {
      console.error('Error fetching emails for view:', err)
      throw err
    }
  }

  const findViewById = (id) => {
    if (!id) return null
    return viewsState.value.find(view => String(view.id) === String(id)) || null
  }

  const visibleViews = computed(() => viewsState.value.filter(view => view.is_visible))
  const isLoading = computed(() => loadingState.value)
  const hasError = computed(() => errorState.value !== null)
  const isEmpty = computed(() => !loadingState.value && viewsState.value.length === 0)

  return {
    views: viewsState,
    loading: loadingState,
    error: errorState,
    visibleViews,
    isLoading,
    hasError,
    isEmpty,
    fetchViews,
    createView,
    updateView,
    deleteView,
    toggleView,
    getViewEmails,
    findViewById
  }
}
