<template>
  <button
    @click="toggleTheme"
    class="flex items-center justify-between w-full px-4 py-2 text-sm text-gray-700 dark:text-gray-200 hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors"
    role="menuitem"
  >
    <span class="flex items-center gap-2">
      <SunIcon v-if="isDark" class="h-5 w-5 text-gray-400" />
      <MoonIcon v-else class="h-5 w-5 text-gray-400" />
      <span>{{ isDark ? 'Light Mode' : 'Dark Mode' }}</span>
    </span>
  </button>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { SunIcon, MoonIcon } from '@heroicons/vue/24/outline'

const isDark = ref(false)

const toggleTheme = () => {
  isDark.value = !isDark.value
  updateTheme()
}

const updateTheme = () => {
  if (isDark.value) {
    document.documentElement.classList.add('dark')
    localStorage.setItem('theme', 'dark')
  } else {
    document.documentElement.classList.remove('dark')
    localStorage.setItem('theme', 'light')
  }
}

onMounted(() => {
  // Check for saved theme or system preference
  const savedTheme = localStorage.getItem('theme')
  const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches

  if (savedTheme === 'dark' || (!savedTheme && systemDark)) {
    isDark.value = true
  } else {
    isDark.value = false
  }
  
  updateTheme()
})
</script>
