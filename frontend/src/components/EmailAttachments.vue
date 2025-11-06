<template>
  <div class="email-attachments bg-gradient-to-br from-gray-50 to-gray-100 border border-gray-200 rounded-xl p-5 shadow-sm">
    <h4 class="text-sm font-bold text-gray-900 mb-4 flex items-center gap-2">
      <PaperClipIcon class="h-5 w-5 text-gray-600" />
      Attachments
      <span class="ml-auto text-xs font-medium text-gray-500 bg-white px-2.5 py-1 rounded-full shadow-sm">
        {{ attachments.length }}
      </span>
    </h4>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
      <div
        v-for="attachment in attachments"
        :key="attachment.id"
        class="flex items-center gap-3 bg-white border border-gray-200 rounded-lg p-3 hover:border-indigo-300 hover:shadow-md transition-all duration-150 group"
      >
        <!-- File Icon -->
        <div
          class="flex-shrink-0 w-12 h-12 rounded-lg flex items-center justify-center shadow-sm transition-transform duration-150 group-hover:scale-105"
          :class="getIconBackgroundClass(attachment.content_type)"
        >
          <component
            :is="getFileIcon(attachment.content_type)"
            class="h-6 w-6"
            :class="getIconColorClass(attachment.content_type)"
          />
        </div>

        <!-- File Info -->
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold text-gray-900 truncate group-hover:text-indigo-700 transition-colors" :title="attachment.name">
            {{ attachment.name }}
          </p>
          <div class="flex items-center gap-2 text-xs text-gray-500 mt-1">
            <span class="font-medium">{{ formatFileSize(attachment.size) }}</span>
            <span v-if="attachment.is_inline" class="inline-flex items-center gap-1">
              <span class="text-gray-300">•</span>
              <span class="text-indigo-600 font-medium bg-indigo-50 px-2 py-0.5 rounded-full">Inline</span>
            </span>
          </div>
        </div>

        <!-- Download Button (placeholder) -->
        <button
          class="flex-shrink-0 p-2 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded-lg transition-all duration-150 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
          :title="`Download ${attachment.name}`"
          @click="handleDownload(attachment)"
        >
          <ArrowDownTrayIcon class="h-5 w-5" />
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import {
  PaperClipIcon,
  ArrowDownTrayIcon,
  DocumentIcon,
  DocumentTextIcon,
  PhotoIcon,
  FilmIcon,
  MusicalNoteIcon,
  ArchiveBoxIcon
} from '@heroicons/vue/24/outline'
import { formatFileSize } from '../utils/dateFormat'

/**
 * Component props
 * @typedef {Object} Attachment
 * @property {number} id - Attachment ID
 * @property {string} name - File name
 * @property {string} content_type - MIME type
 * @property {number} size - File size in bytes
 * @property {boolean} is_inline - Whether attachment is inline
 *
 * @typedef {Object} Props
 * @property {Attachment[]} attachments - Array of attachment objects
 */
const props = defineProps({
  attachments: {
    type: Array,
    required: true,
    default: () => []
  }
})

/**
 * Get icon component based on content type
 */
const getFileIcon = (contentType) => {
  if (!contentType) return DocumentIcon

  const type = contentType.toLowerCase()

  // Images
  if (type.startsWith('image/')) {
    return PhotoIcon
  }

  // Videos
  if (type.startsWith('video/')) {
    return FilmIcon
  }

  // Audio
  if (type.startsWith('audio/')) {
    return MusicalNoteIcon
  }

  // Archives
  if (type.includes('zip') || type.includes('rar') || type.includes('tar') || type.includes('gzip')) {
    return ArchiveBoxIcon
  }

  // Text/Documents
  if (
    type.includes('text') ||
    type.includes('pdf') ||
    type.includes('document') ||
    type.includes('word') ||
    type.includes('excel') ||
    type.includes('spreadsheet') ||
    type.includes('presentation') ||
    type.includes('powerpoint')
  ) {
    return DocumentTextIcon
  }

  // Default
  return DocumentIcon
}

/**
 * Get icon background color class based on content type
 */
const getIconBackgroundClass = (contentType) => {
  if (!contentType) return 'bg-gray-100'

  const type = contentType.toLowerCase()

  if (type.startsWith('image/')) return 'bg-purple-100'
  if (type.startsWith('video/')) return 'bg-red-100'
  if (type.startsWith('audio/')) return 'bg-green-100'
  if (type.includes('zip') || type.includes('rar') || type.includes('tar') || type.includes('gzip')) {
    return 'bg-yellow-100'
  }
  if (type.includes('pdf')) return 'bg-red-100'
  if (type.includes('word') || type.includes('document')) return 'bg-blue-100'
  if (type.includes('excel') || type.includes('spreadsheet')) return 'bg-green-100'
  if (type.includes('powerpoint') || type.includes('presentation')) return 'bg-orange-100'

  return 'bg-gray-100'
}

/**
 * Get icon color class based on content type
 */
const getIconColorClass = (contentType) => {
  if (!contentType) return 'text-gray-600'

  const type = contentType.toLowerCase()

  if (type.startsWith('image/')) return 'text-purple-600'
  if (type.startsWith('video/')) return 'text-red-600'
  if (type.startsWith('audio/')) return 'text-green-600'
  if (type.includes('zip') || type.includes('rar') || type.includes('tar') || type.includes('gzip')) {
    return 'text-yellow-600'
  }
  if (type.includes('pdf')) return 'text-red-600'
  if (type.includes('word') || type.includes('document')) return 'text-blue-600'
  if (type.includes('excel') || type.includes('spreadsheet')) return 'text-green-600'
  if (type.includes('powerpoint') || type.includes('presentation')) return 'text-orange-600'

  return 'text-gray-600'
}

/**
 * Handle attachment download
 * Placeholder for future implementation
 */
const handleDownload = (attachment) => {
  // TODO: Implement download via /api/emails/attachments/{id}/download
  alert(`Download functionality coming soon!\n\nFile: ${attachment.name}`)
}
</script>
