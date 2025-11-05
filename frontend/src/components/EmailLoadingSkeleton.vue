<template>
  <div class="animate-pulse space-y-3">
    <div v-for="i in count" :key="i" class="flex items-start gap-4 p-4 bg-white border-b border-gray-200">
      <!-- Avatar skeleton -->
      <div class="flex-shrink-0 w-10 h-10 bg-gray-200 rounded-full"></div>

      <!-- Content skeleton -->
      <div class="flex-1 min-w-0 space-y-3">
        <!-- Header row -->
        <div class="flex items-center justify-between gap-4">
          <div class="h-4 bg-gray-200 rounded w-1/4"></div>
          <div class="h-3 bg-gray-200 rounded w-20"></div>
        </div>

        <!-- Subject line -->
        <div class="h-4 bg-gray-300 rounded w-3/4"></div>

        <!-- Body preview -->
        <div class="space-y-2">
          <div class="h-3 bg-gray-200 rounded w-full"></div>
          <div class="h-3 bg-gray-200 rounded w-5/6"></div>
        </div>
      </div>

      <!-- Checkbox skeleton -->
      <div class="flex-shrink-0 w-5 h-5 bg-gray-200 rounded"></div>
    </div>
  </div>
</template>

<script setup>
defineProps({
  count: {
    type: Number,
    default: 5
  }
})
</script>
