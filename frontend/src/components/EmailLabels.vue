<template>
  <div v-if="labels && labels.length > 0" class="flex flex-wrap gap-1.5">
    <span
      v-for="label in labels"
      :key="label.id"
      :class="['inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium', getLabelColorClass(label.name)]"
      :title="label.name"
    >
      {{ label.name }}
    </span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  labels: {
    type: Array,
    default: () => []
  }
})

/**
 * Generate consistent color class based on label name
 * Uses a hash function to ensure the same label always gets the same color
 */
const getLabelColorClass = (labelName) => {
  if (!labelName) return 'bg-gray-100 text-gray-700'

  // Color palette for labels
  const colorClasses = [
    'bg-blue-100 text-blue-700',
    'bg-green-100 text-green-700',
    'bg-purple-100 text-purple-700',
    'bg-pink-100 text-pink-700',
    'bg-yellow-100 text-yellow-700',
    'bg-red-100 text-red-700',
    'bg-indigo-100 text-indigo-700',
    'bg-teal-100 text-teal-700',
    'bg-orange-100 text-orange-700',
    'bg-cyan-100 text-cyan-700'
  ]

  // Simple hash function for consistent color assignment
  const hash = labelName.split('').reduce((acc, char) => {
    return acc + char.charCodeAt(0)
  }, 0)

  return colorClasses[hash % colorClasses.length]
}
</script>
