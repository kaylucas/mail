<template>
  <!-- Mobile backdrop overlay -->
  <TransitionRoot :show="isMobileOpen" as="template">
    <div class="lg:hidden fixed inset-0 z-40">
      <TransitionChild
        as="template"
        enter="transition-opacity ease-linear duration-300"
        enter-from="opacity-0"
        enter-to="opacity-100"
        leave="transition-opacity ease-linear duration-300"
        leave-from="opacity-100"
        leave-to="opacity-0"
      >
        <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm" @click="$emit('close')"></div>
      </TransitionChild>

      <TransitionChild
        as="template"
        enter="transition ease-in-out duration-300 transform"
        enter-from="-translate-x-full"
        enter-to="translate-x-0"
        leave="transition ease-in-out duration-300 transform"
        leave-from="translate-x-0"
        leave-to="-translate-x-full"
      >
        <div class="relative flex-1 flex flex-col max-w-xs w-full bg-white">
          <SidebarContent
            :user="user"
            :stats="stats"
            :folders="folders"
            :loading="loading"
            @close="$emit('close')"
          />
        </div>
      </TransitionChild>
    </div>
  </TransitionRoot>

  <!-- Desktop sidebar -->
  <div class="hidden lg:flex lg:flex-shrink-0">
    <div class="flex flex-col w-64 border-r border-gray-200 bg-white shadow-sm">
      <SidebarContent
        :user="user"
        :stats="stats"
        :folders="folders"
        :loading="loading"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { TransitionRoot, TransitionChild, Disclosure, DisclosureButton, DisclosurePanel } from '@headlessui/vue'
import {
  HomeIcon,
  InboxIcon,
  FolderIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
  ChevronDownIcon,
  ChevronRightIcon
} from '@heroicons/vue/24/outline'
import axios from '../axios'
import { clearAuthState } from '../router/index.js'
import UserMenu from './UserMenu.vue'

const props = defineProps({
  isMobileOpen: {
    type: Boolean,
    default: false
  }
})

defineEmits(['close'])

const route = useRoute()
const router = useRouter()

const user = ref(null)
const stats = ref(null)
const folders = ref([])
const loading = ref(true)

// Navigation items
const navigationItems = computed(() => [
  {
    name: 'Dashboard',
    routeName: 'Dashboard',
    icon: HomeIcon,
    badge: null
  },
  {
    name: 'Inbox',
    routeName: 'Emails',
    icon: InboxIcon,
    badge: stats.value?.unread_emails || null
  }
])

// Check if a route is active
const isActiveRoute = (routeName) => {
  return route.name === routeName
}

// Fetch user data and stats
const fetchData = async () => {
  try {
    loading.value = true
    const [userRes, statsRes, foldersRes] = await Promise.all([
      axios.get('/api/user'),
      axios.get('/api/emails/stats'),
      axios.get('/api/emails/folders')
    ])

    user.value = userRes.data
    stats.value = statsRes.data.data
    folders.value = foldersRes.data.data || []
  } catch (error) {
    console.error('Failed to load navigation data:', error)
  } finally {
    loading.value = false
  }
}

// Handle logout
const handleLogout = async () => {
  try {
    await axios.post('/api/logout')
    clearAuthState()
    router.push({ name: 'Login' })
  } catch (error) {
    console.error('Logout failed:', error)
    // Force logout even if API fails
    clearAuthState()
    router.push({ name: 'Login' })
  }
}

// Navigate to folder
const navigateToFolder = (folderId) => {
  router.push({ name: 'Emails', query: { folder: folderId } })
}

onMounted(() => {
  fetchData()
})
</script>

<script>
// Sidebar Content Component (defined inline for reuse between mobile and desktop)
import { defineComponent, h } from 'vue'

