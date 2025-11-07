<template>
  <AppLayout>
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Main Content -->
      <div class="flex-1 overflow-y-auto">
        <main class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
          <!-- Page Header -->
          <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Settings</h1>
            <p class="mt-1 text-sm text-gray-600">
              Manage your email sync and Microsoft account settings
            </p>
          </div>

          <!-- Success/Error Messages -->
          <div v-if="successMessage" class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
            <div class="flex items-center gap-2">
              <CheckCircleIcon class="h-5 w-5 text-green-600 flex-shrink-0" />
              <p class="text-sm text-green-800">{{ successMessage }}</p>
            </div>
          </div>

          <div v-if="errorMessage" class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
            <div class="flex items-start gap-2">
              <ExclamationCircleIcon class="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
              <div class="flex-1">
                <p class="text-sm text-red-800">{{ errorMessage }}</p>
                <p v-if="rateLimitRetryAfter" class="text-xs text-red-700 mt-1">
                  Please try again in {{ rateLimitRetryAfter }} seconds
                </p>
              </div>
            </div>
          </div>

          <!-- Email Sync Section -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="px-6 py-5 border-b border-gray-200">
              <h2 class="text-lg font-semibold text-gray-900">Email Sync</h2>
              <p class="mt-1 text-sm text-gray-600">
                Manage your email synchronization settings
              </p>
            </div>

            <div class="px-6 py-5 space-y-6">
              <!-- Sync Status -->
              <div>
                <div class="flex items-center justify-between mb-4">
                  <span class="text-sm font-medium text-gray-700">Current Status</span>
                  
                  <!-- Status Badge -->
                  <span 
                    v-if="syncStatus === 'syncing'" 
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-blue-50 text-blue-700 border border-blue-200"
                  >
                    <ArrowPathIcon class="h-4 w-4 animate-spin" />
                    Syncing...
                  </span>
                  <span 
                    v-else-if="syncStatus === 'completed'" 
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-green-50 text-green-700 border border-green-200"
                  >
                    <CheckCircleIcon class="h-4 w-4" />
                    Up to date
                  </span>
                  <span 
                    v-else-if="syncStatus === 'failed'" 
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-red-50 text-red-700 border border-red-200"
                  >
                    <ExclamationCircleIcon class="h-4 w-4" />
                    Failed
                  </span>
                  <span 
                    v-else 
                    class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full text-xs font-medium bg-gray-50 text-gray-700 border border-gray-200"
                  >
                    <InformationCircleIcon class="h-4 w-4" />
                    {{ user?.last_email_sync_at ? 'Idle' : 'Never synced' }}
                  </span>
                </div>

                <!-- Progress Info (when syncing) -->
                <div v-if="syncStatus === 'syncing' && progress" class="bg-blue-50 rounded-lg p-4 mb-4">
                  <div class="flex items-center gap-3">
                    <div class="flex-1">
                      <p class="text-sm font-medium text-blue-900">Sync in progress</p>
                      <div class="flex items-center gap-4 mt-2 text-sm text-blue-700">
                        <span v-if="progress.folders_synced">
                          {{ progress.folders_synced }} folders
                        </span>
                        <span v-if="progress.messages_synced">
                          {{ progress.messages_synced }} messages
                        </span>
                      </div>
                    </div>
                  </div>
                </div>

                <!-- Sync Stats -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                  <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">Last Sync</span>
                      <ClockIcon class="h-5 w-5 text-gray-400" />
                    </div>
                    <p class="mt-2 text-lg font-semibold text-gray-900">
                      {{ lastSyncFormatted }}
                    </p>
                  </div>

                  <div class="bg-gray-50 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                      <span class="text-sm text-gray-600">Total Emails</span>
                      <EnvelopeIcon class="h-5 w-5 text-gray-400" />
                    </div>
                    <p class="mt-2 text-lg font-semibold text-gray-900">
                      {{ emailStats?.total_emails?.toLocaleString() || '0' }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Sync Actions -->
              <div class="pt-4 border-t border-gray-200">
                <button
                  @click="triggerSync"
                  :disabled="isSyncing || rateLimitRetryAfter > 0"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  <ArrowPathIcon :class="['h-5 w-5', isSyncing ? 'animate-spin' : '']" />
                  {{ isSyncing ? 'Syncing...' : 'Sync Emails Now' }}
                </button>
                <p class="mt-2 text-xs text-gray-500">
                  Rate limit: 5 syncs per hour
                </p>
              </div>
            </div>
          </div>

          <!-- Email Rules Section -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
            <div class="px-6 py-5 border-b border-gray-200">
              <h2 class="text-lg font-semibold text-gray-900">Email Rules</h2>
              <p class="mt-1 text-sm text-gray-600">
                Configure AI-powered rules to automatically process and organize your emails
              </p>
            </div>

            <div class="px-6 py-5">
              <EmailRulesList />
            </div>
          </div>

          <!-- Microsoft Account Section -->
          <div class="bg-white rounded-lg shadow-sm border border-gray-200">
            <div class="px-6 py-5 border-b border-gray-200">
              <h2 class="text-lg font-semibold text-gray-900">Microsoft Account</h2>
              <p class="mt-1 text-sm text-gray-600">
                Manage your Microsoft 365 connection
              </p>
            </div>

            <div class="px-6 py-5 space-y-6">
              <!-- Connection Status -->
              <div>
                <div class="flex items-start gap-4">
                  <!-- Status Icon -->
                  <div 
                    :class="[
                      'flex-shrink-0 w-12 h-12 rounded-full flex items-center justify-center',
                      isConnected && !isTokenExpired ? 'bg-green-100' : 
                      isConnected && isTokenExpired ? 'bg-yellow-100' : 
                      'bg-gray-100'
                    ]"
                  >
                    <CheckCircleIcon 
                      v-if="isConnected && !isTokenExpired"
                      class="h-6 w-6 text-green-600" 
                    />
                    <ExclamationTriangleIcon
                      v-else-if="isConnected && isTokenExpired"
                      class="h-6 w-6 text-yellow-600"
                    />
                    <InformationCircleIcon
                      v-else
                      class="h-6 w-6 text-gray-600"
                    />
                  </div>

                  <!-- Status Details -->
                  <div class="flex-1 min-w-0">
                    <h3 class="text-base font-medium text-gray-900">
                      {{ isConnected ? 'Connected' : 'Not Connected' }}
                    </h3>
                    
                    <div v-if="isConnected" class="mt-2 space-y-2">
                      <div class="flex items-center gap-2 text-sm text-gray-600">
                        <UserCircleIcon class="h-4 w-4 flex-shrink-0" />
                        <span class="truncate">{{ user?.name }}</span>
                      </div>
                      <div class="flex items-center gap-2 text-sm text-gray-600">
                        <EnvelopeIcon class="h-4 w-4 flex-shrink-0" />
                        <span class="truncate">{{ user?.email }}</span>
                      </div>
                      
                      <!-- Token Expiry Warning -->
                      <div v-if="tokenExpiresSoon" class="flex items-start gap-2 mt-3 p-3 bg-yellow-50 border border-yellow-200 rounded-lg">
                        <ExclamationTriangleIcon class="h-5 w-5 text-yellow-600 flex-shrink-0 mt-0.5" />
                        <div class="flex-1 min-w-0">
                          <p class="text-sm font-medium text-yellow-900">Token expiring soon</p>
                          <p class="text-xs text-yellow-700 mt-1">
                            Your access token will expire {{ tokenExpiryFormatted }}. Please reconnect to refresh.
                          </p>
                        </div>
                      </div>

                      <!-- Token Expired -->
                      <div v-else-if="isTokenExpired" class="flex items-start gap-2 mt-3 p-3 bg-red-50 border border-red-200 rounded-lg">
                        <ExclamationCircleIcon class="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                        <div class="flex-1 min-w-0">
                          <p class="text-sm font-medium text-red-900">Token expired</p>
                          <p class="text-xs text-red-700 mt-1">
                            Your access token has expired. Please reconnect to continue syncing emails.
                          </p>
                        </div>
                      </div>

                      <!-- Token Status (Normal) -->
                      <div v-else class="text-xs text-gray-500 mt-2">
                        Token expires {{ tokenExpiryFormatted }}
                      </div>
                    </div>

                    <p v-else class="mt-2 text-sm text-gray-600">
                      Connect your Microsoft 365 account to sync your emails
                    </p>
                  </div>
                </div>
              </div>

              <!-- Connection Actions -->
              <div class="pt-4 border-t border-gray-200 flex flex-wrap gap-3">
                <button
                  v-if="isConnected"
                  @click="reconnectMicrosoft"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                  <ArrowPathIcon class="h-5 w-5" />
                  Reconnect Account
                </button>
                <button
                  v-else
                  @click="reconnectMicrosoft"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
                >
                  <LinkIcon class="h-5 w-5" />
                  Connect to Microsoft
                </button>

                <button
                  v-if="isConnected"
                  @click="showDisconnectDialog = true"
                  class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-red-300 text-red-700 text-sm font-medium rounded-lg hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 transition-colors"
                >
                  <LinkSlashIcon class="h-5 w-5" />
                  Disconnect Account
                </button>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>

    <!-- Disconnect Confirmation Dialog -->
    <TransitionRoot :show="showDisconnectDialog" as="template">
      <Dialog as="div" class="relative z-50" @close="showDisconnectDialog = false">
        <TransitionChild
          as="template"
          enter="ease-out duration-300"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="ease-in duration-200"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity" />
        </TransitionChild>

        <div class="fixed inset-0 z-10 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              as="template"
              enter="ease-out duration-300"
              enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
              enter-to="opacity-100 translate-y-0 sm:scale-100"
              leave="ease-in duration-200"
              leave-from="opacity-100 translate-y-0 sm:scale-100"
              leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
              <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                <div class="sm:flex sm:items-start">
                  <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                    <ExclamationTriangleIcon class="h-6 w-6 text-red-600" />
                  </div>
                  <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                    <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
                      Disconnect Microsoft Account
                    </DialogTitle>
                    <div class="mt-2">
                      <p class="text-sm text-gray-500">
                        Are you sure you want to disconnect your Microsoft 365 account? This will:
                      </p>
                      <ul class="mt-3 space-y-2 text-sm text-gray-600 list-disc list-inside">
                        <li>Stop email synchronization</li>
                        <li>Remove access to your Microsoft emails</li>
                        <li>Require you to reconnect to resume syncing</li>
                      </ul>
                      <p class="mt-3 text-sm font-medium text-gray-700">
                        This action cannot be undone.
                      </p>
                    </div>
                  </div>
                </div>
                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
                  <button
                    type="button"
                    :disabled="disconnecting"
                    @click="confirmDisconnect"
                    class="inline-flex w-full justify-center items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    <span v-if="disconnecting">Disconnecting...</span>
                    <span v-else>Disconnect</span>
                  </button>
                  <button
                    type="button"
                    :disabled="disconnecting"
                    @click="showDisconnectDialog = false"
                    class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    Cancel
                  </button>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionRoot,
  TransitionChild
} from '@headlessui/vue'
import {
  CheckCircleIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon,
  InformationCircleIcon,
  ArrowPathIcon,
  ClockIcon,
  EnvelopeIcon,
  UserCircleIcon,
  LinkIcon,
  LinkSlashIcon
} from '@heroicons/vue/24/outline'
import AppLayout from '../layouts/AppLayout.vue'
import EmailRulesList from '../components/EmailRulesList.vue'
import axios from '../axios'
import { clearAuthState } from '../router/index.js'

