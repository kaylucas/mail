<template>
  <div class="space-y-4">
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-gray-600 dark:text-gray-300">
          Organize related rules into named views for quick access in the sidebar
        </p>
      </div>
      <button
        @click="openCreateModal"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
      >
        <PlusIcon class="h-5 w-5" />
        Create View
      </button>
    </div>

    <div v-if="error" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
      <div class="flex items-start gap-2">
        <ExclamationCircleIcon class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
        <div class="flex-1">
          <p class="text-sm text-red-800 dark:text-red-200">{{ error }}</p>
        </div>
      </div>
    </div>

    <div v-if="loading && views.length === 0" class="space-y-3">
      <div v-for="i in 3" :key="`view-skeleton-${i}`" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 animate-pulse">
        <div class="flex items-center justify-between">
          <div class="flex-1 space-y-3">
            <div class="h-4 bg-gray-200 dark:bg-gray-700 rounded w-1/4"></div>
            <div class="h-3 bg-gray-200 dark:bg-gray-700 rounded w-1/2"></div>
          </div>
          <div class="h-6 w-12 bg-gray-200 dark:bg-gray-700 rounded"></div>
        </div>
      </div>
    </div>

    <div v-else-if="!loading && views.length === 0" class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-8 text-center transition-colors">
      <SparklesIcon class="h-12 w-12 text-gray-400 dark:text-gray-500 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-gray-900 dark:text-white mb-2">No views yet</h3>
      <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
        Create your first view to group related rules together
      </p>
      <button
        @click="openCreateModal"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
      >
        <PlusIcon class="h-5 w-5" />
        Create Your First View
      </button>
    </div>

    <div v-else class="space-y-3">
      <div
        v-for="view in views"
        :key="view.id"
        class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-lg p-4 hover:shadow-md transition-shadow"
      >
        <div class="flex items-start gap-4">
          <div class="flex-shrink-0">
            <component :is="getIcon(view.icon)" class="h-8 w-8" :style="view.color ? { color: view.color } : null" />
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <h3 class="text-base font-semibold text-gray-900 dark:text-white truncate">
                    {{ view.name }}
                  </h3>
                  <span
                    v-if="!view.is_visible"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 dark:bg-gray-700 text-gray-600 dark:text-gray-300"
                  >
                    Hidden
                  </span>
                </div>
                <p v-if="view.description" class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                  {{ view.description }}
                </p>
                <div class="flex items-center gap-4 mt-3 text-xs text-gray-500 dark:text-gray-400">
                  <span class="flex items-center gap-1">
                    <EnvelopeIcon class="h-4 w-4" />
                    {{ view.email_count }} emails
                  </span>
                  <span class="flex items-center gap-1" v-if="view.unread_count">
                    <EnvelopeOpenIcon class="h-4 w-4" />
                    {{ view.unread_count }} unread
                  </span>
                  <span class="flex items-center gap-1">
                    <TagIcon class="h-4 w-4" />
                    {{ view.rules?.length || 0 }} rule(s)
                  </span>
                </div>
              </div>
              <div class="flex items-center gap-2">
                <button
                  @click="handleToggleVisibility(view.id)"
                  class="p-1.5 rounded-lg text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 transition-colors"
                  :title="view.is_visible ? 'Hide from sidebar' : 'Show in sidebar'"
                >
                  <EyeSlashIcon v-if="view.is_visible" class="h-5 w-5" />
                  <EyeIcon v-else class="h-5 w-5" />
                </button>
                <button
                  @click="openEditModal(view)"
                  class="p-1.5 text-gray-400 hover:text-indigo-600 dark:hover:text-indigo-400 hover:bg-indigo-50 dark:hover:bg-indigo-900/20 rounded transition-colors"
                  title="Edit view"
                >
                  <PencilIcon class="h-5 w-5" />
                </button>
                <button
                  @click="promptDelete(view)"
                  class="p-1.5 text-gray-400 hover:text-red-600 dark:hover:text-red-400 hover:bg-red-50 dark:hover:bg-red-900/20 rounded transition-colors"
                  title="Delete view"
                >
                  <TrashIcon class="h-5 w-5" />
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <EmailViewForm
      v-if="showForm"
      :view="editingView"
      :mode="editingView ? 'edit' : 'create'"
      @saved="handleViewSaved"
      @cancelled="handleFormCancelled"
    />

    <TransitionRoot :show="showDeleteDialog" as="template">
      <Dialog as="div" class="relative z-50" @close="showDeleteDialog = false">
        <TransitionChild
          as="template"
          enter="ease-out duration-300"
          enter-from="opacity-0"
          enter-to="opacity-100"
          leave="ease-in duration-200"
          leave-from="opacity-100"
          leave-to="opacity-0"
        >
          <div class="fixed inset-0 bg-gray-900 bg-opacity-50 backdrop-blur-sm transition-opacity" />
        </TransitionChild>

        <div class="fixed inset-0 z-10 overflow-y-auto">
          <div class="flex min-h-full items-center justify-center p-4">
            <TransitionChild
              as="template"
              enter="ease-out duration-300"
              enter-from="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
              enter-to="opacity-100 translate-y-0 sm:scale-100"
              leave="ease-in duration-200"
              leave-from="opacity-100 translate-y-0 sm:scale-100"
              leave-to="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
            >
              <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-900 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg">
                <div class="px-6 pt-6">
                  <div class="sm:flex sm:items-start">
                    <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                      <ExclamationTriangleIcon class="h-6 w-6 text-red-600" />
                    </div>
                    <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                      <DialogTitle as="h3" class="text-lg font-medium leading-6 text-gray-900 dark:text-white">
                        Delete Email View
                      </DialogTitle>
                      <div class="mt-2">
                        <p class="text-sm text-gray-500 dark:text-gray-300">
                          Are you sure you want to delete "{{ deletingView?.name }}"? This action cannot be undone.
                        </p>
                      </div>
                    </div>
                  </div>
                </div>
                <div class="bg-gray-50 dark:bg-gray-900/60 px-6 py-4 mt-4 flex flex-row-reverse gap-3">
                  <button
                    type="button"
                    :disabled="deleting"
                    @click="confirmDelete"
                    class="inline-flex w-full justify-center items-center gap-2 rounded-lg bg-red-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    <span v-if="deleting">Deleting...</span>
                    <span v-else>Delete</span>
                  </button>
                  <button
                    type="button"
                    :disabled="deleting"
                    @click="showDeleteDialog = false"
                    class="inline-flex w-full justify-center rounded-lg bg-white dark:bg-gray-800 px-4 py-2.5 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-sm ring-1 ring-inset ring-gray-300 dark:ring-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                  >
                    Cancel
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
import { ref, onMounted, defineExpose } from 'vue'
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionChild,
  TransitionRoot
} from '@headlessui/vue'
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  EyeIcon,
  EyeSlashIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon,
  SparklesIcon,
  TagIcon,
  EnvelopeIcon,
  EnvelopeOpenIcon,
  FolderIcon,
  InboxIcon,
  ShoppingCartIcon,
  CodeBracketIcon,
  BuildingStorefrontIcon
} from '@heroicons/vue/24/outline'
import { useViews } from '../composables/useViews'
import EmailViewForm from './EmailViewForm.vue'

