<template>
  <div class="space-y-4">
    <!-- Header with Create Button -->
    <div class="flex items-center justify-between">
      <div>
        <p class="text-sm text-gray-600">
          Create AI-powered rules to automatically process incoming emails
        </p>
      </div>
      <button
        @click="showForm = true"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
      >
        <PlusIcon class="h-5 w-5" />
        Create Rule
      </button>
    </div>

    <!-- Error Message -->
    <div v-if="error" class="bg-red-50 border border-red-200 rounded-lg p-4">
      <div class="flex items-start gap-2">
        <ExclamationCircleIcon class="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
        <div class="flex-1">
          <p class="text-sm text-red-800">{{ error }}</p>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading && rules.length === 0" class="space-y-3">
      <div v-for="i in 3" :key="i" class="bg-white border border-gray-200 rounded-lg p-4 animate-pulse">
        <div class="flex items-center justify-between">
          <div class="flex-1 space-y-3">
            <div class="h-4 bg-gray-200 rounded w-1/4"></div>
            <div class="h-3 bg-gray-200 rounded w-1/2"></div>
          </div>
          <div class="h-6 w-12 bg-gray-200 rounded"></div>
        </div>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="isEmpty" class="bg-white border border-gray-200 rounded-lg p-8 text-center">
      <SparklesIcon class="h-12 w-12 text-gray-400 mx-auto mb-4" />
      <h3 class="text-lg font-medium text-gray-900 mb-2">No email rules yet</h3>
      <p class="text-sm text-gray-600 mb-4">
        Create your first AI-powered rule to automatically process your emails
      </p>
      <button
        @click="showForm = true"
        class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 transition-colors"
      >
        <PlusIcon class="h-5 w-5" />
        Create Your First Rule
      </button>
    </div>

    <!-- Rules List -->
    <div v-else class="space-y-3">
      <div
        v-for="rule in rules"
        :key="rule.id"
        class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow"
      >
        <div class="flex items-start gap-4">
          <!-- Active Toggle -->
          <div class="flex-shrink-0 pt-1">
            <button
              @click="handleToggleRule(rule.id)"
              :class="[
                'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                rule.is_active ? 'bg-indigo-600' : 'bg-gray-200'
              ]"
              role="switch"
              :aria-checked="rule.is_active"
            >
              <span
                :class="[
                  'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                  rule.is_active ? 'translate-x-5' : 'translate-x-0'
                ]"
              />
            </button>
          </div>

          <!-- Rule Details -->
          <div class="flex-1 min-w-0">
            <div class="flex items-start justify-between gap-4">
              <div class="flex-1 min-w-0">
                <h3 class="text-base font-semibold text-gray-900 flex items-center gap-2">
                  {{ rule.name }}
                  <span
                    v-if="!rule.is_active"
                    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-gray-100 text-gray-600"
                  >
                    Inactive
                  </span>
                </h3>
                <p v-if="rule.description" class="text-sm text-gray-600 mt-1">
                  {{ rule.description }}
                </p>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-2">
                <button
                  @click="handleEditRule(rule)"
                  class="p-1.5 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50 rounded transition-colors"
                  title="Edit rule"
                >
                  <PencilIcon class="h-5 w-5" />
                </button>
                <button
                  @click="handleDeleteRule(rule)"
                  class="p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                  title="Delete rule"
                >
                  <TrashIcon class="h-5 w-5" />
                </button>
              </div>
            </div>

            <!-- Rule Stats -->
            <div class="flex items-center gap-4 mt-3 text-xs text-gray-500">
              <span class="flex items-center gap-1">
                <BoltIcon class="h-4 w-4" />
                Priority: {{ rule.priority || 0 }}
              </span>
              <span class="flex items-center gap-1">
                <CheckCircleIcon class="h-4 w-4" />
                {{ rule.actions?.length || 0 }} action(s)
              </span>
              <span v-if="rule.ai_provider" class="flex items-center gap-1">
                <SparklesIcon class="h-4 w-4" />
                {{ rule.ai_provider }}
              </span>
            </div>

            <!-- Conditions Preview -->
            <div v-if="hasConditions(rule)" class="mt-3 flex flex-wrap gap-2">
              <span
                v-if="rule.simple_conditions?.from"
                class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-700 text-xs rounded"
              >
                From: {{ rule.simple_conditions.from }}
              </span>
              <span
                v-if="rule.simple_conditions?.to"
                class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-700 text-xs rounded"
              >
                To: {{ rule.simple_conditions.to }}
              </span>
              <span
                v-if="rule.simple_conditions?.subject"
                class="inline-flex items-center px-2 py-1 bg-blue-50 text-blue-700 text-xs rounded"
              >
                Subject: {{ rule.simple_conditions.subject }}
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Rule Form Modal -->
    <EmailRuleForm
      v-if="showForm"
      :rule="editingRule"
      @saved="handleRuleSaved"
      @cancelled="handleFormCancelled"
    />

    <!-- Delete Confirmation Dialog -->
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
              <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white px-4 pb-4 pt-5 text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-lg sm:p-6">
                <div class="sm:flex sm:items-start">
                  <div class="mx-auto flex h-12 w-12 flex-shrink-0 items-center justify-center rounded-full bg-red-100 sm:mx-0 sm:h-10 sm:w-10">
                    <ExclamationTriangleIcon class="h-6 w-6 text-red-600" />
                  </div>
                  <div class="mt-3 text-center sm:ml-4 sm:mt-0 sm:text-left">
                    <DialogTitle as="h3" class="text-base font-semibold leading-6 text-gray-900">
                      Delete Email Rule
                    </DialogTitle>
                    <div class="mt-2">
                      <p class="text-sm text-gray-500">
                        Are you sure you want to delete "{{ deletingRule?.name }}"? This action cannot be undone.
                      </p>
                    </div>
                  </div>
                </div>
                <div class="mt-5 sm:mt-4 sm:flex sm:flex-row-reverse gap-3">
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
                    class="mt-3 inline-flex w-full justify-center rounded-lg bg-white px-4 py-2.5 text-sm font-semibold text-gray-900 shadow-sm ring-1 ring-inset ring-gray-300 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 sm:mt-0 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
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
import { ref, onMounted } from 'vue'
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionRoot,
  TransitionChild
} from '@headlessui/vue'
import {
  PlusIcon,
  PencilIcon,
  TrashIcon,
  SparklesIcon,
  BoltIcon,
  CheckCircleIcon,
  ExclamationCircleIcon,
  ExclamationTriangleIcon
} from '@heroicons/vue/24/outline'
import { useEmailRules } from '../composables/useEmailRules'
import EmailRuleForm from './EmailRuleForm.vue'