const router = useRouter()

// State
const user = ref(null)
const emailStats = ref(null)
const syncStatus = ref('idle')
const progress = ref(null)
const successMessage = ref('')
const errorMessage = ref('')
const rateLimitRetryAfter = ref(0)
const showDisconnectDialog = ref(false)
const disconnecting = ref(false)

// Polling
let syncStatusPollInterval = null
let tokenStatusPollInterval = null
let rateLimitCountdown = null

// Computed properties
const isConnected = computed(() => {
  return user.value?.office365_connection !== null && user.value?.office365_connection !== undefined
})

const isTokenExpired = computed(() => {
  if (!isConnected.value || !user.value.office365_connection?.token_expires_at) {
    return false
  }
  return new Date(user.value.office365_connection.token_expires_at) < new Date()
})

const tokenExpiresSoon = computed(() => {
  if (!isConnected.value || !user.value.office365_connection?.token_expires_at) {
    return false
  }
  const expiresAt = new Date(user.value.office365_connection.token_expires_at)
  const now = new Date()
  const diffMinutes = (expiresAt - now) / 1000 / 60
  return diffMinutes > 0 && diffMinutes < 5 // Less than 5 minutes
})

const tokenExpiryFormatted = computed(() => {
  if (!isConnected.value || !user.value.office365_connection?.token_expires_at) {
    return 'Unknown'
  }
  
  const expiresAt = new Date(user.value.office365_connection.token_expires_at)
  const now = new Date()
  const diffMs = expiresAt - now
  
  if (diffMs < 0) {
    return 'expired'
  }
  
  const diffMinutes = Math.floor(diffMs / 1000 / 60)
  const diffHours = Math.floor(diffMinutes / 60)
  const diffDays = Math.floor(diffHours / 24)
  
  if (diffDays > 0) {
    const label = diffDays > 1 ? 'days' : 'day'
    return 'in ' + diffDays + ' ' + label
  } else if (diffHours > 0) {
    const label = diffHours > 1 ? 'hours' : 'hour'
    return 'in ' + diffHours + ' ' + label
  } else {
    const label = diffMinutes > 1 ? 'minutes' : 'minute'
    return 'in ' + diffMinutes + ' ' + label
  }
})

