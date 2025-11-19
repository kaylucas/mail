<template>
  <div
    @click="handleClick"
    :class="[
      'group flex items-start gap-4 p-4 border-b border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer transition-colors',
      email.is_read ? 'bg-white dark:bg-gray-900' : 'bg-blue-50 dark:bg-blue-900/10'
    ]"
    :style="{ transform: `translateY(${offsetY}px)` }"
  >
    <!-- Checkbox for multi-select -->
    <div 
      class="flex-shrink-0 pt-1 transition-opacity duration-200"
      :class="selected ? 'opacity-100' : 'opacity-0 group-hover:opacity-30 hover:opacity-100'"
      @click.stop
    >
      <input
        type="checkbox"
        :checked="selected"
        @change="$emit('toggle-select', email.id)"
        class="h-4 w-4 text-blue-600 border-gray-300 dark:border-gray-500 rounded focus:ring-blue-500 dark:bg-gray-700"
      />
    </div>

    <!-- Sender avatar -->
    <div class="flex-shrink-0">
      <div
        :class="[
          'w-10 h-10 rounded-full flex items-center justify-center text-sm font-semibold text-white',
          avatarColor
        ]"
        :title="email.from_email"
      >
        {{ senderInitials }}
      </div>
    </div>

    <!-- Email content -->
    <div class="flex-1 min-w-0">
      <!-- Header row: sender name, unread indicator, date -->
      <div class="flex items-center justify-between gap-2 mb-1">
        <div class="flex items-center gap-2 min-w-0">
          <!-- Unread indicator -->
          <div
            v-if="!email.is_read"
            class="flex-shrink-0 w-2 h-2 bg-blue-600 dark:bg-blue-500 rounded-full"
            title="Unread"
          ></div>

          <!-- Sender name -->
          <span :class="['text-sm truncate', email.is_read ? 'text-gray-700 dark:text-gray-300' : 'text-gray-900 dark:text-white font-semibold']">
            {{ email.from_name || email.from_email }}
          </span>

          <!-- Importance indicator -->
          <ExclamationCircleIcon
            v-if="email.importance === 'high'"
            class="flex-shrink-0 h-4 w-4 text-red-500"
            title="High priority"
          />
        </div>

        <!-- Date -->
        <span class="flex-shrink-0 text-xs text-gray-500 dark:text-gray-400" :title="fullDateTime">
          {{ relativeTime }}
        </span>
      </div>

      <!-- Subject line -->
      <div class="flex items-center gap-2 mb-1">
        <h3 :class="['text-sm truncate flex-1', email.is_read ? 'font-normal text-gray-800 dark:text-gray-200' : 'font-semibold text-gray-900 dark:text-white']">
          {{ email.subject || '(No subject)' }}
        </h3>

        <!-- Attachment indicator -->
        <PaperClipIcon
          v-if="email.has_attachments"
          class="flex-shrink-0 h-4 w-4 text-gray-400 dark:text-gray-500"
          title="Has attachments"
        />
      </div>

      <!-- Body preview -->
      <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">
        {{ bodyPreview }}
      </p>

      <!-- Labels and Reminders -->
      <div class="flex items-center gap-2 mt-2">
        <EmailLabels :labels="email.labels" />
        <EmailReminderBadge :reminders="email.reminders" />
      </div>

      <!-- Folder label (if not in main folder) -->
      <div v-if="email.email_folder && showFolder" class="mt-2">
        <span class="inline-flex items-center gap-1 px-2 py-0.5 text-xs font-medium text-gray-600 dark:text-gray-300 bg-gray-100 dark:bg-gray-800 rounded">
          <FolderIcon class="h-3 w-3" />
          {{ email.email_folder.display_name }}
        </span>
      </div>

      <!-- Attachment previews (if expanded) -->
      <div v-if="email.has_attachments && email.attachments && email.attachments.length > 0 && showAttachments" class="mt-2 flex flex-wrap gap-2">
        <div
          v-for="attachment in email.attachments.slice(0, 3)"
          :key="attachment.id"
          class="inline-flex items-center gap-1.5 px-2 py-1 text-xs text-gray-600 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded"
        >
          <DocumentIcon class="h-3.5 w-3.5" />
          <span class="truncate max-w-[150px]">{{ attachment.name }}</span>
          <span class="text-gray-400 dark:text-gray-500">{{ formatSize(attachment.size) }}</span>
        </div>
        <span v-if="email.attachments.length > 3" class="text-xs text-gray-500 dark:text-gray-400 py-1">
          +{{ email.attachments.length - 3 }} more
        </span>
      </div>
    </div>

    <!-- Actions menu -->
    <div class="flex-shrink-0 flex items-start gap-1" @click.stop>
      <!-- Mark read/unread button -->
      <button
        @click="toggleReadStatus"
        class="p-1.5 text-gray-400 dark:text-gray-500 hover:text-gray-600 dark:hover:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800 rounded transition-colors"
        :title="email.is_read ? 'Mark as unread' : 'Mark as read'"
      >
        <EnvelopeOpenIcon v-if="!email.is_read" class="h-5 w-5" />
        <EnvelopeIcon v-else class="h-5 w-5" />
      </button>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import {
  PaperClipIcon,
  ExclamationCircleIcon,
  EnvelopeIcon,
  EnvelopeOpenIcon,
  FolderIcon,
  DocumentIcon
} from '@heroicons/vue/24/outline'
import { formatRelativeTime, formatFullDateTime, getInitials, formatFileSize } from '../utils/dateFormat'
import EmailLabels from './EmailLabels.vue'
import EmailReminderBadge from './EmailReminderBadge.vue'

const props = defineProps({
  email: {
    type: Object,
    required: true
  },
  selected: {
    type: Boolean,
    default: false
  },
  showFolder: {
    type: Boolean,
    default: false
  },
  showAttachments: {
    type: Boolean,
    default: false
  },
  offsetY: {
    type: Number,
    default: 0
  }
})

const emit = defineEmits(['click', 'toggle-select', 'mark-read'])

// Computed properties
const senderInitials = computed(() => {
  return getInitials(props.email.from_name || props.email.from_email)
})

const avatarColor = computed(() => {
  // Generate consistent color based on email address
  const colors = [
    'bg-blue-500',
    'bg-green-500',
    'bg-purple-500',
    'bg-pink-500',
    'bg-yellow-500',
    'bg-red-500',
    'bg-indigo-500',
    'bg-teal-500'
  ]

  const email = props.email.from_email || ''
  const hash = email.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0)
  return colors[hash % colors.length]
})

const relativeTime = computed(() => {
  return formatRelativeTime(props.email.received_date_time)
})

const fullDateTime = computed(() => {
  return formatFullDateTime(props.email.received_date_time)
})

const bodyPreview = computed(() => {
  return props.email.body_preview || ''
})

// Methods
const handleClick = () => {
  emit('click', props.email)
}

const toggleReadStatus = () => {
  emit('mark-read', props.email.id, !props.email.is_read)
}

const formatSize = (bytes) => {
  return formatFileSize(bytes)
}
</script>

<style scoped>
.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