const iconMap = {
  inbox: InboxIcon,
  folder: FolderIcon,
  tag: TagIcon,
  'shopping-cart': ShoppingCartIcon,
  github: CodeBracketIcon,
  storefront: BuildingStorefrontIcon,
  sparkles: SparklesIcon
}

const {
  views,
  loading,
  error,
  fetchViews,
  deleteView,
  toggleView
} = useViews()

const showForm = ref(false)
const editingView = ref(null)
const showDeleteDialog = ref(false)
const deletingView = ref(null)
const deleting = ref(false)

const openCreateModal = () => {
  editingView.value = null
  showForm.value = true
}

const openEditModal = (view) => {
  editingView.value = view
  showForm.value = true
}

const handleFormCancelled = () => {
  showForm.value = false
  editingView.value = null
}

const handleViewSaved = async () => {
  showForm.value = false
  editingView.value = null
  await fetchViews()
}

const promptDelete = (view) => {
  deletingView.value = view
  showDeleteDialog.value = true
}

const confirmDelete = async () => {
  if (!deletingView.value) return
  deleting.value = true
  try {
    await deleteView(deletingView.value.id)
    showDeleteDialog.value = false
    deletingView.value = null
  } catch (err) {
    console.error('Failed to delete view:', err)
  } finally {
    deleting.value = false
  }
}

const handleToggleVisibility = async (id) => {
  try {
    await toggleView(id)
  } catch (err) {
    console.error('Failed to toggle view visibility:', err)
  }
}

const getIcon = (icon) => {
  return iconMap[icon] || FolderIcon
}

onMounted(() => {
  fetchViews().catch(() => {})
})

const viewsArray = views

defineExpose({ openCreateModal })
</script>
