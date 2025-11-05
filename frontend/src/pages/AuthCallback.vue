<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from '../axios'

const router = useRouter()

onMounted(async () => {
  // Extract token from URL hash
  const hash = window.location.hash
  const params = new URLSearchParams(hash.split('?')[1])
  const token = params.get('token')

  if (token) {
    // Store token in localStorage
    localStorage.setItem('auth_token', token)

    // Set token in axios defaults
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`

    // Redirect to dashboard
    router.push('/dashboard')
  } else {
    // No token, redirect to login with error
    router.push('/?error=no_token')
  }
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 flex items-center justify-center">
    <div class="text-center">
      <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-gray-900 mx-auto"></div>
      <p class="mt-4 text-gray-600">Completing authentication...</p>
    </div>
  </div>
</template>
