<template>
  <div class="h-screen flex flex-col">
    <!-- Email Sync Status Indicator -->
    <EmailSyncIndicator />

    <!-- Header -->
    <header class="bg-white border-b border-gray-200 px-6 py-4">
      <div class="max-w-7xl mx-auto flex items-center justify-between">
        <div>
          <h1 class="text-2xl font-bold text-gray-900">Inbox</h1>
          <p class="text-sm text-gray-600 mt-1">Manage your emails</p>
        </div>
        <button
          @click="refreshEmails"
          class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
        >
          <ArrowPathIcon class="h-5 w-5" :class="{ 'animate-spin': isRefreshing }" />
          Refresh
        </button>
      </div>
    </header>

    <!-- Email inbox component -->
    <EmailInbox
      @email-selected="handleEmailSelected"
      @mark-read="handleMarkRead"
    />

    <!-- Email detail modal -->
    <TransitionRoot appear :show="selectedEmail !== null" as="template">
      <Dialog as="div" @close="closeEmailDetail" class="relative z-50">
        <TransitionChild
          as="template"
          enter="duration-300 ease-out"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="duration-200 ease-in"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-black bg-opacity-25" />
        </TransitionChild>

        <div class="fixed inset-0 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              as="template"
              enter="duration-300 ease-out"
              enter-from="opacity-0 scale-95"
              enter-to="opacity-100 scale-100"
              leave="duration-200 ease-in"
              leave-from="opacity-100 scale-100"
              leave-to="opacity-0 scale-95"
            >
              <DialogPanel class="w-full max-w-4xl transform overflow-hidden rounded-2xl bg-white shadow-xl transition-all">
                <!-- Modal header -->
                <div class="border-b border-gray-200 px-6 py-4">
                  <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                      <DialogTitle as="h3" class="text-lg font-semibold text-gray-900 mb-2">
                        {{ selectedEmail?.subject || '(No subject)' }}
                      </DialogTitle>
                      <div class="flex items-center gap-4 text-sm text-gray-600">
                        <div class="flex items-center gap-2">
                          <span class="font-medium">From:</span>
                          <span>{{ selectedEmail?.from_name }} &lt;{{ selectedEmail?.from_email }}&gt;</span>
                        </div>
                        <div class="flex items-center gap-2">
                          <span class="font-medium">Date:</span>
                          <span>{{ formatDateTime(selectedEmail?.received_date_time) }}</span>
                        </div>
                      </div>
                      <div v-if="selectedEmail?.to_recipients && selectedEmail.to_recipients.length > 0" class="mt-2 text-sm text-gray-600">
                        <span class="font-medium">To:</span>
                        {{ formatRecipients(selectedEmail.to_recipients) }}
                      </div>
                    </div>
                    <button
                      @click="closeEmailDetail"
                      class="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                      <XMarkIcon class="h-6 w-6" />
                    </button>
                  </div>
                </div>

                <!-- Modal body -->
                <div class="px-6 py-4 max-h-[60vh] overflow-y-auto">
                  <!-- Attachments -->
                  <div v-if="selectedEmail?.attachments && selectedEmail.attachments.length > 0" class="mb-4">
                    <h4 class="text-sm font-semibold text-gray-700 mb-2">
                      Attachments ({{ selectedEmail.attachments.length }})
                    </h4>
                    <div class="flex flex-wrap gap-2">
                      <div
                        v-for="attachment in selectedEmail.attachments"
                        :key="attachment.id"
                        class="inline-flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-lg"
                      >
                        <DocumentIcon class="h-5 w-5 text-gray-400" />
                        <div class="min-w-0">
                          <div class="text-sm font-medium text-gray-900 truncate max-w-[200px]">
                            {{ attachment.name }}
                          </div>
                          <div class="text-xs text-gray-500">
                            {{ formatFileSize(attachment.size) }}
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Email body -->
                  <div
                    v-if="selectedEmail?.body_content"
                    class="prose prose-sm max-w-none"
                    v-html="sanitizeHtml(selectedEmail.body_content)"
                  ></div>
                  <div v-else class="text-gray-500 italic">
                    No content available
                  </div>
                </div>

                <!-- Modal footer -->
                <div class="border-t border-gray-200 px-6 py-4 flex justify-end">
                  <button
                    @click="closeEmailDetail"
                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors"
                  >
                    Close
                  </button>
                </div>
              </DialogPanel>
            </TransitionChild>
          </div>
        </div>
      </Dialog>
    </TransitionRoot>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { Dialog, DialogPanel, DialogTitle, TransitionChild, TransitionRoot } from '@headlessui/vue'
import { ArrowPathIcon, XMarkIcon, DocumentIcon } from '@heroicons/vue/24/outline'
import EmailInbox from '../components/EmailInbox.vue'
import EmailSyncIndicator from '../components/EmailSyncIndicator.vue'
import { formatFullDateTime, formatFileSize } from '../utils/dateFormat'

const selectedEmail = ref(null)
const isRefreshing = ref(false)

// Email inbox ref for calling refresh
const inboxRef = ref(null)

const handleEmailSelected = (email) => {
  selectedEmail.value = email
}

const closeEmailDetail = () => {
  selectedEmail.value = null
}

const handleMarkRead = (emailId, isRead) => {
  console.log(`Email ${emailId} marked as ${isRead ? 'read' : 'unread'}`)
}

const refreshEmails = async () => {
  isRefreshing.value = true
  // In a real implementation, you'd call the refresh method from useEmails
  setTimeout(() => {
    isRefreshing.value = false
  }, 1000)
}

const formatDateTime = (dateString) => {
  if (!dateString) return ''
  return formatFullDateTime(dateString)
}

const formatRecipients = (recipients) => {
  if (!recipients || recipients.length === 0) return ''
  return recipients
    .map(r => r.name ? `${r.name} <${r.email}>` : r.email)
    .join(', ')
}

const sanitizeHtml = (html) => {
  // Basic sanitization - in production, use a library like DOMPurify
  if (!html) return ''

  // For text content, just escape HTML
  const div = document.createElement('div')
  div.textContent = html
  return div.innerHTML
}
</script>

<style>
/* Prose styles for email body */
.prose {
  color: #374151;
  line-height: 1.75;
}

.prose p {
  margin-top: 1.25em;
  margin-bottom: 1.25em;
}

.prose a {
  color: #2563eb;
  text-decoration: underline;
}

.prose strong {
  font-weight: 600;
}

.prose ul {
  list-style-type: disc;
  margin-top: 1.25em;
  margin-bottom: 1.25em;
  padding-left: 1.625em;
}

.prose ol {
  list-style-type: decimal;
  margin-top: 1.25em;
  margin-bottom: 1.25em;
  padding-left: 1.625em;
}

.prose img {
  max-width: 100%;
  height: auto;
}
</style>
