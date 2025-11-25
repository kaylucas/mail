<template>
  <TransitionRoot :show="true" as="template">
    <Dialog as="div" class="relative z-50" @close="handleCancel">
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
            <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white dark:bg-gray-900 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl">
              <div class="bg-white dark:bg-gray-900 px-6 pt-6 pb-4 border-b border-gray-200 dark:border-gray-800">
                <div class="flex items-center justify-between">
                  <DialogTitle as="h3" class="text-xl font-semibold text-gray-900 dark:text-white">
                    {{ isEditMode ? 'Edit Email View' : 'Create Email View' }}
                  </DialogTitle>
                  <button
                    @click="handleCancel"
                    class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200 transition-colors"
                  >
                    <XMarkIcon class="h-6 w-6" />
                  </button>
                </div>
              </div>

              <form @submit.prevent="handleSubmit" class="px-6 py-6 space-y-6">
                <div v-if="formError" class="bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg p-4">
                  <div class="flex items-start gap-2">
                    <ExclamationCircleIcon class="h-5 w-5 text-red-600 dark:text-red-400 flex-shrink-0 mt-0.5" />
                    <p class="text-sm text-red-800 dark:text-red-200">{{ formError }}</p>
                  </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                  <div class="space-y-4">
                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                        View Name <span class="text-red-500">*</span>
                      </label>
                      <input
                        v-model="formData.name"
                        type="text"
                        required
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        placeholder="e.g., GitHub Alerts"
                      />
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                        Description
                      </label>
                      <textarea
                        v-model="formData.description"
                        rows="3"
                        class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        placeholder="Optional description"
                      />
                    </div>

                    <div>
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                        Icon
                      </label>
                      <div class="grid grid-cols-3 gap-2">
                        <button
                          v-for="icon in iconOptions"
                          :key="icon.value"
                          type="button"
                          @click="formData.icon = icon.value"
                          :class="[
                            'flex items-center gap-2 px-3 py-2 rounded-lg border text-sm font-medium transition-colors',
                            formData.icon === icon.value
                              ? 'border-indigo-500 bg-indigo-50 dark:bg-indigo-900/30 text-indigo-600 dark:text-indigo-300'
                              : 'border-gray-200 dark:border-gray-700 text-gray-600 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800'
                          ]"
                        >
                          <component :is="icon.icon" class="h-4 w-4" />
                          {{ icon.label }}
                        </button>
                      </div>
                    </div>

                    <div class="flex items-center gap-4">
                      <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                          Color
                        </label>
                        <input
                          v-model="formData.color"
                          type="color"
                          class="w-full h-10 rounded-lg border border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800"
                        />
                      </div>
                      <div class="flex-1">
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200 mb-1">
                          Order
                        </label>
                        <input
                          v-model.number="formData.order"
                          type="number"
                          min="0"
                          class="block w-full rounded-lg border-gray-300 dark:border-gray-700 bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        />
                      </div>
                    </div>

                    <div class="flex items-center justify-between py-3 border-t border-gray-200 dark:border-gray-800">
                      <div>
                        <label class="text-sm font-medium text-gray-700 dark:text-gray-200">
                          Visible in sidebar
                        </label>
                        <p class="text-xs text-gray-500 dark:text-gray-400">Hide views without deleting them</p>
                      </div>
                      <button
                        type="button"
                        @click="formData.is_visible = !formData.is_visible"
                        :class="[
                          'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                          formData.is_visible ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'
                        ]"
                      >
                        <span
                          :class="[
                            'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                            formData.is_visible ? 'translate-x-5' : 'translate-x-0'
                          ]"
                        />
                      </button>
                    </div>
                  </div>

                  <div class="space-y-4">
                    <div class="flex items-center justify-between">
                      <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                        Associated Rules <span class="text-red-500">*</span>
                      </label>
                      <span class="text-xs text-gray-500 dark:text-gray-400">Select at least one</span>
                    </div>

                    <div
                      v-if="rulesLoading"
                      class="space-y-2"
                    >
                      <div v-for="i in 3" :key="`rules-skeleton-${i}`" class="h-10 rounded-lg bg-gray-100 dark:bg-gray-800 animate-pulse" />
                    </div>

                    <div v-else-if="availableRules.length === 0" class="p-4 rounded-lg border border-dashed border-gray-300 dark:border-gray-700 text-center text-sm text-gray-500 dark:text-gray-400">
                      No rules available. Create a rule first.
                    </div>

                    <div v-else class="space-y-2 max-h-72 overflow-y-auto pr-1">
                      <label
                        v-for="rule in availableRules"
                        :key="rule.id"
                        class="flex items-start gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-800 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer"
                      >
                        <input
                          type="checkbox"
                          class="mt-1 h-4 w-4 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                          :value="rule.id"
                          v-model="formData.rule_ids"
                        />
                        <div>
                          <p class="text-sm font-medium text-gray-900 dark:text-white">{{ rule.name }}</p>
                          <p class="text-xs text-gray-500 dark:text-gray-400" v-if="rule.description">{{ rule.description }}</p>
                        </div>
                      </label>
                    </div>
                  </div>
                </div>
              </form>

              <div class="bg-gray-50 dark:bg-gray-900/60 px-6 py-4 border-t border-gray-200 dark:border-gray-800 flex items-center justify-end gap-3">
                <button
                  type="button"
                  @click="handleCancel"
                  :disabled="saving"
                  class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="button"
                  @click="handleSubmit"
                  :disabled="saving"
                  class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  <span v-if="saving">Saving...</span>
                  <span v-else>{{ isEditMode ? 'Update View' : 'Create View' }}</span>
                </button>
              </div>
            </DialogPanel>
          </TransitionChild>
        </div>
      </div>
    </Dialog>
  </TransitionRoot>
