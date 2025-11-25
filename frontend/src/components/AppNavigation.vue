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
            :views="views"
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
        :views="views"
        :loading="loading"
      />
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { TransitionRoot, TransitionChild } from '@headlessui/vue'
import {
  HomeIcon,
  InboxIcon,
  FolderIcon,
  Cog6ToothIcon,
  ArrowRightOnRectangleIcon,
  ChevronDownIcon,
  ChevronRightIcon,
  TagIcon,
  ShoppingCartIcon,
  SparklesIcon,
  CodeBracketIcon,
  BuildingStorefrontIcon
} from '@heroicons/vue/24/outline'
import axios from '../axios'
import { clearAuthState } from '../router/index.js'
import UserMenu from './UserMenu.vue'
import { useViews } from '../composables/useViews'

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
const { views, fetchViews } = useViews()

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
    await fetchViews()
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
import { defineComponent, h, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from '../axios'
import { clearAuthState } from '../router/index.js'
import UserMenu from './UserMenu.vue'
import {
  InboxIcon,
  FolderIcon,
  ArrowRightOnRectangleIcon,
  Cog6ToothIcon,
  PlusIcon,
  TagIcon,
  ShoppingCartIcon,
  SparklesIcon,
  CodeBracketIcon,
  BuildingStorefrontIcon
} from '@heroicons/vue/24/outline'

const iconMap = {
  inbox: InboxIcon,
  folder: FolderIcon,
  tag: TagIcon,
  'shopping-cart': ShoppingCartIcon,
  github: CodeBracketIcon,
  storefront: BuildingStorefrontIcon,
  sparkles: SparklesIcon
}

const SidebarContent = defineComponent({
  name: 'SidebarContent',
  props: {
    user: Object,
    stats: Object,
    folders: Array,
    views: Array,
    loading: Boolean
  },
  emits: ['close'],
  setup(props, { emit }) {
    const route = useRoute()
    const router = useRouter()

    const viewsItems = computed(() => {
      if (!props.views || props.views.length === 0) {
        return []
      }

      return props.views.map(view => {
        const IconComponent = iconMap[view.icon] || FolderIcon
        return {
          id: view.id,
          name: view.name,
          icon: IconComponent,
          color: view.color,
          routeName: 'Emails',
          query: { view: view.id },
          badge: view.unread_count || view.email_count || null
        }
      })
    })

    const mailItems = computed(() => [
      { name: 'All Mail', id: 'all', icon: InboxIcon },
      { name: 'Sent', id: 'sent', icon: ArrowRightOnRectangleIcon },
      { name: 'Drafts', id: 'drafts', icon: FolderIcon },
      { name: 'Spam', id: 'spam', icon: FolderIcon },
      { name: 'Trash', id: 'trash', icon: FolderIcon }
    ])

    const isActiveRoute = (routeName, query = {}) => {
      if (route.name !== routeName) return false
      if (Object.keys(query).length > 0) {
        return Object.entries(query).every(([k, v]) => route.query[k]?.toString() === v.toString())
      }
      return true
    }

    const navigateTo = (routeName, query = {}) => {
      router.push({ name: routeName, query })
      emit('close')
    }

    const handleAddView = () => {
      router.push({ name: 'ViewsManagement' })
      emit('close')
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

    return () => h('div', { class: 'flex flex-col h-full bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-300 border-r border-gray-200 dark:border-gray-800' }, [
      h('div', { class: 'flex-shrink-0 px-4 py-4' }, [
        props.user ? h(UserMenu, { user: props.user }) : h('div', { class: 'h-16 bg-gray-100 dark:bg-gray-800 animate-pulse rounded-lg' })
      ]),
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
      h('nav', { class: 'flex-1 px-2 space-y-6 overflow-y-auto' }, [
        h('div', [
          h('div', { class: 'flex items-center justify-between px-3' }, [
            h('h3', { class: 'text-xs font-semibold text-gray-500 uppercase tracking-wider' }, 'Views'),
            h('button', {
              onClick: handleAddView,
              class: 'text-xs text-indigo-600 dark:text-indigo-400 hover:text-indigo-500'
            }, 'Manage')
          ]),
          h('div', { class: 'mt-1 space-y-1' }, (
            viewsItems.value.length ? viewsItems.value : [{ name: 'Inbox', icon: InboxIcon, routeName: 'Emails', query: {}, badge: props.stats?.unread_emails }]
          ).map(item =>
            h('button', {
              key: item.id || item.name,
              onClick: () => navigateTo(item.routeName, item.query),
              class: [
                'group w-full flex items-center justify-between px-3 py-2 text-sm font-medium rounded-md transition-colors',
                isActiveRoute(item.routeName, item.query)
                  ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white'
                  : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'
              ]
            }, [
              h('div', { class: 'flex items-center truncate' }, [
                h(item.icon, {
                  class: [
                    'flex-shrink-0 h-5 w-5 mr-3',
                    item.color ? '' : isActiveRoute(item.routeName, item.query) ? 'text-gray-500 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300'
                  ],
                  style: item.color ? { color: item.color } : null
                }),
                h('span', { class: 'truncate' }, item.name)
              ]),
              item.badge ? h('span', {
                class: 'inline-block py-0.5 px-2 text-xs font-medium rounded-full bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-300'
              }, item.badge.toString()) : null
            ])
          ))
        ]),
        h('div', { class: 'space-y-1' }, [
          h('button', {
            onClick: handleAddView,
            class: 'group w-full flex items-center px-3 py-2 text-sm font-medium text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white rounded-md'
          }, [
            h(PlusIcon, { class: 'flex-shrink-0 h-5 w-5 mr-3 text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300' }),
            h('span', 'Add view')
          ])
        ]),
        h('div', [
          h('h3', { class: 'px-3 mt-6 text-xs font-semibold text-gray-500 uppercase tracking-wider' }, 'Mail'),
          h('div', { class: 'mt-1 space-y-1 flex flex-col' }, mailItems.value.map(item =>
            h('button', {
              key: item.id,
              onClick: () => navigateTo('Emails', { folder: item.id }),
              class: [
                'group w-full flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors',
                isActiveRoute('Emails', { folder: item.id })
                  ? 'bg-gray-100 dark:bg-gray-800 text-gray-900 dark:text-white'
                  : 'text-gray-600 dark:text-gray-400 hover:bg-gray-50 dark:hover:bg-gray-800 hover:text-gray-900 dark:hover:text-white'
              ]
            }, [
              h(item.icon, {
                class: [
                  'flex-shrink-0 h-5 w-5 mr-3',
                  isActiveRoute('Emails', { folder: item.id }) ? 'text-gray-500 dark:text-gray-200' : 'text-gray-400 dark:text-gray-500 group-hover:text-gray-500 dark:group-hover:text-gray-300'
                ]
              }),
              h('span', item.name)
            ])
          ))
        ]),
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
            ] }),
            h('span', 'Settings')
          ])
        ])
      ]),
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
