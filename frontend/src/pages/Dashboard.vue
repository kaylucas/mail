<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import { Dialog, DialogPanel, DialogTitle, TransitionRoot, TransitionChild } from '@headlessui/vue'

const router = useRouter()
const user = ref(null)
const connection = ref(null)
const loading = ref(true)
const showReconnectDialog = ref(false)
const successMessage = ref('')
const errorMessage = ref('')

const isConnected = computed(() => connection.value !== null)
const isTokenExpired = computed(() => {
  if (!connection.value || !connection.value.token_expires_at) return false
  return new Date(connection.value.token_expires_at) < new Date()
})

const fetchUserData = async () => {
  try {
    loading.value = true
    const response = await axios.get('/api/user')
    user.value = response.data
    connection.value = response.data.office365_connection
  } catch (error) {
    console.error('Failed to fetch user data:', error)
    errorMessage.value = 'Failed to load user data'
  } finally {
    loading.value = false
  }
}

const handleLogout = async () => {
  try {
    await axios.post('/api/logout')
    // Clear token from localStorage
    localStorage.removeItem('auth_token')
    delete axios.defaults.headers.common['Authorization']
    router.push({ name: 'Login' })
  } catch (error) {
    console.error('Logout failed:', error)
    // Even if logout fails, clear token and redirect
    localStorage.removeItem('auth_token')
    delete axios.defaults.headers.common['Authorization']
    router.push({ name: 'Login' })
  }
}

const reconnectMicrosoft = () => {
  window.location.href = '/auth/microsoft'
}

const deleteConnection = async () => {
  if (!confirm('Are you sure you want to disconnect your Microsoft 365 account?')) {
    return
  }

  try {
    await axios.delete('/api/office365/connections')
    successMessage.value = 'Successfully disconnected from Microsoft 365'
    showReconnectDialog.value = false
    await fetchUserData()
  } catch (error) {
    console.error('Failed to delete connection:', error)
    errorMessage.value = 'Failed to disconnect'
  }
}

onMounted(() => {
  fetchUserData()

  // Check for success/error in URL
  const urlParams = new URLSearchParams(window.location.hash.split('?')[1])
  if (urlParams.has('success')) {
    successMessage.value = 'Successfully connected to Microsoft 365'
  } else if (urlParams.has('error')) {
    errorMessage.value = 'Authentication failed. Please try again.'
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm border-b">
      <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
        <div class="flex justify-between items-center">
          <div class="flex items-center gap-4">
            <h1 class="text-2xl font-bold text-gray-900">{{ user?.name || 'Dashboard' }}</h1>
          </div>
          <div class="flex items-center gap-4">
            <div v-if="user" class="flex items-center gap-3">
              <img v-if="user.avatar" :src="user.avatar" alt="Avatar" class="w-8 h-8 rounded-full" />
              <div v-else class="w-8 h-8 rounded-full bg-indigo-600 flex items-center justify-center text-white font-semibold">
                {{ user.name?.[0]?.toUpperCase() }}
              </div>
              <span class="text-sm text-gray-700">{{ user.email }}</span>
            </div>
            <button
              @click="handleLogout"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition"
            >
              Logout
            </button>
          </div>
        </div>
      </div>
    </header>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <svg class="animate-spin h-8 w-8 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>

      <!-- Content -->
      <div v-else class="space-y-6">
        <!-- Success Message -->
        <div v-if="successMessage" class="bg-green-50 border border-green-200 rounded-lg p-4">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
            <p class="text-sm text-green-800">{{ successMessage }}</p>
          </div>
        </div>

        <!-- Error Message -->
        <div v-if="errorMessage" class="bg-red-50 border border-red-200 rounded-lg p-4">
          <div class="flex items-center gap-2">
            <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
            <p class="text-sm text-red-800">{{ errorMessage }}</p>
          </div>
        </div>

        <!-- Welcome Section -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h2 class="text-xl font-semibold text-gray-900 mb-2">
            Welcome back, {{ user?.name }}!
          </h2>
          <p class="text-gray-600">
            Manage your Microsoft 365 connection and settings.
          </p>
        </div>

        <!-- Connection Status Card -->
        <div class="bg-white rounded-lg shadow-md p-6">
          <h3 class="text-lg font-semibold text-gray-900 mb-4">Microsoft 365 Connection</h3>

          <!-- Connected and Valid -->
          <div v-if="isConnected && !isTokenExpired" class="space-y-4">
            <div class="flex items-start gap-3">
              <div class="flex-shrink-0 w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
              </div>
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-900">Connected to Microsoft 365</p>
                <p class="text-sm text-gray-500 mt-1">{{ user?.email }}</p>
                <p v-if="connection?.token_expires_at" class="text-xs text-gray-400 mt-1">
                  Token expires: {{ new Date(connection.token_expires_at).toLocaleString() }}
                </p>
              </div>
            </div>
            <div class="flex gap-3 mt-4">
              <button
                @click="reconnectMicrosoft"
                class="px-4 py-2 text-sm font-medium text-indigo-600 bg-indigo-50 rounded-lg hover:bg-indigo-100 transition"
              >
                Reconnect
              </button>
              <button
                @click="deleteConnection"
                class="px-4 py-2 text-sm font-medium text-red-600 bg-red-50 rounded-lg hover:bg-red-100 transition"
              >
                Disconnect
              </button>
            </div>
          </div>

          <!-- Connected but Expired -->
          <div v-else-if="isConnected && isTokenExpired" class="space-y-4">
            <div class="flex items-start gap-3">
              <div class="flex-shrink-0 w-10 h-10 bg-yellow-100 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
              </div>
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-900">Connection expired</p>
                <p class="text-sm text-gray-500 mt-1">Your Microsoft 365 token has expired. Please reconnect.</p>
              </div>
            </div>
            <button
              @click="reconnectMicrosoft"
              class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition"
            >
              Reconnect
            </button>
          </div>

          <!-- Not Connected -->
          <div v-else class="space-y-4">
            <div class="flex items-start gap-3">
              <div class="flex-shrink-0 w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
              </div>
              <div class="flex-1">
                <p class="text-sm font-medium text-gray-900">Not connected</p>
                <p class="text-sm text-gray-500 mt-1">Connect your Microsoft 365 account to get started.</p>
              </div>
            </div>
            <button
              @click="reconnectMicrosoft"
              class="px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition"
            >
              Connect to Microsoft 365
            </button>
          </div>
        </div>
      </div>
    </main>
  </div>
</template>
