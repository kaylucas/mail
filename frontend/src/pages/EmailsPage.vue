<template>
  <AppLayout>
    <div class="flex-1 flex flex-col overflow-hidden">
      <!-- Email Sync Status Indicator -->
      <EmailSyncIndicator />

      <!-- Header -->
      <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-6 py-4 transition-colors">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
          <div>
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ headerTitle }}</h1>
            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ headerSubtitle }}</p>
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
        :view-id="viewId"
        @email-selected="handleEmailSelected"
        @mark-read="handleMarkRead"
      />
    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { ArrowPathIcon } from '@heroicons/vue/24/outline'
import AppLayout from '../layouts/AppLayout.vue'
import EmailInbox from '../components/EmailInbox.vue'
import EmailSyncIndicator from '../components/EmailSyncIndicator.vue'
import { useViews } from '../composables/useViews'

const router = useRouter()
const route = useRoute()
const isRefreshing = ref(false)
const { views, fetchViews, findViewById } = useViews()

const viewId = computed(() => route.query.view || null)

const activeView = computed(() => {
  if (!viewId.value) return null
  return findViewById(viewId.value)
})

const headerTitle = computed(() => activeView.value?.name || 'Inbox')
const headerSubtitle = computed(() => {
  if (activeView.value) {
    return activeView.value.description || 'Emails filtered by this view'
  }
  return 'Manage your emails'
})

const handleEmailSelected = (email) => {
  // Navigate to EmailViewer page instead of opening modal
  router.push({ name: 'EmailViewer', params: { id: email.id } })
}

const handleMarkRead = (emailId, isRead) => {
  // Mark read handler - currently handled by EmailInbox component
  // Could be used for additional side effects in the future
}

const refreshEmails = async () => {
  isRefreshing.value = true
  // In a real implementation, you'd call the refresh method from useEmails
  setTimeout(() => {
    isRefreshing.value = false
  }, 1000)
}

onMounted(() => {
  if (!views.value.length) {
    fetchViews().catch(() => {})
  }
})
</script>