</template>

<script setup>
import { computed, reactive, ref, watch, onMounted } from 'vue'
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionChild,
  TransitionRoot
} from '@headlessui/vue'
import {
  XMarkIcon,
  ExclamationCircleIcon,
  FolderIcon,
  InboxIcon,
  TagIcon,
  ShoppingCartIcon,
  SparklesIcon
} from '@heroicons/vue/24/outline'
import { useViews } from '../composables/useViews'
import { useEmailRules } from '../composables/useEmailRules'

const props = defineProps({
  view: {
    type: Object,
    default: null
  },
  mode: {
    type: String,
    default: 'create'
  }
})

const emit = defineEmits(['saved', 'cancelled'])

const { createView, updateView } = useViews()
const { rules, fetchRules, loading: rulesLoading } = useEmailRules()

const saving = ref(false)
const formError = ref(null)

const formData = reactive({
  name: '',
  description: '',
  icon: 'folder',
  color: '#6366F1',
  is_visible: true,
  order: 0,
  rule_ids: []
})

const iconOptions = [
  { value: 'folder', label: 'Folder', icon: FolderIcon },
  { value: 'inbox', label: 'Inbox', icon: InboxIcon },
  { value: 'tag', label: 'Tag', icon: TagIcon },
  { value: 'shopping-cart', label: 'Shopping', icon: ShoppingCartIcon },
  { value: 'sparkles', label: 'Highlights', icon: SparklesIcon }
]

const availableRules = computed(() => rules.value || [])
const isEditMode = computed(() => props.mode === 'edit' && !!props.view)

const initializeForm = () => {
  if (props.view) {
    formData.name = props.view.name || ''
    formData.description = props.view.description || ''
    formData.icon = props.view.icon || 'folder'
    formData.color = props.view.color || '#6366F1'
    formData.is_visible = props.view.is_visible ?? true
    formData.order = props.view.order ?? 0
    formData.rule_ids = props.view.rules ? props.view.rules.map(rule => rule.id) : []
  } else {
    formData.name = ''
    formData.description = ''
    formData.icon = 'folder'
    formData.color = '#6366F1'
    formData.is_visible = true
    formData.order = 0
    formData.rule_ids = []
  }
  formError.value = null
}

watch(() => props.view, () => {
  initializeForm()
})

onMounted(() => {
  initializeForm()
  if (!rules.value.length) {
    fetchRules().catch(() => {})
  }
})

const validateForm = () => {
  if (!formData.name.trim()) {
    formError.value = 'View name is required.'
    return false
  }
  if (!formData.rule_ids.length) {
    formError.value = 'Select at least one rule for this view.'
    return false
  }
  formError.value = null
  return true
}

const handleSubmit = async () => {
  if (!validateForm()) {
    return
  }

  const payload = {
    name: formData.name,
    description: formData.description || null,
    icon: formData.icon,
    color: formData.color,
    is_visible: formData.is_visible,
    order: formData.order ?? 0,
    rule_ids: formData.rule_ids
  }

  saving.value = true
  try {
    let result
    if (isEditMode.value && props.view) {
      result = await updateView(props.view.id, payload)
    } else {
      result = await createView(payload)
    }
    emit('saved', result)
  } catch (error) {
    formError.value = error.response?.data?.message || 'Failed to save email view.'
  } finally {
    saving.value = false
  }
}

const handleCancel = () => {
  emit('cancelled')
}
</script>
