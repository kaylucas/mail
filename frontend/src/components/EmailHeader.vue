<template>
  <header class="bg-white border-b border-gray-200 shadow-sm">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
      <!-- Back Button Row -->
      <div class="mb-6">
        <button
          @click="$emit('back')"
          class="inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-indigo-600 transition-all duration-150 hover:gap-3 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 rounded-lg px-3 py-2 -ml-3"
        >
          <ArrowLeftIcon class="h-5 w-5" />
          Back to Inbox
        </button>
      </div>

      <!-- Subject Line -->
      <h1 class="text-3xl font-bold text-gray-900 mb-6 leading-tight">
        {{ email.subject || '(No subject)' }}
      </h1>

      <!-- Email Metadata -->
      <div class="space-y-4">
        <!-- From -->
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0 w-12 h-12 rounded-full bg-gradient-to-br from-indigo-500 to-indigo-600 flex items-center justify-center text-white font-bold text-base shadow-md ring-2 ring-white">
            {{ getInitials(email.from_name) }}
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center justify-between gap-4 flex-wrap">
              <div class="min-w-0 flex-1">
                <p class="text-base font-bold text-gray-900">{{ email.from_name }}</p>
                <p class="text-sm text-gray-600 truncate mt-0.5">{{ email.from_email }}</p>
              </div>
              <div class="flex-shrink-0 text-sm font-medium text-gray-500">
                {{ formatDateTime(email.received_date_time) }}
              </div>
            </div>
          </div>
        </div>

        <!-- Recipients -->
        <div class="pl-16 space-y-2">
          <!-- To -->
          <div v-if="email.to_recipients" class="text-sm">
            <span class="font-semibold text-gray-700">To:</span>
            <span class="text-gray-600 ml-2">{{ formatRecipients(email.to_recipients) }}</span>
          </div>

          <!-- CC -->
          <div v-if="email.cc_recipients" class="text-sm">
            <span class="font-semibold text-gray-700">Cc:</span>
            <span class="text-gray-600 ml-2">{{ formatRecipients(email.cc_recipients) }}</span>
          </div>

          <!-- BCC -->
          <div v-if="email.bcc_recipients" class="text-sm">
            <span class="font-semibold text-gray-700">Bcc:</span>
            <span class="text-gray-600 ml-2">{{ formatRecipients(email.bcc_recipients) }}</span>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="mt-6 flex items-center gap-3 pt-6 border-t border-gray-200">
        <!-- Mark as Read/Unread -->
        <button
          @click="toggleReadStatus"
          class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium rounded-lg transition-all duration-150 shadow-sm hover:shadow focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          :class="email.is_read
            ? 'text-gray-700 bg-white border border-gray-300 hover:bg-gray-50 hover:border-gray-400'
            : 'text-white bg-indigo-600 hover:bg-indigo-700'
          "
        >
          <EnvelopeIcon v-if="email.is_read" class="h-5 w-5" />
          <EnvelopeOpenIcon v-else class="h-5 w-5" />
          {{ email.is_read ? 'Mark Unread' : 'Mark Read' }}
        </button>

        <!-- Reply Button (UI only) -->
        <button
          disabled
          class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-400 bg-white border border-gray-200 rounded-lg cursor-not-allowed opacity-60"
          title="Reply (Coming soon)"
        >
          <ArrowUturnLeftIcon class="h-5 w-5" />
          Reply
        </button>

        <!-- Forward Button (UI only) -->
        <button
          disabled
          class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium text-gray-400 bg-white border border-gray-200 rounded-lg cursor-not-allowed opacity-60"
          title="Forward (Coming soon)"
        >
          <ArrowUturnRightIcon class="h-5 w-5" />
          Forward
        </button>

        <!-- Importance Badge -->
        <div v-if="email.importance && email.importance !== 'normal'" class="ml-auto">
          <span
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold shadow-sm"
            :class="{
              'bg-red-100 text-red-700 ring-1 ring-red-200': email.importance === 'high',
              'bg-gray-100 text-gray-700 ring-1 ring-gray-200': email.importance === 'low'
            }"
          >
            <ExclamationCircleIcon v-if="email.importance === 'high'" class="h-4 w-4" />
            {{ email.importance.charAt(0).toUpperCase() + email.importance.slice(1) }} Priority
          </span>
        </div>
      </div>
    </div>
  </header>
</template>

<script setup>
import { ArrowLeftIcon, EnvelopeIcon, EnvelopeOpenIcon, ArrowUturnLeftIcon, ArrowUturnRightIcon, ExclamationCircleIcon } from '@heroicons/vue/24/outline'
import { formatFullDateTime, getInitials } from '../utils/dateFormat'

/**
 * Component props
 * @typedef {Object} Props
 * @property {Object} email - Email object containing metadata
 * @property {number} email.id - Email ID
 * @property {string} email.subject - Email subject
 * @property {string} email.from_name - Sender name
 * @property {string} email.from_email - Sender email
 * @property {string} email.to_recipients - To recipients (string or array)
 * @property {string} [email.cc_recipients] - CC recipients (string or array)
 * @property {string} [email.bcc_recipients] - BCC recipients (string or array)
 * @property {string} email.received_date_time - Received date/time (ISO 8601)
 * @property {boolean} email.is_read - Read status
 * @property {string} [email.importance] - Email importance (normal, high, low)
 */
const props = defineProps({
  email: {
    type: Object,
    required: true
  }
})

/**
 * Component emits
 * @event toggle-read - Emitted when read status should be toggled
 * @param {boolean} isRead - New read status
 * @event back - Emitted when back button is clicked
 */
const emit = defineEmits(['toggle-read', 'back'])

/**
 * Format date/time for display
 */
const formatDateTime = (dateString) => {
  if (!dateString) return ''
  return formatFullDateTime(dateString)
}

/**
 * Format recipients for display
 * Handles both string and array formats
 */
const formatRecipients = (recipients) => {
  if (!recipients) return ''

  // If it's already a string, return it
  if (typeof recipients === 'string') {
    return recipients
  }

  // If it's an array, format each recipient
  if (Array.isArray(recipients)) {
    return recipients
      .map(r => {
        if (typeof r === 'string') return r
        return r.name ? `${r.name} <${r.email}>` : r.email
      })
      .join(', ')
  }

  return ''
}

/**
 * Toggle read/unread status
 */
const toggleReadStatus = () => {
  emit('toggle-read', !props.email.is_read)
}
</script>
