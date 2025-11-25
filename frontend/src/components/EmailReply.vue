<template>
  <div class="bg-white dark:bg-gray-900 border-t border-gray-200 dark:border-gray-800 p-4 transition-colors">
    <div class="max-w-4xl mx-auto">
      <div class="flex items-start gap-4">
        <!-- Reply Icon -->
        <div class="flex-shrink-0 mt-1">
          <ArrowUturnLeftIcon class="h-5 w-5 text-gray-400 dark:text-gray-500" />
        </div>

        <!-- Reply Form Container -->
        <div class="flex-1 min-w-0">
          <!-- Header: Recipient & Cc/Bcc Toggle -->
          <div class="flex items-center justify-between mb-2">
            <div class="flex items-center gap-2">
              <span class="text-sm font-medium text-gray-900 dark:text-white">
                {{ email.from_name || email.from_email }}
              </span>
              <button 
                v-if="email.from_email"
                class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300"
              >
                &lt;{{ email.from_email }}&gt;
              </button>
            </div>
            <button
              @click="showCcBcc = !showCcBcc"
              class="text-xs text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 font-medium"
            >
              Cc/Bcc
            </button>
          </div>

          <!-- Cc/Bcc Inputs -->
          <div v-if="showCcBcc" class="mb-4 space-y-2">
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-gray-500 dark:text-gray-400 w-8">Cc:</label>
              <input
                v-model="cc"
                type="text"
                class="flex-1 bg-transparent border-b border-gray-200 dark:border-gray-700 text-sm text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-0 px-0 py-1 placeholder-gray-400 dark:placeholder-gray-600"
                placeholder="Cc recipients"
              />
            </div>
            <div class="flex items-center gap-2">
              <label class="text-xs font-medium text-gray-500 dark:text-gray-400 w-8">Bcc:</label>
              <input
                v-model="bcc"
                type="text"
                class="flex-1 bg-transparent border-b border-gray-200 dark:border-gray-700 text-sm text-gray-900 dark:text-white focus:border-indigo-500 focus:ring-0 px-0 py-1 placeholder-gray-400 dark:placeholder-gray-600"
                placeholder="Bcc recipients"
              />
            </div>
          </div>

          <!-- Editor Area -->
          <div class="relative">
            <textarea
              v-model="replyBody"
              rows="4"
              class="w-full bg-transparent border-0 text-gray-900 dark:text-white placeholder-gray-400 dark:placeholder-gray-600 focus:ring-0 p-0 resize-none text-base"
              placeholder="Write, or press &quot;space&quot; for AI, &quot;/&quot; for commands..."
            ></textarea>
            
            <!-- AI/Command Hint (Visual only for now) -->
            <div class="absolute bottom-0 right-0 pointer-events-none">
              <span class="text-xs text-gray-400 dark:text-gray-600">...</span>
            </div>
          </div>

          <!-- Actions Toolbar (Hidden for now, but structure ready) -->
          <div class="flex items-center justify-between mt-4">
             <div class="flex items-center gap-2">
                <!-- Formatting tools could go here -->
             </div>
             <button
                @click="handleSend"
                :disabled="!replyBody.trim()"
                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
             >
               Send
             </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { ArrowUturnLeftIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  email: {
    type: Object,
    required: true
  }
})

const emit = defineEmits(['send'])

const showCcBcc = ref(false)
const cc = ref('')
const bcc = ref('')
const replyBody = ref('')

const handleSend = () => {
  if (!replyBody.value.trim()) return

  emit('send', {
    to: props.email.from_email,
    cc: cc.value,
    bcc: bcc.value,
    body: replyBody.value
  })
  
  // Reset form
  replyBody.value = ''
  showCcBcc.value = false
  cc.value = ''
  bcc.value = ''
}
</script>
