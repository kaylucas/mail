import { ref, computed } from 'vue'
import axios from '../axios'

/**
 * Email reminders management composable
 * Handles fetching and managing email reminders with due date tracking
 *
 * @returns {Object} Email reminders state and methods
 */
export function useEmailReminders() {
  // State
  const reminders = ref([])
  const loading = ref(false)
  const error = ref(null)

  /**
   * Fetch email reminders from API
   * @param {string|null} status - Filter by status: 'pending', 'dismissed', 'completed', or null for all
   */
  const fetchReminders = async (status = null) => {
    try {
      loading.value = true
      error.value = null

      const params = {}
      if (status) {
        params.status = status
      }

      const response = await axios.get('/api/email-reminders', { params })
      reminders.value = response.data.data || response.data

    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to fetch email reminders'
      console.error('Error fetching email reminders:', err)
    } finally {
      loading.value = false
    }
  }

  /**
   * Dismiss a reminder
   * @param {number} id - Reminder ID
   * @returns {Object} Updated reminder
   */
  const dismissReminder = async (id) => {
    try {
      const response = await axios.patch(`/api/email-reminders/${id}/dismiss`)
      const updatedReminder = response.data.data || response.data

      // Update in local state
      const index = reminders.value.findIndex(r => r.id === id)
      if (index !== -1) {
        reminders.value[index] = updatedReminder
      }

      return updatedReminder
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to dismiss reminder'
      console.error('Error dismissing reminder:', err)
      throw err
    }
  }

  /**
   * Mark a reminder as completed
   * @param {number} id - Reminder ID
   * @returns {Object} Updated reminder
   */
  const completeReminder = async (id) => {
    try {
      const response = await axios.patch(`/api/email-reminders/${id}/complete`)
      const updatedReminder = response.data.data || response.data

      // Update in local state
      const index = reminders.value.findIndex(r => r.id === id)
      if (index !== -1) {
        reminders.value[index] = updatedReminder
      }

      return updatedReminder
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to complete reminder'
      console.error('Error completing reminder:', err)
      throw err
    }
  }

  /**
   * Snooze a reminder for a specified number of days
   * @param {number} id - Reminder ID
   * @param {number} days - Number of days to snooze
   * @returns {Object} Updated reminder
   */
  const snoozeReminder = async (id, days) => {
    try {
      const response = await axios.patch(`/api/email-reminders/${id}/snooze`, { days })
      const updatedReminder = response.data.data || response.data

      // Update in local state
      const index = reminders.value.findIndex(r => r.id === id)
      if (index !== -1) {
        reminders.value[index] = updatedReminder
      }

      return updatedReminder
    } catch (err) {
      error.value = err.response?.data?.message || 'Failed to snooze reminder'
      console.error('Error snoozing reminder:', err)
      throw err
    }
  }

  // Computed properties
  const pendingReminders = computed(() => {
    return reminders.value.filter(reminder => reminder.status === 'pending')
  })

  const dueReminders = computed(() => {
    const now = new Date()
    return pendingReminders.value.filter(reminder => {
      if (!reminder.remind_at) return false
      const remindAt = new Date(reminder.remind_at)
      return remindAt <= now
    })
  })

  const isLoading = computed(() => loading.value)
  const hasError = computed(() => error.value !== null)
  const isEmpty = computed(() => !loading.value && reminders.value.length === 0)
  const hasDueReminders = computed(() => dueReminders.value.length > 0)

  return {
    // State
    reminders,
    loading,
    error,

    // Computed
    pendingReminders,
    dueReminders,
    isLoading,
    hasError,
    isEmpty,
    hasDueReminders,

    // Methods
    fetchReminders,
    dismissReminder,
    completeReminder,
    snoozeReminder
  }
}
