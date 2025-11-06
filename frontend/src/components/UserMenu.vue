<template>
  <Menu as="div" class="relative">
    <MenuButton class="w-full flex items-center gap-3 p-2 rounded-lg hover:bg-gray-50 transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
      <!-- User Avatar -->
      <div
        class="flex-shrink-0 h-10 w-10 rounded-full flex items-center justify-center text-white font-semibold text-sm shadow-md ring-2 ring-white"
        :style="{ backgroundColor: avatarColor }"
      >
        {{ initials }}
      </div>

      <!-- User Info -->
      <div class="flex-1 text-left overflow-hidden">
        <p class="text-sm font-semibold text-gray-900 truncate">{{ user.name }}</p>
        <p class="text-xs text-gray-500 truncate">{{ user.email }}</p>
      </div>

      <!-- Dropdown Icon -->
      <ChevronDownIcon class="h-4 w-4 text-gray-400 flex-shrink-0 transition-transform duration-150" />
    </MenuButton>

    <transition
      enter-active-class="transition ease-out duration-100"
      enter-from-class="transform opacity-0 scale-95"
      enter-to-class="transform opacity-100 scale-100"
      leave-active-class="transition ease-in duration-75"
      leave-from-class="transform opacity-100 scale-100"
      leave-to-class="transform opacity-0 scale-95"
    >
      <MenuItems class="absolute bottom-full left-0 mb-2 w-full origin-bottom-left rounded-lg bg-white shadow-xl ring-1 ring-black ring-opacity-5 focus:outline-none z-50 border border-gray-200">
        <div class="py-1">
          <MenuItem v-slot="{ active }" disabled>
            <button
              :class="[
                'w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed transition-colors',
                active ? 'bg-gray-50' : ''
              ]"
            >
              <UserCircleIcon class="h-5 w-5" />
              <span>Profile</span>
              <span class="ml-auto text-xs bg-gray-100 px-2 py-0.5 rounded-full">Soon</span>
            </button>
          </MenuItem>

          <MenuItem v-slot="{ active }" disabled>
            <button
              :class="[
                'w-full flex items-center gap-2 px-4 py-2 text-sm text-gray-400 cursor-not-allowed transition-colors',
                active ? 'bg-gray-50' : ''
              ]"
            >
              <Cog6ToothIcon class="h-5 w-5" />
              <span>Settings</span>
              <span class="ml-auto text-xs bg-gray-100 px-2 py-0.5 rounded-full">Soon</span>
            </button>
          </MenuItem>

          <div class="border-t border-gray-200 my-1"></div>

          <MenuItem v-slot="{ active }">
            <button
              @click="handleLogout"
              :class="[
                'w-full flex items-center gap-2 px-4 py-2 text-sm text-red-600 font-medium transition-colors',
                active ? 'bg-red-50 text-red-700' : ''
              ]"
            >
              <ArrowRightOnRectangleIcon class="h-5 w-5" />
              Logout
            </button>
          </MenuItem>
        </div>
      </MenuItems>
    </transition>
  </Menu>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue'
import {
  ChevronDownIcon,
  UserCircleIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon
} from '@heroicons/vue/24/outline'
import axios from '../axios'
import { clearAuthState } from '../router/index.js'

const props = defineProps({
  user: {
    type: Object,
    required: true,
    validator: (user) => user && user.email
  }
})

const router = useRouter()

// Generate initials from user name (handles edge cases)
const initials = computed(() => {
  if (!props.user?.name || !props.user.name.trim()) {
    return '?'
  }

  const words = props.user.name.trim().split(/\s+/).filter(Boolean)

  if (words.length === 0) return '?'
  if (words.length === 1) {
    // Single word: take first two characters
    return words[0].substring(0, 2).toUpperCase()
  } else {
    // Multiple words: take first character of first two words
    return (words[0][0] + words[1][0]).toUpperCase()
  }
})

// Generate a consistent color based on user name
const avatarColor = computed(() => {
  if (!props.user?.name) return '#6366f1' // Default indigo

  // Generate a hash from the name
  let hash = 0
  const name = props.user.name.toLowerCase()
  for (let i = 0; i < name.length; i++) {
    hash = name.charCodeAt(i) + ((hash << 5) - hash)
  }

  // Color palette (professional colors)
  const colors = [
    '#6366f1', // Indigo
    '#8b5cf6', // Purple
    '#ec4899', // Pink
    '#f59e0b', // Amber
    '#10b981', // Emerald
    '#3b82f6', // Blue
    '#06b6d4', // Cyan
    '#84cc16', // Lime
    '#f97316', // Orange
    '#14b8a6'  // Teal
  ]

  return colors[Math.abs(hash) % colors.length]
})

// Handle logout
const handleLogout = async () => {
  try {
    await axios.post('/api/logout')
    clearAuthState()
    router.push({ name: 'Login' })
  } catch (error) {
    // Force logout even if API fails
    clearAuthState()
    router.push({ name: 'Login' })
  }
}
</script>
