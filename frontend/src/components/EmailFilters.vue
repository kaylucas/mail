<template>
  <div class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 p-4 transition-colors">
    <div class="max-w-7xl mx-auto">
      <!-- Top row: Search and main filters -->
      <div class="flex flex-col sm:flex-row gap-3 mb-3">
        <!-- Search input -->
        <div class="flex-1 relative">
          <MagnifyingGlassIcon class="absolute left-3 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" />
          <input
            v-model="searchQuery"
            type="text"
            placeholder="Search emails..."
            class="w-full pl-10 pr-4 py-2 border border-gray-300 dark:border-gray-700 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400"
            @input="handleSearchInput"
          />
          <button
            v-if="searchQuery"
            @click="clearSearch"
            class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300"
          >
            <XMarkIcon class="h-5 w-5" />
          </button>
        </div>

        <!-- Folder selector -->
        <Menu as="div" class="relative">
          <MenuButton class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-2 focus:ring-blue-500 whitespace-nowrap transition-colors">
            <FolderIcon class="h-5 w-5 text-gray-400" />
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
              {{ selectedFolderName }}
            </span>
            <ChevronDownIcon class="h-4 w-4 text-gray-400" />
          </MenuButton>
          <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
          >
            <MenuItems class="absolute right-0 mt-2 w-56 origin-top-right bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-10 border border-gray-200 dark:border-gray-700">
              <div class="py-1">
                <MenuItem v-slot="{ active }">
                  <button
                    @click="$emit('update:folder', null)"
                    :class="[
                      active ? 'bg-gray-100 dark:bg-gray-700' : '',
                      !folder ? 'font-semibold text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-200',
                      'block w-full text-left px-4 py-2 text-sm'
                    ]"
                  >
                    All Folders
                  </button>
                </MenuItem>
                <MenuItem v-for="f in folders" :key="f.id" v-slot="{ active }">
                  <button
                    @click="$emit('update:folder', f.id)"
                    :class="[
                      active ? 'bg-gray-100 dark:bg-gray-700' : '',
                      folder === f.id ? 'font-semibold text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-200',
                      'block w-full text-left px-4 py-2 text-sm flex items-center justify-between'
                    ]"
                  >
                    <span>{{ f.display_name }}</span>
                    <span v-if="f.unread_item_count > 0" class="text-xs font-semibold text-blue-600 dark:text-blue-400">
                      {{ f.unread_item_count }}
                    </span>
                  </button>
                </MenuItem>
              </div>
            </MenuItems>
          </transition>
        </Menu>

        <!-- Sort selector -->
        <Menu as="div" class="relative">
          <MenuButton class="inline-flex items-center gap-2 px-4 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-2 focus:ring-blue-500 whitespace-nowrap transition-colors">
            <span class="text-sm font-medium text-gray-700 dark:text-gray-200">
              {{ sortLabel }}
            </span>
            <ChevronDownIcon class="h-4 w-4 text-gray-400" />
          </MenuButton>
          <transition
            enter-active-class="transition duration-100 ease-out"
            enter-from-class="transform scale-95 opacity-0"
            enter-to-class="transform scale-100 opacity-100"
            leave-active-class="transition duration-75 ease-in"
            leave-from-class="transform scale-100 opacity-100"
            leave-to-class="transform scale-95 opacity-0"
          >
            <MenuItems class="absolute right-0 mt-2 w-48 origin-top-right bg-white dark:bg-gray-800 rounded-lg shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none z-10 border border-gray-200 dark:border-gray-700">
              <div class="py-1">
                <MenuItem v-for="option in sortOptions" :key="option.value" v-slot="{ active }">
                  <button
                    @click="$emit('update:sortBy', option.value)"
                    :class="[
                      active ? 'bg-gray-100 dark:bg-gray-700' : '',
                      sortBy === option.value ? 'font-semibold text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-200',
                      'block w-full text-left px-4 py-2 text-sm'
                    ]"
                  >
                    {{ option.label }}
                  </button>
                </MenuItem>
              </div>
            </MenuItems>
          </transition>
        </Menu>

        <!-- Sort order toggle -->
        <button
          @click="$emit('toggle-sort-order')"
          class="inline-flex items-center justify-center px-3 py-2 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:ring-2 focus:ring-blue-500 transition-colors"
          :title="sortOrder === 'desc' ? 'Newest first' : 'Oldest first'"
        >
          <ArrowUpIcon v-if="sortOrder === 'asc'" class="h-5 w-5 text-gray-600 dark:text-gray-400" />
          <ArrowDownIcon v-else class="h-5 w-5 text-gray-600 dark:text-gray-400" />
        </button>
      </div>

      <!-- Bottom row: Filter chips -->
      <div class="flex flex-wrap items-center gap-2">
        <!-- Read/Unread filter -->
        <button
          v-for="readOption in readOptions"
          :key="readOption.value"
          @click="$emit('update:isRead', readOption.value)"
          :class="[
            isRead === readOption.value
              ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'
              : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700',
            'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border rounded-full transition-colors'
          ]"
        >
          <component :is="readOption.icon" class="h-4 w-4" />
          {{ readOption.label }}
        </button>

        <!-- Has attachments filter -->
        <button
          @click="$emit('update:hasAttachments', hasAttachments ? null : true)"
          :class="[
            hasAttachments
              ? 'bg-blue-100 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'
              : 'bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 border-gray-200 dark:border-gray-700 hover:bg-gray-200 dark:hover:bg-gray-700',
            'inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium border rounded-full transition-colors'
          ]"
        >
          <PaperClipIcon class="h-4 w-4" />
          Has Attachments
        </button>

        <!-- Clear filters button -->
        <button
          v-if="hasActiveFilters"
          @click="$emit('clear-filters')"
          class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-red-700 dark:text-red-400 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-full hover:bg-red-100 dark:hover:bg-red-900/30 transition-colors"
        >
          <XMarkIcon class="h-4 w-4" />
          Clear Filters
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { Menu, MenuButton, MenuItems, MenuItem } from '@headlessui/vue'
import {
  MagnifyingGlassIcon,
  FolderIcon,
  ChevronDownIcon,
  ArrowUpIcon,
  ArrowDownIcon,
  PaperClipIcon,
  XMarkIcon,
  EnvelopeIcon,
  EnvelopeOpenIcon,
  InboxIcon
} from '@heroicons/vue/24/outline'

