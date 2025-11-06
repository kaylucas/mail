<template>
  <Transition name="slide-down">
    <div v-if="isVisible" class="fixed top-0 left-0 right-0 z-50">
      <!-- Syncing State -->
      <div
        v-if="syncStatus === 'syncing'"
        class="px-4 py-3 md:px-6 md:py-4 bg-blue-600 shadow-lg backdrop-blur-sm"
        role="status"
        aria-live="polite"
        aria-atomic="true"
      >
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4 flex-wrap md:flex-nowrap">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <ArrowPathIcon class="h-5 w-5 md:h-6 md:w-6 text-white animate-spin flex-shrink-0" />
            <div class="flex-1 min-w-0">
              <p class="font-medium text-sm md:text-base text-white">
                Syncing your emails...
              </p>
              <p v-if="progress" class="text-xs md:text-sm text-white/90 mt-0.5">
                <template v-if="progress.folders_synced || progress.messages_synced">
                  <span v-if="progress.folders_synced">{{ progress.folders_synced }} folders</span>
                  <span v-if="progress.folders_synced && progress.messages_synced"> · </span>
                  <span v-if="progress.messages_synced">{{ progress.messages_synced }} messages</span>
                </template>
                <template v-else>
                  Starting sync...
                </template>
              </p>
            </div>
          </div>
          <button
            @click="closeBanner"
            class="p-1.5 rounded-lg hover:bg-white/10 active:bg-white/20 transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-white/50 flex-shrink-0"
            aria-label="Close sync notification"
          >
            <XMarkIcon class="h-5 w-5 text-white" />
          </button>
        </div>
      </div>

      <!-- Completed State -->
      <div
        v-else-if="syncStatus === 'completed'"
        class="px-4 py-3 md:px-6 md:py-4 bg-green-600 shadow-lg backdrop-blur-sm"
        role="status"
        aria-live="polite"
        aria-atomic="true"
      >
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4 flex-wrap md:flex-nowrap">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <CheckCircleIcon class="h-5 w-5 md:h-6 md:w-6 text-white flex-shrink-0" />
            <div class="flex-1 min-w-0">
              <p class="font-medium text-sm md:text-base text-white">
                Sync complete! {{ syncedCount }} emails synced
              </p>
            </div>
          </div>
          <button
            @click="closeBanner"
            class="p-1.5 rounded-lg hover:bg-white/10 active:bg-white/20 transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-white/50 flex-shrink-0"
            aria-label="Close sync notification"
          >
            <XMarkIcon class="h-5 w-5 text-white" />
          </button>
        </div>
      </div>

      <!-- Failed State -->
      <div
        v-else-if="syncStatus === 'failed'"
        class="px-4 py-3 md:px-6 md:py-4 bg-red-600 shadow-lg backdrop-blur-sm"
        role="alert"
        aria-live="assertive"
        aria-atomic="true"
      >
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4 flex-wrap md:flex-nowrap">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <ExclamationCircleIcon class="h-5 w-5 md:h-6 md:w-6 text-white flex-shrink-0" />
            <div class="flex-1 min-w-0">
              <p class="font-medium text-sm md:text-base text-white">
                Email sync failed
              </p>
              <p v-if="errorMessage" class="text-xs md:text-sm text-white/90 mt-0.5 truncate">
                {{ errorMessage }}
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0">
            <button
              @click="retrySync"
              class="px-3 py-1.5 md:px-4 md:py-2 bg-white text-red-600 rounded-lg font-medium text-sm hover:bg-red-50 active:bg-red-100 transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-white focus:ring-offset-2 focus:ring-offset-red-600 flex-shrink-0"
            >
              Retry
            </button>
            <button
              @click="closeBanner"
              class="p-1.5 rounded-lg hover:bg-white/10 active:bg-white/20 transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-white/50 flex-shrink-0"
              aria-label="Close error notification"
            >
              <XMarkIcon class="h-5 w-5 text-white" />
            </button>
          </div>
        </div>
      </div>

      <!-- Long Running State (after 5 minutes) -->
      <div
        v-else-if="syncStatus === 'long_running'"
        class="px-4 py-3 md:px-6 md:py-4 bg-yellow-500 shadow-lg backdrop-blur-sm"
        role="status"
        aria-live="polite"
        aria-atomic="true"
      >
        <div class="max-w-7xl mx-auto flex items-center justify-between gap-4 flex-wrap md:flex-nowrap">
          <div class="flex items-center gap-3 flex-1 min-w-0">
            <ClockIcon class="h-5 w-5 md:h-6 md:w-6 text-gray-900 flex-shrink-0" />
            <div class="flex-1 min-w-0">
              <p class="font-medium text-sm md:text-base text-gray-900">
                This is taking longer than expected...
              </p>
              <p class="text-xs md:text-sm text-gray-900/90 mt-0.5">
                We're still syncing your emails. This may take a while for large inboxes.
              </p>
            </div>
          </div>
          <button
            @click="closeBanner"
            class="p-1.5 rounded-lg hover:bg-gray-900/10 active:bg-gray-900/20 transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-gray-900/50 flex-shrink-0"
            aria-label="Close sync notification"
          >
            <XMarkIcon class="h-5 w-5 text-gray-900" />
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import {
  ArrowPathIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  XMarkIcon,
  ClockIcon
} from '@heroicons/vue/24/outline'
import axios from '../axios.js'