const {
  rules,
  loading,
  error,
  isEmpty,
  fetchRules,
  toggleRule,
  deleteRule
} = useEmailRules()

// Local state
const showForm = ref(false)
const editingRule = ref(null)
const showDeleteDialog = ref(false)
const deletingRule = ref(null)
const deleting = ref(false)

// Methods
const handleToggleRule = async (ruleId) => {
  try {
    await toggleRule(ruleId)
  } catch (err) {
    console.error('Failed to toggle rule:', err)
  }
}

const handleEditRule = (rule) => {
  editingRule.value = rule
  showForm.value = true
}

const handleDeleteRule = (rule) => {
  deletingRule.value = rule
  showDeleteDialog.value = true
}

const confirmDelete = async () => {
  if (!deletingRule.value) return

  deleting.value = true
  try {
    await deleteRule(deletingRule.value.id)
    showDeleteDialog.value = false
    deletingRule.value = null
  } catch (err) {
    console.error('Failed to delete rule:', err)
  } finally {
    deleting.value = false
  }
}

const handleRuleSaved = async () => {
  showForm.value = false
  editingRule.value = null
  await fetchRules()
}

const handleFormCancelled = () => {
  showForm.value = false
  editingRule.value = null
}

const hasConditions = (rule) => {
  const conditions = rule.simple_conditions
  if (!conditions) return false
  return conditions.from || conditions.to || conditions.subject
}

// Lifecycle
onMounted(() => {
  fetchRules()
})
</script>
