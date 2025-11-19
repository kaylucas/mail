<template>
  <div class="h-screen flex overflow-hidden bg-gray-50 dark:bg-gray-900">
    <!-- Mobile menu button -->
    <div class="lg:hidden fixed top-0 left-0 right-0 z-50 bg-white border-b border-gray-200 px-4 py-3 shadow-sm">
      <button
        @click="toggleMobileMenu"
        type="button"
        class="inline-flex items-center justify-center p-2 rounded-lg text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
      >
        <span class="sr-only">Open sidebar</span>
        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
        </svg>
      </button>
    </div>

    <!-- Sidebar Navigation -->
    <AppNavigation
      :is-mobile-open="mobileMenuOpen"
      @close="closeMobileMenu"
    />

    <!-- Main content area -->
    <div class="flex-1 flex flex-col overflow-hidden lg:pl-0 pt-16 lg:pt-0">
      <slot />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import AppNavigation from '../components/AppNavigation.vue'

const mobileMenuOpen = ref(false)

const toggleMobileMenu = () => {
  mobileMenuOpen.value = !mobileMenuOpen.value
}

const closeMobileMenu = () => {
  mobileMenuOpen.value = false
}
</script>