const lastSyncFormatted = computed(() => {
  if (!user.value?.last_email_sync_at) {
    return 'Never'
  }
  
  const lastSync = new Date(user.value.last_email_sync_at)
  const now = new Date()
  const diffMs = now - lastSync
  const diffMinutes = Math.floor(diffMs / 1000 / 60)
  const diffHours = Math.floor(diffMinutes / 60)
  const diffDays = Math.floor(diffHours / 24)
  
  if (diffMinutes < 1) {
    return 'Just now'
  } else if (diffMinutes < 60) {
    return diffMinutes + ' min ago'
  } else if (diffHours < 24) {
    const label = diffHours > 1 ? 'hours' : 'hour'
    return diffHours + ' ' + label + ' ago'
  } else {
    const label = diffDays > 1 ? 'days' : 'day'
    return diffDays + ' ' + label + ' ago'
  }
})

const isSyncing = computed(() => {
  return syncStatus.value === 'syncing'
})

// Methods
async function fetchUserData() {
  try {
    const response = await axios.get('/api/user')
    user.value = response.data
  } catch (error) {
    console.error('Failed to fetch user data:', error)
    errorMessage.value = 'Failed to load user data'
  }
}

async function fetchEmailStats() {
  try {
    const response = await axios.get('/api/emails/stats')
    emailStats.value = response.data.data
  } catch (error) {
    console.error('Failed to fetch email stats:', error)
  }
}

