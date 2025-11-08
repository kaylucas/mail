import { ref, computed } from 'vue'
import axios from '../axios'

/**
 * Email rules management composable
 * Handles fetching, creating, updating, and managing AI-powered email rules
 *
 * @returns {Object} Email rules state and methods
 */
export function useEmailRules() {
  // State
  const rules = ref([])
  const loading = ref(false)
  const error = ref(null)

  /**
   * Fetch all email rules from API
   */
  const fetchRules = async () => {
    try {
      loading.value = true
      error.value = null

      const response = await axios.get('/api/email-rules')
      rules.value = response.data.data || response.data

    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch email rules'
      console.error('Error fetching email rules:', err)
    } finally {
      loading.value = false
    }
  }

  /**
   * Create a new email rule
   * @param {Object} ruleData - Rule configuration data
   * @returns {Object} Created rule
   */
  const createRule = async (ruleData) => {
    try {
      loading.value = true
      error.value = null

      const response = await axios.post('/api/email-rules', ruleData)
      const newRule = response.data.data || response.data

      // Add to local state
      rules.value.push(newRule)

      return newRule
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to create email rule'
      console.error('Error creating email rule:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Update an existing email rule
   * @param {number} id - Rule ID
   * @param {Object} ruleData - Updated rule data
   * @returns {Object} Updated rule
   */
  const updateRule = async (id, ruleData) => {
    try {
      loading.value = true
      error.value = null

      const response = await axios.put(`/api/email-rules/${id}`, ruleData)
      const updatedRule = response.data.data || response.data

      // Update in local state
      const index = rules.value.findIndex(r => r.id === id)
      if (index !== -1) {
        rules.value[index] = updatedRule
      }

      return updatedRule
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to update email rule'
      console.error('Error updating email rule:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Delete an email rule
   * @param {number} id - Rule ID
   */
  const deleteRule = async (id) => {
    try {
      loading.value = true
      error.value = null

      await axios.delete(`/api/email-rules/${id}`)

      // Remove from local state
      rules.value = rules.value.filter(r => r.id !== id)

    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to delete email rule'
      console.error('Error deleting email rule:', err)
      throw err
    } finally {
      loading.value = false
    }
  }

  /**
   * Toggle rule active status
   * @param {number} id - Rule ID
   * @returns {Object} Updated rule
   */
  const toggleRule = async (id) => {
    try {
      // Optimistic update
      const index = rules.value.findIndex(r => r.id === id)
      if (index !== -1) {
        const oldValue = rules.value[index].is_active
        rules.value[index].is_active = !oldValue

        try {
          const response = await axios.patch(`/api/email-rules/${id}/toggle`)
          const updatedRule = response.data.data || response.data

          // Merge response data to preserve relationships (like actions)
          rules.value[index] = { ...rules.value[index], ...updatedRule }

          return updatedRule
        } catch (err) {
          // Rollback on error
          rules.value[index].is_active = oldValue
          throw err
        }
      }
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to toggle email rule'
      console.error('Error toggling email rule:', err)
      throw err
    }
  }

  // Computed properties
  const activeRules = computed(() => {
    return rules.value.filter(rule => rule.is_active)
  })

  const isLoading = computed(() => loading.value)
  const hasError = computed(() => error.value !== null)
  const isEmpty = computed(() => !loading.value && rules.value.length === 0)

  return {
    // State
    rules,
    loading,
    error,

    // Computed
    activeRules,
    isLoading,
    hasError,
    isEmpty,

    // Methods
    fetchRules,
    createRule,
    updateRule,
    deleteRule,
    toggleRule
  }
}
