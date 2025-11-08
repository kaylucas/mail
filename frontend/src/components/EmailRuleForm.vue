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
            <DialogPanel class="relative transform overflow-hidden rounded-lg bg-white text-left shadow-xl transition-all sm:my-8 sm:w-full sm:max-w-3xl">
              <!-- Header -->
              <div class="bg-white px-6 pt-6 pb-4 border-b border-gray-200">
                <div class="flex items-center justify-between">
                  <DialogTitle as="h3" class="text-xl font-semibold text-gray-900">
                    {{ isEditMode ? 'Edit Email Rule' : 'Create Email Rule' }}
                  </DialogTitle>
                  <button
                    @click="handleCancel"
                    class="text-gray-400 hover:text-gray-600 transition-colors"
                  >
                    <XMarkIcon class="h-6 w-6" />
                  </button>
                </div>
              </div>

              <!-- Form -->
              <form @submit.prevent="handleSubmit" class="px-6 py-6">
                <!-- Error Message -->
                <div v-if="formError" class="mb-6 bg-red-50 border border-red-200 rounded-lg p-4">
                  <div class="flex items-start gap-2">
                    <ExclamationCircleIcon class="h-5 w-5 text-red-600 flex-shrink-0 mt-0.5" />
                    <p class="text-sm text-red-800">{{ formError }}</p>
                  </div>
                </div>

                <div class="space-y-6">
                  <!-- Basic Info -->
                  <div class="space-y-4">
                    <div>
                      <label for="name" class="block text-sm font-medium text-gray-700 mb-1">
                        Rule Name <span class="text-red-500">*</span>
                      </label>
                      <input
                        id="name"
                        v-model="formData.name"
                        type="text"
                        required
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        placeholder="e.g., Urgent emails from CEO"
                      />
                    </div>

                    <div>
                      <label for="description" class="block text-sm font-medium text-gray-700 mb-1">
                        Description
                      </label>
                      <textarea
                        id="description"
                        v-model="formData.description"
                        rows="2"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        placeholder="Optional description of what this rule does"
                      />
                    </div>
                  </div>

                  <!-- Simple Conditions (Accordion) -->
                  <div class="border border-gray-200 rounded-lg">
                    <button
                      type="button"
                      @click="showConditions = !showConditions"
                      class="w-full flex items-center justify-between px-4 py-3 text-left hover:bg-gray-50 transition-colors"
                    >
                      <span class="text-sm font-medium text-gray-700">Simple Conditions (Optional)</span>
                      <ChevronDownIcon
                        :class="['h-5 w-5 text-gray-400 transition-transform', showConditions ? 'rotate-180' : '']"
                      />
                    </button>

                    <div v-if="showConditions" class="px-4 pb-4 space-y-3 border-t border-gray-200">
                      <p class="text-xs text-gray-600 pt-3">
                        Add basic filters to narrow down which emails this rule applies to
                      </p>

                      <div>
                        <label for="condition-from" class="block text-sm text-gray-700 mb-1">
                          From (sender)
                        </label>
                        <input
                          id="condition-from"
                          v-model="formData.simple_conditions.from"
                          type="text"
                          class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                          placeholder="e.g., boss@company.com"
                        />
                      </div>

                      <div>
                        <label for="condition-to" class="block text-sm text-gray-700 mb-1">
                          To (recipient)
                        </label>
                        <input
                          id="condition-to"
                          v-model="formData.simple_conditions.to"
                          type="text"
                          class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                          placeholder="e.g., support@company.com"
                        />
                      </div>

                      <div>
                        <label for="condition-subject" class="block text-sm text-gray-700 mb-1">
                          Subject contains
                        </label>
                        <input
                          id="condition-subject"
                          v-model="formData.simple_conditions.subject"
                          type="text"
                          class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                          placeholder="e.g., urgent"
                        />
                      </div>
                    </div>
                  </div>

                  <!-- AI Prompt -->
                  <div>
                    <label for="prompt" class="block text-sm font-medium text-gray-700 mb-1">
                      AI Processing Prompt <span class="text-red-500">*</span>
                    </label>
                    <textarea
                      id="prompt"
                      v-model="formData.prompt"
                      rows="4"
                      required
                      class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm font-mono"
                      placeholder="Tell the AI what to analyze and what actions to take. Example: Analyze this email for urgency. If it requires immediate attention, add the 'Urgent' label and create a reminder for today."
                    />
                    <p class="mt-1 text-xs text-gray-500">
                      This prompt guides the AI on how to process matching emails
                    </p>
                  </div>

                  <!-- Actions -->
                  <div>
                    <div class="flex items-center justify-between mb-3">
                      <label class="block text-sm font-medium text-gray-700">
                        Actions
                      </label>
                      <button
                        type="button"
                        @click="addAction"
                        class="inline-flex items-center gap-1 text-sm text-indigo-600 hover:text-indigo-700 font-medium"
                      >
                        <PlusIcon class="h-4 w-4" />
                        Add Action
                      </button>
                    </div>

                    <div v-if="formData.actions.length === 0" class="text-sm text-gray-500 italic">
                      No actions configured. Add at least one action above.
                    </div>

                    <div v-else class="space-y-3">
                      <div
                        v-for="(action, index) in formData.actions"
                        :key="index"
                        class="border border-gray-200 rounded-lg p-4"
                      >
                        <div class="flex items-start justify-between gap-4">
                          <div class="flex-1 space-y-3">
                            <!-- Action Type -->
                            <div>
                              <label :for="`action-type-${index}`" class="block text-sm font-medium text-gray-700 mb-1">
                                Action Type
                              </label>
                              <select
                                :id="`action-type-${index}`"
                                v-model="action.type"
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                              >
                                <option value="add_label">Add Label</option>
                                <option value="forward">Forward Email</option>
                                <option value="add_reminder">Add Reminder</option>
                              </select>
                            </div>

                            <!-- Add Label Config -->
                            <div v-if="action.type === 'add_label'">
                              <label :for="`action-label-${index}`" class="block text-sm text-gray-700 mb-1">
                                Label Name
                              </label>
                              <input
                                :id="`action-label-${index}`"
                                v-model="action.label_name"
                                type="text"
                                required
                                class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                placeholder="e.g., Important"
                              />
                            </div>

                            <!-- Forward Config -->
                            <div v-if="action.type === 'forward'" class="space-y-3">
                              <div>
                                <label :for="`action-forward-${index}`" class="block text-sm text-gray-700 mb-1">
                                  Forward to (comma-separated emails)
                                </label>
                                <input
                                  :id="`action-forward-${index}`"
                                  v-model="action.forwardTo"
                                  type="text"
                                  required
                                  class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                  placeholder="e.g., team@company.com, manager@company.com"
                                />
                              </div>
                              <div class="flex items-center gap-3">
                                <input
                                  :id="`action-note-checkbox-${index}`"
                                  v-model="action.includeNote"
                                  type="checkbox"
                                  class="rounded border-gray-300"
                                />
                                <label :for="`action-note-checkbox-${index}`" class="text-sm text-gray-700">
                                  Include note when forwarding
                                </label>
                              </div>
                              <div v-if="action.includeNote">
                                <label :for="`action-note-text-${index}`" class="block text-sm text-gray-700 mb-1">
                                  Forwarding Note
                                </label>
                                <input
                                  :id="`action-note-text-${index}`"
                                  v-model="action.reminderMessage"
                                  type="text"
                                  class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                  placeholder="Optional note to include when forwarding"
                                />
                              </div>
                            </div>

                            <!-- Add Reminder Config -->
                            <div v-if="action.type === 'add_reminder'" class="space-y-3">
                              <div>
                                <label :for="`action-days-${index}`" class="block text-sm text-gray-700 mb-1">
                                  Remind After (days)
                                </label>
                                <input
                                  :id="`action-days-${index}`"
                                  v-model.number="action.days_after"
                                  type="number"
                                  min="0"
                                  required
                                  class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                  placeholder="e.g., 1"
                                />
                              </div>
                              <div>
                                <label :for="`action-message-${index}`" class="block text-sm text-gray-700 mb-1">
                                  Reminder Message
                                </label>
                                <input
                                  :id="`action-message-${index}`"
                                  v-model="action.message"
                                  type="text"
                                  class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                                  placeholder="e.g., Follow up on this request"
                                />
                              </div>
                            </div>
                          </div>

                          <!-- Remove Button -->
                          <button
                            type="button"
                            @click="removeAction(index)"
                            class="flex-shrink-0 p-1.5 text-gray-400 hover:text-red-600 hover:bg-red-50 rounded transition-colors"
                            title="Remove action"
                          >
                            <TrashIcon class="h-5 w-5" />
                          </button>
                        </div>
                      </div>
                    </div>
                  </div>

                  <!-- Advanced Settings -->
                  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                      <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">
                        Priority
                      </label>
                      <input
                        id="priority"
                        v-model.number="formData.priority"
                        type="number"
                        min="0"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        placeholder="0"
                      />
                      <p class="mt-1 text-xs text-gray-500">
                        Higher priority rules run first
                      </p>
                    </div>

                    <div>
                      <label for="ai_provider" class="block text-sm font-medium text-gray-700 mb-1">
                        AI Provider
                      </label>
                      <select
                        id="ai_provider"
                        v-model="formData.ai_provider"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                      >
                        <option value="default">Default</option>
                        <option value="anthropic">Anthropic (Claude)</option>
                        <option value="openai">OpenAI (GPT)</option>
                        <option value="gemini">Google (Gemini)</option>
                      </select>
                    </div>

                    <div class="md:col-span-2">
                      <label for="ai_model" class="block text-sm font-medium text-gray-700 mb-1">
                        AI Model Override (Optional)
                      </label>
                      <input
                        id="ai_model"
                        v-model="formData.ai_model"
                        type="text"
                        class="block w-full rounded-lg border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 text-sm"
                        placeholder="e.g., gpt-4, claude-3-opus"
                      />
                      <p class="mt-1 text-xs text-gray-500">
                        Leave empty to use default model for selected provider
                      </p>
                    </div>
                  </div>

                  <!-- Is Active Toggle -->
                  <div class="flex items-center justify-between py-3 border-t border-gray-200">
                    <div>
                      <label for="is_active" class="text-sm font-medium text-gray-700">
                        Enable this rule
                      </label>
                      <p class="text-xs text-gray-500 mt-1">
                        Inactive rules will not process emails
                      </p>
                    </div>
                    <button
                      type="button"
                      @click="formData.is_active = !formData.is_active"
                      :class="[
                        'relative inline-flex h-6 w-11 flex-shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2',
                        formData.is_active ? 'bg-indigo-600' : 'bg-gray-200'
                      ]"
                      role="switch"
                      :aria-checked="formData.is_active"
                    >
                      <span
                        :class="[
                          'pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out',
                          formData.is_active ? 'translate-x-5' : 'translate-x-0'
                        ]"
                      />
                    </button>
                  </div>
                </div>
              </form>

              <!-- Footer -->
              <div class="bg-gray-50 px-6 py-4 border-t border-gray-200 flex items-center justify-end gap-3">
                <button
                  type="button"
                  @click="handleCancel"
                  :disabled="saving"
                  class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  Cancel
                </button>
                <button
                  type="submit"
                  @click="handleSubmit"
                  :disabled="saving || !isFormValid"
                  class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                >
                  <span v-if="saving">Saving...</span>
                  <span v-else>{{ isEditMode ? 'Update Rule' : 'Create Rule' }}</span>
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
import { ref, computed, onMounted } from 'vue'
import {
  Dialog,
  DialogPanel,
  DialogTitle,
  TransitionRoot,
  TransitionChild
} from '@headlessui/vue'
import {
  XMarkIcon,
  PlusIcon,
  TrashIcon,
  ChevronDownIcon,
  ExclamationCircleIcon
} from '@heroicons/vue/24/outline'
import { useEmailRules } from '../composables/useEmailRules'