async function fetchSyncStatus() {
  try {
    const response = await axios.get('/api/emails/sync/status')
    const data = response.data
    
    syncStatus.value = data.status || 'idle'
    progress.value = data.progress || null
    
    // If sync completed or failed, stop polling
    if (data.status === 'completed' || data.status === 'failed') {
      stopSyncStatusPolling()
      
      // Refresh user data and stats
      await Promise.all([fetchUserData(), fetchEmailStats()])
      
      if (data.status === 'completed') {
        successMessage.value = 'Sync completed! ' + (data.synced_count || 0) + ' emails synced.'
        clearMessagesAfterDelay()
      } else if (data.status === 'failed') {
        errorMessage.value = data.message || 'Sync failed. Please try again.'
      }
    }
    
    // Start polling if syncing
    if (data.status === 'syncing' && !syncStatusPollInterval) {
      startSyncStatusPolling()
    }
  } catch (error) {
    console.error('Failed to fetch sync status:', error)
  }
}

async function triggerSync() {
  // Clear previous messages
  successMessage.value = ''
  errorMessage.value = ''
  rateLimitRetryAfter.value = 0
  
  try {
    await axios.post('/api/emails/sync/initial')
    
    syncStatus.value = 'syncing'
    progress.value = null
    
    // Start polling for status updates
    startSyncStatusPolling()
    
    successMessage.value = 'Email sync started successfully'
    clearMessagesAfterDelay()
  } catch (error) {
    console.error('Failed to trigger sync:', error)
    
    // Handle rate limiting (429)
    if (error.response?.status === 429) {
      const retryAfter = parseInt(error.response.headers['retry-after'] || '60')
      rateLimitRetryAfter.value = retryAfter
      errorMessage.value = 'Rate limit exceeded. Please try again later.'
      
      // Start countdown
      startRateLimitCountdown()
    } else {
      errorMessage.value = error.response?.data?.message || 'Failed to start sync. Please try again.'
    }
  }
}