const SidebarContent = defineComponent({
  name: 'SidebarContent',
  props: {
    user: Object,
    stats: Object,
    folders: Array,
    loading: Boolean
  },
  emits: ['close'],
  setup(props, { emit }) {
    const route = useRoute()
    const router = useRouter()

    const navigationItems = computed(() => [
      {
        name: 'Dashboard',
        routeName: 'Dashboard',
        icon: HomeIcon,
        badge: null
      },
      {
        name: 'Inbox',
        routeName: 'Emails',
        icon: InboxIcon,
        badge: props.stats?.unread_emails || null
      }
    ])

    const isActiveRoute = (routeName) => {
      return route.name === routeName
    }

    const handleLogout = async () => {
      try {
        await axios.post('/api/logout')
        clearAuthState()
        router.push({ name: 'Login' })
        emit('close')
      } catch (error) {
        console.error('Logout failed:', error)
        clearAuthState()
        router.push({ name: 'Login' })
        emit('close')
      }
    }

    const navigateToFolder = (folderId) => {
      router.push({ name: 'Emails', query: { folder: folderId } })
      emit('close')
    }

    const navigateTo = (routeName) => {
      router.push({ name: routeName })
      emit('close')
    }

    return () => h('div', { class: 'flex flex-col h-full' }, [
      // User Menu at top
      h('div', { class: 'flex-shrink-0 px-4 py-4 border-b border-gray-200' }, [
        props.user ? h(UserMenu, { user: props.user }) : h('div', { class: 'h-16 bg-gray-100 animate-pulse rounded-lg' })
      ]),

      // Main navigation
      h('nav', { class: 'flex-1 px-4 py-4 space-y-1 overflow-y-auto' }, [
        // Primary navigation items
        ...navigationItems.value.map(item =>
          h('button', {
            key: item.routeName,
            onClick: () => navigateTo(item.routeName),
            class: [
              'w-full flex items-center gap-3 px-3 py-2 text-sm font-medium rounded-lg transition-all duration-150',
              isActiveRoute(item.routeName)
                ? 'bg-indigo-50 text-indigo-700 border-l-4 border-indigo-600 pl-2.5 shadow-sm'
                : 'text-gray-700 hover:bg-gray-50 hover:text-gray-900 border-l-4 border-transparent'
            ]
          }, [
            h(item.icon, { class: 'h-5 w-5 flex-shrink-0' }),
            h('span', { class: 'flex-1 text-left' }, item.name),
            item.badge ? h('span', {
              class: 'inline-flex items-center justify-center px-2 py-0.5 text-xs font-semibold rounded-full bg-indigo-600 text-white'
            }, item.badge.toString()) : null
          ])
        ),

        // Folders section
        h(Disclosure, { as: 'div', class: 'mt-6', defaultOpen: true }, ({ open }) => [
          h(DisclosureButton, {
            class: 'w-full flex items-center gap-2 px-3 py-2 text-sm font-semibold text-gray-700 rounded-lg hover:bg-gray-50 transition-all duration-150'
          }, [
            h(open ? ChevronDownIcon : ChevronRightIcon, { class: 'h-4 w-4 flex-shrink-0 transition-transform duration-150' }),
            h(FolderIcon, { class: 'h-5 w-5 flex-shrink-0 text-gray-500' }),
            h('span', 'Folders'),
            h('span', { class: 'ml-auto text-xs font-medium text-gray-500 bg-gray-100 px-2 py-0.5 rounded-full' }, `${props.folders?.length || 0}`)
          ]),
          h(DisclosurePanel, { class: 'mt-1 space-y-0.5' },
            props.loading
              ? [h('div', { class: 'px-3 py-2 pl-11 text-xs text-gray-500 animate-pulse' }, 'Loading folders...')]
              : props.folders?.length > 0
                ? props.folders.map(folder =>
                    h('button', {
                      key: folder.id,
                      onClick: () => navigateToFolder(folder.id),
                      class: [
                        'w-full flex items-center gap-2 px-3 py-2 pl-11 text-sm rounded-lg transition-all duration-150',
                        route.query.folder === folder.id
                          ? 'bg-gray-100 text-gray-900 font-medium shadow-sm'
                          : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'
                      ]
                    }, [
                      h('span', { class: 'flex-1 text-left truncate' }, folder.display_name),
                      folder.unread_item_count > 0 ? h('span', {
                        class: 'text-xs font-medium text-gray-500 bg-gray-200 px-2 py-0.5 rounded-full'
                      }, folder.unread_item_count.toString()) : null
                    ])
                  )
                : [h('div', { class: 'px-3 py-2 pl-11 text-xs text-gray-500 italic' }, 'No folders found')]
          )
        ]),

        // Settings (placeholder)
        h('button', {
          disabled: true,
          class: 'w-full flex items-center gap-3 px-3 py-2 mt-6 text-sm font-medium text-gray-400 rounded-lg cursor-not-allowed opacity-50 border-l-4 border-transparent'
        }, [
          h(Cog6ToothIcon, { class: 'h-5 w-5 flex-shrink-0' }),
          h('span', 'Settings'),
          h('span', { class: 'ml-auto text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full' }, 'Soon')
        ])
      ]),

      // Logout button at bottom
      h('div', { class: 'flex-shrink-0 px-4 py-4 border-t border-gray-200 bg-gray-50' }, [
        h('button', {
          onClick: handleLogout,
          class: 'w-full flex items-center gap-3 px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 hover:text-red-700 rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500'
        }, [
          h(ArrowRightOnRectangleIcon, { class: 'h-5 w-5 flex-shrink-0' }),
          h('span', 'Logout')
        ])
      ])
    ])
  }
})

export { SidebarContent }
</script>
