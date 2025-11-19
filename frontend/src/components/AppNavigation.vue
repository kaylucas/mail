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
    <div class="flex flex-col w-64 border-r border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 shadow-sm transition-colors">
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

    // "Views" section (Placeholder for now)
    const viewsItems = computed(() => [
      {
        name: 'Inbox',
        routeName: 'Emails',
        icon: InboxIcon,
        badge: props.stats?.unread_emails || null
      },
      {
        name: 'GitHub',
        // Placeholder route/query for now
        routeName: 'Emails',
        query: { label: 'github' },
        icon: FolderIcon, // Using FolderIcon as a placeholder for GitHub icon
        badge: null
      }
    ])

    // "Mail" section (Standard folders)
    const mailItems = computed(() => [
      { name: 'All Mail', id: 'all', icon: InboxIcon },
      { name: 'Sent', id: 'sent', icon: ArrowRightOnRectangleIcon }, // Using ArrowRight... as placeholder for Sent
      { name: 'Drafts', id: 'drafts', icon: FolderIcon }, // Placeholder
      { name: 'Spam', id: 'spam', icon: FolderIcon }, // Placeholder
      { name: 'Trash', id: 'trash', icon: FolderIcon }, // Placeholder
    ])



    const isActiveRoute = (routeName, query = {}) => {
      if (route.name !== routeName) return false
      // Simple query check - if query is provided, it must match
      if (Object.keys(query).length > 0) {
        return Object.entries(query).every(([k, v]) => route.query[k] === v)
      }
      return true
    }

    const isActiveFolder = (folderId) => {
      return route.name === 'Emails' && route.query.folder === folderId
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

    const navigateTo = (routeName, query = {}) => {
      router.push({ name: routeName, query })
      emit('close')
    }

    return () => h('div', { class: 'flex flex-col h-full bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-300 border-r border-gray-200 dark:border-gray-800' }, [
      // User Menu at top
      h('div', { class: 'flex-shrink-0 px-4 py-4' }, [
        props.user ? h(UserMenu, { user: props.user }) : h('div', { class: 'h-16 bg-gray-100 dark:bg-gray-800 animate-pulse rounded-lg' })
      ]),

      // Search bar placeholder
      h('div', { class: 'px-4 mb-4' }, [
          h('div', { class: 'relative' }, [
              h('div', { class: 'absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none' }, [
                  h('svg', { class: 'h-4 w-4 text-gray-400 dark:text-gray-500', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
                      h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z' })
                  ])
              ]),
              h('input', {
                  type: 'text',
                  class: 'block w-full pl-10 pr-3 py-1.5 border border-transparent rounded-md leading-5 bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-gray-300 placeholder-gray-500 focus:outline-none focus:bg-white dark:focus:bg-gray-700 focus:ring-2 focus:ring-indigo-500 sm:text-sm transition-colors',
                  placeholder: 'Search'
              })
          ])
      ]),

      // Main navigation
      h('nav', { class: 'flex-1 px-2 space-y-6 overflow-y-auto' }, [
        
        // Views Section
        h('div', [
            h('h3', { class: 'px-3 text-xs font-semibold text-gray-500 uppercase tracking-wider' }, 'Views'),
            h('div', { class: 'mt-1 space-y-1' }, 
                viewsItems.value.map(item => 
                    h('button', {
                        key: item.name,
                        onClick: () => navigateTo(item.routeName, item.query),
                        class: [
                            'group w-full flex items-center justify-between px-3 py-2 text-sm font-medium rounded-md transition-colors',
                            isActiveRoute(item.routeName, item.query)
                                ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white'
                                : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'
                        ]
                    }, [
                        h('div', { class: 'flex items-center truncate' }, [
                            h(item.icon, { class: [
                                'flex-shrink-0 h-5 w-5 mr-3',
                                isActiveRoute(item.routeName, item.query) ? 'text-gray-500 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300'
                            ]}),
                            h('span', { class: 'truncate' }, item.name)
                        ]),
                        item.badge ? h('span', {
                            class: 'inline-block py-0.5 px-2 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'
                        }, item.badge.toString()) : null
                    ])
                )
            )
        ]),

        // "Add view" and "Less" buttons
        h('div', { class: 'space-y-1' }, [
             h('button', {
                class: 'group w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white rounded-md'
             }, [
                 h('svg', { class: 'flex-shrink-0 h-5 w-5 mr-3 text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
                     h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M12 6v6m0 0v6m0-6h6m-6 0H6' })
                 ]),
                 h('span', 'Add view')
             ]),
             h('button', {
                class: 'group w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white rounded-md'
             }, [
                 h('svg', { class: 'flex-shrink-0 h-5 w-5 mr-3 text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
                     h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M5 15l7-7 7 7' })
                 ]),
                 h('span', 'Less')
             ])
        ]),

        // Mail Section
        h('div', [
            h('h3', { class: 'px-3 mt-6 text-xs font-semibold text-gray-500 uppercase tracking-wider' }, 'Mail'),
            h('div', { class: 'mt-1 space-y-1' }, 
                mailItems.value.map(item => 
                    h('button', {
                        key: item.id,
                        onClick: () => {}, 
                        class: 'group w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white rounded-md'
                    }, [
                        h(item.icon, { class: 'flex-shrink-0 h-5 w-5 mr-3 text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }),
                        h('span', item.name)
                    ])
                )
            )
        ]),

        // Settings Section
        h('div', [
             h('button', {
                onClick: () => navigateTo('Settings'),
                class: [
                    'group w-full flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors mt-6',
                    isActiveRoute('Settings')
                        ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white'
                        : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'
                ]
            }, [
                h(Cog6ToothIcon, { class: [
                    'flex-shrink-0 h-5 w-5 mr-3',
                    isActiveRoute('Settings') ? 'text-gray-500 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300'
                ]}),
                h('span', 'Settings')
            ]),
            h('button', {
                class: 'group w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white rounded-md'
            }, [
                 h('svg', { class: 'flex-shrink-0 h-5 w-5 mr-3 text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
                     h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z' })
                 ]),
                 h('span', 'Support & feedback')
            ]),
            h('button', {
                class: 'group w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white rounded-md'
            }, [
                 h('svg', { class: 'flex-shrink-0 h-5 w-5 mr-3 text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300', fill: 'none', viewBox: '0 0 24 24', stroke: 'currentColor' }, [
                     h('path', { 'stroke-linecap': 'round', 'stroke-linejoin': 'round', 'stroke-width': '2', d: 'M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4' })
                 ]),
                 h('span', 'Get macOS app')
            ])
        ]),

      ]),

      // Logout button at bottom
      h('div', { class: 'flex-shrink-0 px-4 py-4 border-t border-gray-200 dark:border-gray-800' }, [
        h('button', {
          onClick: handleLogout,
          class: 'w-full flex items-center gap-3 px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-md transition-colors'
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