// Component state
const syncStatus = ref('idle')
const isVisible = ref(false)
const progress = ref(null)
const syncedCount = ref(0)
const errorMessage = ref('')
const syncStartTime = ref(null)
const LONG_RUNNING_THRESHOLD = 5 * 60 * 1000 // 5 minutes in milliseconds

// Polling state
let pollInterval = null
const POLL_INTERVAL = 2500 // Poll every 2.5 seconds

/**
 * Check the current sync status from the backend
 */
async function checkSyncStatus() {
  try {
    const response = await axios.get('/api/emails/sync/status')
    const data = response.data

    // Handle different status states
    if (data.status === 'syncing') {
      handleSyncingState(data)
    } else if (data.status === 'completed') {
      handleCompletedState(data)
    } else if (data.status === 'failed') {
      handleFailedState(data)
    } else {
      // Idle state - hide banner
      handleIdleState(data)
    }
  } catch (error) {
    console.error('Failed to check sync status:', error)

    // Only show error if we were actively syncing
    if (syncStatus.value === 'syncing') {
      errorMessage.value = 'Could not connect to server. Check your connection.'
      syncStatus.value = 'failed'
      isVisible.value = true
      stopPolling()
    }
  }
}

/**
 * Handle syncing state
 */
function handleSyncingState(data) {
  syncStatus.value = 'syncing'
  isVisible.value = true
  progress.value = data.progress || null

  // Track when sync started for long-running detection
  if (!syncStartTime.value && data.started_at) {
    syncStartTime.value = new Date(data.started_at)
  }

  // Check if sync has been running for too long
  if (syncStartTime.value) {
    const elapsedTime = Date.now() - syncStartTime.value.getTime()
    if (elapsedTime > LONG_RUNNING_THRESHOLD) {
      syncStatus.value = 'long_running'
    }
  }

  // Continue polling if not already polling
  if (!pollInterval) {
    startPolling()
  }
}

/**
 * Handle completed state
 */
function handleCompletedState(data) {
  syncStatus.value = 'completed'
  isVisible.value = true
  syncedCount.value = data.synced_count || 0
  syncStartTime.value = null
  stopPolling()

  // Auto-dismiss after 5 seconds
  setTimeout(() => {
    if (syncStatus.value === 'completed') {
      isVisible.value = false
    }
  }, 5000)
}

/**
 * Handle failed state
 */
function handleFailedState(data) {
  syncStatus.value = 'failed'
  isVisible.value = true
  errorMessage.value = data.error || 'Unknown error occurred'
  syncStartTime.value = null
  stopPolling()
}

/**
 * Handle idle state (no sync in progress)
 */
function handleIdleState(data) {
  // Don't show banner for users who already have emails
  if (data.has_emails) {
    isVisible.value = false
    stopPolling()
  } else {
    // New user with no emails - don't show anything yet
    isVisible.value = false
    stopPolling()
  }

  syncStartTime.value = null
}

/**
 * Start polling for sync status
 */
function startPolling() {
  if (pollInterval) return
  pollInterval = setInterval(checkSyncStatus, POLL_INTERVAL)
}

/**
 * Stop polling for sync status
 */
function stopPolling() {
  if (pollInterval) {
    clearInterval(pollInterval)
    pollInterval = null
  }
}

/**
 * Close the banner manually
 */
function closeBanner() {
  isVisible.value = false
  stopPolling()

  // Reset state
  syncStartTime.value = null
  progress.value = null
}

/**
 * Retry a failed sync
 */
async function retrySync() {
  // Reset state and start checking again
  errorMessage.value = ''
  syncStatus.value = 'syncing'
  isVisible.value = true
  syncStartTime.value = new Date()

  // Start polling immediately
  await checkSyncStatus()
  startPolling()
}

// Lifecycle hooks
onMounted(() => {
  // Check status once on mount
  checkSyncStatus()
})

onUnmounted(() => {
  // Clean up polling when component is destroyed
  stopPolling()
})
</script>

<style scoped>
/* Slide-down transition animation with smooth cubic-bezier curves */
.slide-down-enter-active,
.slide-down-leave-active {
  transition: transform 300ms cubic-bezier(0.4, 0, 0.2, 1),
              opacity 300ms cubic-bezier(0.4, 0, 0.2, 1);
}

.slide-down-enter-from {
  transform: translateY(-100%);
  opacity: 0;
}

.slide-down-leave-to {
  transform: translateY(-100%);
  opacity: 0;
}

.slide-down-enter-to,
.slide-down-leave-from {
  transform: translateY(0);
  opacity: 1;
}
</style>