const props = defineProps({
  folder: {
    type: Number,
    default: null
  },
  isRead: {
    type: Boolean,
    default: null
  },
  hasAttachments: {
    type: Boolean,
    default: null
  },
  search: {
    type: String,
    default: ''
  },
  sortBy: {
    type: String,
    default: 'received_date_time'
  },
  sortOrder: {
    type: String,
    default: 'desc'
  },
  folders: {
    type: Array,
    default: () => []
  },
  hasActiveFilters: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits([
  'update:folder',
  'update:isRead',
  'update:hasAttachments',
  'update:search',
  'update:sortBy',
  'toggle-sort-order',
  'clear-filters'
])

// Local search state (for immediate UI update)
const searchQuery = ref(props.search)

// Watch for external search changes
watch(() => props.search, (newValue) => {
  searchQuery.value = newValue
})

const handleSearchInput = () => {
  emit('update:search', searchQuery.value)
}

const clearSearch = () => {
  searchQuery.value = ''
  emit('update:search', '')
}

// Sort options
const sortOptions = [
  { label: 'Date Received', value: 'received_date_time' },
  { label: 'Date Sent', value: 'sent_date_time' },
  { label: 'Subject', value: 'subject' }
]

const sortLabel = computed(() => {
  const option = sortOptions.find(o => o.value === props.sortBy)
  return option ? option.label : 'Sort By'
})

// Read filter options
const readOptions = [
  { label: 'All', value: null, icon: InboxIcon },
  { label: 'Unread', value: false, icon: EnvelopeIcon },
  { label: 'Read', value: true, icon: EnvelopeOpenIcon }
]

// Selected folder name
const selectedFolderName = computed(() => {
  if (!props.folder) return 'All Folders'
  const folder = props.folders.find(f => f.id === props.folder)
  return folder ? folder.display_name : 'All Folders'
})
</script>
