<template>
  <AppLayout>
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Loading State -->
      <div v-if="loading" class="flex-1 flex items-center justify-center bg-gray-50">
        <div class="text-center">
          <div class="relative">
            <div class="animate-spin rounded-full h-16 w-16 border-4 border-gray-200 border-t-indigo-600 mx-auto mb-4"></div>
            <div class="absolute inset-0 flex items-center justify-center">
              <svg class="h-8 w-8 text-indigo-600 opacity-50" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
              </svg>
            </div>
          </div>
          <p class="text-sm font-medium text-gray-900 mb-1">Loading email</p>
          <p class="text-xs text-gray-500">Please wait...</p>
        </div>
      </div>

      <!-- Error State -->
      <div v-else-if="error" class="flex-1 flex items-center justify-center bg-gray-50 px-4">
        <div class="max-w-md w-full">
          <div class="bg-white border-2 border-red-200 rounded-xl p-8 text-center shadow-lg">
            <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-red-100 mb-4">
              <svg class="h-8 w-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
              </svg>
            </div>
            <h3 class="text-xl font-bold text-gray-900 mb-2">Failed to Load Email</h3>
            <p class="text-sm text-gray-600 mb-6">{{ error }}</p>
            <button
              @click="goBack"
              class="inline-flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-medium rounded-lg hover:bg-indigo-700 transition-all duration-150 shadow-md hover:shadow-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
            >
              <ArrowLeftIcon class="h-5 w-5" />
              Back to Inbox
            </button>
          </div>
        </div>
      </div>

      <!-- Email Content -->
      <div v-else-if="email" class="flex-1 flex flex-col overflow-hidden">
        <!-- Email Header Component -->
        <EmailHeader
          :email="email"
          @toggle-read="handleToggleRead"
          @test-rules="testEmailRules"
          @back="goBack"
        />

        <!-- Email Rule Test Results Modal -->
        <EmailRuleTestResults
          :show="showTestResults"
          :results="testResults"
          :loading="testingRules"
          :error="testError"
          @close="showTestResults = false"
          @retest="handleRetest"
        />

        <!-- Email Body Container -->
        <div class="flex-1 overflow-y-auto bg-white">
          <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
            <!-- Email Attachments Component -->
            <EmailAttachments
              v-if="email.attachments && email.attachments.length > 0"
              :attachments="email.attachments"
              class="mb-6"
            />

            <!-- Email Body Component -->
            <EmailBody
              :html-content="email.body_content"
              :content-type="email.body_content_type"
            />
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { ArrowLeftIcon } from '@heroicons/vue/24/outline'
import axios from '../axios'
import AppLayout from '../layouts/AppLayout.vue'
import EmailHeader from '../components/EmailHeader.vue'
import EmailBody from '../components/EmailBody.vue'
import EmailAttachments from '../components/EmailAttachments.vue'
import EmailRuleTestResults from '../components/EmailRuleTestResults.vue'

const route = useRoute()
const router = useRouter()

const email = ref(null)
const loading = ref(true)
const error = ref(null)

// Rule testing state
const showTestResults = ref(false)
const testResults = ref(null)
const testingRules = ref(false)
const testError = ref(null)

/**
 * Fetch email details from the API
 */
const fetchEmail = async () => {
  try {
    loading.value = true
    error.value = null

    const emailId = route.params.id
    if (!emailId) {
      throw new Error('Email ID is required')
    }

    const response = await axios.get(`/api/emails/${emailId}`)
    email.value = response.data.data

    // Mark as read after 1 second delay (prevents marking as read on accidental clicks)
    if (email.value && !email.value.is_read) {
      setTimeout(async () => {
        // Double-check email is still unread before marking
        if (email.value && !email.value.is_read) {
          try {
            await markAsRead(emailId, true)
            email.value.is_read = true
          } catch (err) {
            // Silently fail - not critical
          }
        }
      }, 1000)
    }
  } catch (err) {
    if (err.response?.status === 404) {
      error.value = 'Email not found. It may have been deleted.'
    } else if (err.response?.status === 401) {
      error.value = 'You are not authorized to view this email.'
    } else {
      error.value = err.message || 'An unexpected error occurred while loading the email.'
    }
  } finally {
    loading.value = false
  }
}

/**
 * Mark email as read/unread
 */
const markAsRead = async (emailId, isRead) => {
  try {
    await axios.patch(`/api/emails/${emailId}/read`, {
      is_read: isRead
    })
  } catch (err) {
    // Re-throw to allow caller to handle
    throw err
  }
}

/**
 * Handle read/unread toggle from header
 */
const handleToggleRead = async (isRead) => {
  try {
    const emailId = route.params.id
    await markAsRead(emailId, isRead)

    if (email.value) {
      email.value.is_read = isRead
    }
  } catch (err) {
    // Silently fail or show toast notification
  }
}

/**
 * Navigate back to previous page or emails list
 */
const goBack = () => {
  // Check if there's a previous page in history
  if (window.history.length > 1) {
    router.go(-1)
  } else {
    // Default to emails list
    router.push({ name: 'Emails' })
  }
}

/**
 * Test email rules in dry-run mode
 */
const testEmailRules = async () => {
  try {
    testingRules.value = true
    testError.value = null
    testResults.value = null
    showTestResults.value = true

    const emailId = route.params.id
    if (!emailId) {
      throw new Error('Email ID is required')
    }

    const response = await axios.post(`/api/emails/${emailId}/test-rules`)
    testResults.value = response.data.data

    console.log('Rule test results:', testResults.value)
  } catch (err) {
    if (err.response?.status === 404) {
      testError.value = 'Email not found.'
    } else if (err.response?.status === 401) {
      testError.value = 'You are not authorized to test rules on this email.'
    } else if (err.response?.data?.message === 'No active rules to test') {
      testError.value = 'You don\'t have any active rules to test against this email.'
    } else if (!err.response) {
      testError.value = 'Network error. Please check your connection and try again.'
    } else {
      testError.value = err.response?.data?.error || err.message || 'Failed to test rules. Please try again.'
    }
    console.error('Failed to test email rules:', err)
  } finally {
    testingRules.value = false
  }
}

/**
 * Handle retest request from modal
 */
const handleRetest = () => {
  testEmailRules()
}

onMounted(() => {
  fetchEmail()
})
</script>
