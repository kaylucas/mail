<script setup>
import { ref } from 'vue'

const loading = ref(false)

const handleMicrosoftLogin = () => {
  loading.value = true
  // Redirect to backend OAuth endpoint (not frontend route)
  // VITE_API_URL is set in frontend/.env (e.g., http://mail.loc)
  const apiUrl = import.meta.env.VITE_API_URL || 'http://mail.loc'
  window.location.href = `${apiUrl}/auth/microsoft`
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 px-4">
    <div class="max-w-md w-full bg-white rounded-lg shadow-md p-8 space-y-6">
      <!-- Logo/Header Area -->
      <div class="text-center">
        <h1 class="text-3xl font-bold text-gray-900">Welcome</h1>
        <p class="mt-2 text-sm text-gray-600">
          Sign in with your Microsoft account to continue
        </p>
      </div>

      <!-- Microsoft Login Button -->
      <button
        @click="handleMicrosoftLogin"
        :disabled="loading"
        class="w-full flex items-center justify-center gap-3 bg-white border-2 border-gray-300 hover:border-gray-400 text-gray-700 font-semibold py-3 px-4 rounded-lg transition duration-150 shadow-sm hover:shadow disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <svg v-if="!loading" class="w-5 h-5" viewBox="0 0 23 23" fill="none">
          <rect width="11" height="11" fill="#F25022"/>
          <rect y="12" width="11" height="11" fill="#00A4EF"/>
          <rect x="12" width="11" height="11" fill="#7FBA00"/>
          <rect x="12" y="12" width="11" height="11" fill="#FFB900"/>
        </svg>
        <svg v-else class="animate-spin h-5 w-5 text-gray-700" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span>{{ loading ? 'Redirecting...' : 'Sign in with Microsoft' }}</span>
      </button>

      <!-- Optional: App Description -->
      <div class="text-center text-xs text-gray-500 mt-6">
        <p>Secure authentication powered by Microsoft</p>
      </div>
    </div>
  </div>
</template>
