<template>
  <AppLayout>
    <div class="flex-1 flex flex-col overflow-hidden">
      <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-800 px-6 py-6">
        <div class="max-w-5xl mx-auto flex flex-col gap-2">
          <div class="flex items-center justify-between flex-wrap gap-3">
            <div>
              <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Manage Views</h1>
              <p class="text-sm text-gray-600 dark:text-gray-400">
                Create curated collections of rules to power your sidebar
              </p>
            </div>
            <button
              @click="handleCreateClick"
              class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            >
              <PlusIcon class="h-5 w-5" />
              New View
            </button>
          </div>
        </div>
      </header>

      <main class="flex-1 overflow-y-auto px-6 py-6 bg-gray-50 dark:bg-gray-950">
        <div class="max-w-5xl mx-auto">
          <EmailViewsList ref="viewsListRef" />
        </div>
      </main>
    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import AppLayout from '../layouts/AppLayout.vue'
import EmailViewsList from '../components/EmailViewsList.vue'
import { PlusIcon } from '@heroicons/vue/24/outline'

const viewsListRef = ref(null)

const handleCreateClick = () => {
  viewsListRef.value?.openCreateModal?.()
}
</script>