function reconnectMicrosoft() {
  // Redirect to Microsoft OAuth flow
  window.location.href = '/auth/microsoft'
}

async function confirmDisconnect() {
  disconnecting.value = true
  errorMessage.value = ''
  successMessage.value = ''
  
  try {
    await axios.delete('/api/office365/connections')
    
    successMessage.value = 'Successfully disconnected from Microsoft 365'
    showDisconnectDialog.value = false
    
    // Logout and redirect to login
    setTimeout(async () => {
      clearAuthState()
      router.push({ name: 'Login' })
    }, 1500)
  } catch (error) {
    console.error('Failed to disconnect:', error)
    errorMessage.value = error.response?.data?.message || 'Failed to disconnect. Please try again.'
    showDisconnectDialog.value = false
  } finally {
    disconnecting.value = false
  }
}

function startSyncStatusPolling() {
  if (syncStatusPollInterval) return
  
  syncStatusPollInterval = setInterval(() => {
    fetchSyncStatus()
  }, 3000) // Poll every 3 seconds
}

function stopSyncStatusPolling() {
  if (syncStatusPollInterval) {
    clearInterval(syncStatusPollInterval)
    syncStatusPollInterval = null
  }
}

function startTokenStatusPolling() {
  if (tokenStatusPollInterval) return
  
  tokenStatusPollInterval = setInterval(() => {
    fetchUserData()
  }, 30000) // Poll every 30 seconds
}

function stopTokenStatusPolling() {
  if (tokenStatusPollInterval) {
    clearInterval(tokenStatusPollInterval)
    tokenStatusPollInterval = null
  }
}

function startRateLimitCountdown() {
  if (rateLimitCountdown) {
    clearInterval(rateLimitCountdown)
  }
  
  rateLimitCountdown = setInterval(() => {
    rateLimitRetryAfter.value--
    
    if (rateLimitRetryAfter.value <= 0) {
      clearInterval(rateLimitCountdown)
      rateLimitCountdown = null
      errorMessage.value = ''
    }
  }, 1000)
}

function clearMessagesAfterDelay() {
  setTimeout(() => {
    successMessage.value = ''
    errorMessage.value = ''
  }, 5000)
}

// Lifecycle
onMounted(async () => {
  // Initial data fetch
  await Promise.all([
    fetchUserData(),
    fetchEmailStats(),
    fetchSyncStatus()
  ])
  
  // Start polling for token status
  startTokenStatusPolling()
})

onUnmounted(() => {
  stopSyncStatusPolling()
  stopTokenStatusPolling()
  
  if (rateLimitCountdown) {
    clearInterval(rateLimitCountdown)
  }
})
</script>
