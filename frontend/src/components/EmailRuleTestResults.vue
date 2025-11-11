<template>
  <!-- Modal Backdrop -->
  <Transition
    enter-active-class="transition-opacity duration-300 ease-out"
    enter-from-class="opacity-0"
    enter-to-class="opacity-100"
    leave-active-class="transition-opacity duration-200 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="show"
      class="fixed inset-0 bg-gray-500/75 z-40"
      @click="$emit('close')"
    />
  </Transition>

  <!-- Slide-over Panel -->
  <Transition
    enter-active-class="transition-transform duration-300 ease-out"
    enter-from-class="translate-x-full"
    enter-to-class="translate-x-0"
    leave-active-class="transition-transform duration-200 ease-in"
    leave-from-class="translate-x-0"
    leave-to-class="translate-x-full"
  >
    <div
      v-if="show"
      class="fixed inset-y-0 right-0 flex max-w-full pl-10 z-50"
      @keydown.esc="$emit('close')"
    >
      <div class="w-screen max-w-2xl">
        <div class="flex h-full flex-col overflow-y-scroll bg-white shadow-xl">
          <!-- Header -->
          <div class="bg-gradient-to-r from-indigo-500 to-indigo-600 px-6 py-6">
            <div class="flex items-start justify-between">
              <div>
                <h2 class="text-2xl font-bold text-white">Rule Test Results</h2>
                <p v-if="results" class="mt-1 text-sm text-indigo-100">
                  Testing {{ results.rules_evaluated }} rules against Email #{{ results.email_id }}
                </p>
              </div>
              <button
                @click="$emit('close')"
                class="rounded-md text-indigo-100 hover:text-white focus:outline-none focus:ring-2 focus:ring-white transition-colors"
              >
                <XMarkIcon class="h-6 w-6" />
              </button>
            </div>
          </div>

          <!-- Content -->
          <div class="flex-1 px-6 py-6">
            <!-- Loading State -->
            <div v-if="loading" class="flex flex-col items-center justify-center py-12">
              <div class="animate-spin rounded-full h-16 w-16 border-b-4 border-indigo-600 mb-4"></div>
              <p class="text-lg font-medium text-gray-700">Testing rules against email...</p>
              <p class="text-sm text-gray-500 mt-2">This may take a few seconds</p>
            </div>

            <!-- Error State -->
            <div v-else-if="error" class="rounded-lg bg-red-50 border border-red-200 p-4">
              <div class="flex items-start gap-3">
                <ExclamationTriangleIcon class="h-6 w-6 text-red-600 flex-shrink-0" />
                <div class="flex-1">
                  <h3 class="text-sm font-medium text-red-800">Error Testing Rules</h3>
                  <p class="text-sm text-red-700 mt-1">{{ error }}</p>
                  <button
                    @click="$emit('retest')"
                    class="mt-3 inline-flex items-center gap-2 px-3 py-1.5 text-sm font-medium text-red-700 hover:text-red-800 transition-colors"
                  >
                    Try Again
                  </button>
                </div>
              </div>
            </div>

            <!-- Results Display -->
            <div v-else-if="results" class="space-y-6">
              <!-- Summary Cards -->
              <div class="grid grid-cols-3 gap-4">
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <p class="text-sm font-medium text-gray-600">Rules Evaluated</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1">{{ results.rules_evaluated }}</p>
                </div>
                <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                  <p class="text-sm font-medium text-green-700">Matched</p>
                  <p class="text-2xl font-bold text-green-900 mt-1">{{ results.matched_rules.length }}</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                  <p class="text-sm font-medium text-gray-600">Execution Time</p>
                  <p class="text-2xl font-bold text-gray-900 mt-1">{{ results.execution_time_ms }}ms</p>
                </div>
              </div>

              <!-- AI Classification -->
              <div
                v-if="results.ai_classification.is_automated !== null || results.ai_classification.needs_response !== null"
                class="bg-blue-50 rounded-lg p-4 border border-blue-200"
              >
                <h3 class="text-sm font-bold text-blue-900 mb-3 flex items-center gap-2">
                  <SparklesIcon class="h-5 w-5" />
                  AI Classification
                </h3>
                <div class="space-y-2">
                  <div v-if="results.ai_classification.is_automated !== null" class="flex items-center gap-2">
                    <span
                      :class="results.ai_classification.is_automated ? 'bg-blue-100 text-blue-800' : 'bg-green-100 text-green-800'"
                      class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium"
                    >
                      <component :is="results.ai_classification.is_automated ? CpuChipIcon : UserIcon" class="h-4 w-4" />
                      {{ results.ai_classification.is_automated ? 'Automated Email' : 'Human Email' }}
                    </span>
                  </div>
                  <div v-if="results.ai_classification.needs_response !== null" class="flex items-center gap-2">
                    <span
                      :class="results.ai_classification.needs_response ? 'bg-orange-100 text-orange-800' : 'bg-gray-100 text-gray-800'"
                      class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-medium"
                    >
                      <component :is="results.ai_classification.needs_response ? ChatBubbleLeftRightIcon : CheckCircleIcon" class="h-4 w-4" />
                      {{ results.ai_classification.needs_response ? 'Needs Response' : 'No Response Needed' }}
                    </span>
                  </div>
                </div>
              </div>

              <!-- Matched Rules Section -->
              <div>
                <h3 class="text-lg font-bold text-gray-900 mb-4">
                  Matched Rules ({{ results.matched_rules.length }})
                </h3>
                <div v-if="results.matched_rules.length === 0" class="text-sm text-gray-500 italic">
                  No rules matched this email
                </div>
                <div v-else class="space-y-4">
                  <div
                    v-for="rule in results.matched_rules"
                    :key="rule.rule_id"
                    class="bg-green-50 rounded-lg p-4 border border-green-200"
                  >
                    <div class="flex items-start justify-between gap-3 mb-2">
                      <h4 class="text-base font-bold text-green-900">{{ rule.rule_name }}</h4>
                      <span
                        :class="rule.match_type === 'simple' ? 'bg-blue-100 text-blue-800' : 'bg-purple-100 text-purple-800'"
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium flex-shrink-0"
                      >
                        {{ rule.match_type === 'simple' ? 'Simple Match' : 'AI Match' }}
                      </span>
                    </div>
                    <p v-if="rule.rule_description" class="text-sm text-gray-700 mb-3">
                      {{ rule.rule_description }}
                    </p>
                    <div v-if="rule.actions.length > 0">
                      <p class="text-sm font-semibold text-gray-700 mb-2">Actions that would be executed:</p>
                      <ul class="space-y-1.5">
                        <li
                          v-for="(action, idx) in rule.actions"
                          :key="idx"
                          class="flex items-start gap-2 text-sm text-gray-700"
                        >
                          <component :is="getActionIcon(action.action_type)" class="h-5 w-5 text-indigo-600 flex-shrink-0 mt-0.5" />
                          <span>{{ action.description }}</span>
                        </li>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>

              <!-- Non-Matched Rules Section (Collapsible) -->
              <div v-if="results.non_matched_rules.length > 0">
                <button
                  @click="showNonMatched = !showNonMatched"
                  class="flex items-center justify-between w-full text-left mb-3 hover:bg-gray-50 rounded-lg p-3 transition-colors"
                >
                  <h3 class="text-lg font-bold text-gray-700">
                    Rules That Didn't Match ({{ results.non_matched_rules.length }})
                  </h3>
                  <ChevronDownIcon
                    :class="showNonMatched ? 'rotate-180' : ''"
                    class="h-5 w-5 text-gray-400 transition-transform"
                  />
                </button>
                <Transition
                  enter-active-class="transition-all duration-200 ease-out"
                  enter-from-class="opacity-0 max-h-0"
                  enter-to-class="opacity-100 max-h-screen"
                  leave-active-class="transition-all duration-200 ease-in"
                  leave-from-class="opacity-100 max-h-screen"
                  leave-to-class="opacity-0 max-h-0"
                >
                  <div v-if="showNonMatched" class="space-y-2">
                    <div
                      v-for="rule in results.non_matched_rules"
                      :key="rule.rule_id"
                      class="bg-gray-50 rounded-lg p-3 border border-gray-200"
                    >
                      <h4 class="text-sm font-medium text-gray-700">{{ rule.rule_name }}</h4>
                      <p v-if="rule.rule_description" class="text-xs text-gray-600 mt-1">
                        {{ rule.rule_description }}
                      </p>
                    </div>
                  </div>
                </Transition>
              </div>
            </div>
          </div>

          <!-- Footer -->
          <div class="border-t border-gray-200 px-6 py-4 bg-gray-50">
            <div class="flex items-center justify-between">
              <p v-if="results" class="text-sm text-gray-500">
                Completed in {{ results.execution_time_ms }}ms
              </p>
              <div class="flex items-center gap-3">
                <button
                  v-if="!loading"
                  @click="$emit('retest')"
                  class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  <ArrowPathIcon class="h-4 w-4" />
                  Test Again
                </button>
                <button
                  @click="$emit('close')"
                  class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 rounded-lg hover:bg-indigo-700 transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                >
                  Close
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Transition>
</template>

<script setup>
import { ref } from 'vue'
import {
  XMarkIcon,
  ExclamationTriangleIcon,
  SparklesIcon,
  CpuChipIcon,
  UserIcon,
  ChatBubbleLeftRightIcon,
  CheckCircleIcon,
  ChevronDownIcon,
  ArrowPathIcon,
  TagIcon,
  PaperAirplaneIcon,
  BellAlertIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  show: {
    type: Boolean,
    required: true,
  },
  results: {
    type: Object,
    default: null,
  },
  loading: {
    type: Boolean,
    default: false,
  },
  error: {
    type: String,
    default: null,
  },
})

defineEmits(['close', 'retest'])

const showNonMatched = ref(false)

const getActionIcon = (actionType) => {
  switch (actionType) {
    case 'add_label':
      return TagIcon
    case 'forward':
      return PaperAirplaneIcon
    case 'add_reminder':
      return BellAlertIcon
    default:
      return CheckCircleIcon
  }
}
</script>