const props = defineProps({
  rule: {
    type: Object,
    default: null
  }
})

const emit = defineEmits(['saved', 'cancelled'])

const { createRule, updateRule } = useEmailRules()

// Local state
const saving = ref(false)
const formError = ref(null)
const showConditions = ref(false)

const formData = ref({
  name: '',
  description: '',
  simple_conditions: {
    from: '',
    to: '',
    subject: ''
  },
  prompt: '',
  is_active: true,
  priority: 0,
  ai_provider: 'default',
  ai_model: '',
  actions: []
})

// Computed
const isEditMode = computed(() => props.rule !== null)

const isFormValid = computed(() => {
  return formData.value.name.trim() !== '' && formData.value.prompt.trim() !== ''
})

// Methods
const addAction = () => {
  formData.value.actions.push({
    type: 'add_label',
    labelName: '',
    forwardTo: '',
    includeNote: false,
    daysAfter: 1,
    reminderMessage: ''
  })
}

const removeAction = (index) => {
  formData.value.actions.splice(index, 1)
}

const handleSubmit = async () => {
  if (!isFormValid.value) return

  saving.value = true
  formError.value = null

  try {
    // Clean up empty simple conditions
    const cleanedData = {
      ...formData.value,
      simple_conditions: Object.fromEntries(
        Object.entries(formData.value.simple_conditions).filter(([_, v]) => v !== '')
      )
    }

    // Build actions with correct backend structure: { action_type, action_config }
    cleanedData.actions = cleanedData.actions.map(action => {
      const actionConfig = {}

      if (action.type === 'add_label') {
        actionConfig.label_name = action.labelName
      } else if (action.type === 'forward') {
        actionConfig.email_addresses = action.forwardTo.split(',').map(e => e.trim()).filter(e => e)
        if (action.includeNote === true) {
          actionConfig.include_note = true
        }
      } else if (action.type === 'add_reminder') {
        actionConfig.days_after = action.daysAfter
        actionConfig.message = action.reminderMessage
      }

      return {
        action_type: action.type,
        action_config: actionConfig
      }
    })

    if (isEditMode.value) {
      await updateRule(props.rule.id, cleanedData)
    } else {
      await createRule(cleanedData)
    }

    emit('saved')
  } catch (error) {
    formError.value = error.response?.data?.message || 'Failed to save rule. Please try again.'
    console.error('Failed to save rule:', error)
  } finally {
    saving.value = false
  }
}

const handleCancel = () => {
  emit('cancelled')
}

// Initialize form with rule data if editing
onMounted(() => {
  if (props.rule) {
    // Convert backend action format to form format
    const convertedActions = props.rule.actions ? props.rule.actions.map(action => {
      const config = action.action_config || action.actionConfig || {}
      const base = {
        type: action.action_type || action.actionType,
        labelName: '',
        forwardTo: '',
        includeNote: false,
        daysAfter: 1,
        reminderMessage: ''
      }

      if (base.type === 'add_label') {
        base.labelName = config.label_name || ''
      } else if (base.type === 'forward') {
        base.forwardTo = Array.isArray(config.email_addresses) ? config.email_addresses.join(', ') : ''
        base.includeNote = config.include_note === true
      } else if (base.type === 'add_reminder') {
        base.daysAfter = config.days_after || 1
        base.reminderMessage = config.message || ''
      }

      return base
    }) : []

    formData.value = {
      name: props.rule.name || '',
      description: props.rule.description || '',
      simple_conditions: props.rule.simple_conditions || { from: '', to: '', subject: '' },
      prompt: props.rule.prompt || '',
      is_active: props.rule.is_active !== undefined ? props.rule.is_active : true,
      priority: props.rule.priority || 0,
      ai_provider: props.rule.ai_provider || 'default',
      ai_model: props.rule.ai_model || '',
      actions: convertedActions
    }

    // Show conditions if any are set
    if (props.rule.simple_conditions && Object.keys(props.rule.simple_conditions).length > 0) {
      showConditions.value = true
    }
  }
})
</script>
