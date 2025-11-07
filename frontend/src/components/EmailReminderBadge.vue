<template>
  <div v-if="hasActiveReminder" :class="['inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-medium', reminderColorClass]" :title="reminderTooltip">
    <BellIcon v-if="reminderType === 'overdue' || reminderType === 'soon'" class="h-3.5 w-3.5" />
    <ClockIcon v-else class="h-3.5 w-3.5" />
    <span>{{ reminderLabel }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { BellIcon, ClockIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  reminders: {
    type: Array,
    default: () => []
  }
})

/**
 * Check if email has any active reminders (pending or triggered)
 */
const hasActiveReminder = computed(() => {
  if (!props.reminders || props.reminders.length === 0) return false

  return props.reminders.some(reminder =>
    reminder.status === 'pending' || reminder.status === 'triggered'
  )
})

/**
 * Get the most urgent active reminder
 */
const activeReminder = computed(() => {
  if (!hasActiveReminder.value) return null

  // Filter to active reminders
  const active = props.reminders.filter(r =>
    r.status === 'pending' || r.status === 'triggered'
  )

  if (active.length === 0) return null

  // Sort by remind_at date (earliest first)
  return active.sort((a, b) => {
    const dateA = new Date(a.remind_at)
    const dateB = new Date(b.remind_at)
    return dateA - dateB
  })[0]
})

/**
 * Determine reminder urgency type
 */
const reminderType = computed(() => {
  if (!activeReminder.value) return null

  const now = new Date()
  const remindAt = new Date(activeReminder.value.remind_at)
  const diffMs = remindAt - now
  const diffHours = diffMs / (1000 * 60 * 60)

  if (diffMs < 0) {
    return 'overdue' // Past due
  } else if (diffHours <= 24) {
    return 'soon' // Due within 24 hours
  } else {
    return 'future' // Due later
  }
})

/**
 * Get color class based on urgency
 */
const reminderColorClass = computed(() => {
  switch (reminderType.value) {
    case 'overdue':
      return 'bg-red-100 text-red-700 border border-red-200'
    case 'soon':
      return 'bg-yellow-100 text-yellow-700 border border-yellow-200'
    case 'future':
      return 'bg-blue-100 text-blue-700 border border-blue-200'
    default:
      return 'bg-gray-100 text-gray-700 border border-gray-200'
  }
})

/**
 * Get human-readable label for reminder
 */
const reminderLabel = computed(() => {
  if (!activeReminder.value) return ''

  const now = new Date()
  const remindAt = new Date(activeReminder.value.remind_at)
  const diffMs = remindAt - now
  const diffMinutes = Math.floor(diffMs / (1000 * 60))
  const diffHours = Math.floor(diffMinutes / 60)
  const diffDays = Math.floor(diffHours / 24)

  if (diffMs < 0) {
    // Overdue
    const overdueDays = Math.abs(diffDays)
    const overdueHours = Math.abs(diffHours)

    if (overdueDays > 0) {
      return `${overdueDays}d overdue`
    } else if (overdueHours > 0) {
      return `${overdueHours}h overdue`
    } else {
      return 'Overdue'
    }
  } else if (diffHours < 1) {
    return 'Due soon'
  } else if (diffHours < 24) {
    return `Due in ${diffHours}h`
  } else {
    return `Due in ${diffDays}d`
  }
})

/**
 * Get tooltip text with full reminder details
 */
const reminderTooltip = computed(() => {
  if (!activeReminder.value) return ''

  const remindAt = new Date(activeReminder.value.remind_at)
  const dateStr = remindAt.toLocaleDateString()
  const timeStr = remindAt.toLocaleTimeString()

  let tooltip = `Reminder: ${activeReminder.value.message || 'Follow up on this email'}\n`
  tooltip += `Due: ${dateStr} at ${timeStr}`

  if (props.reminders.length > 1) {
    tooltip += `\n(+${props.reminders.length - 1} more)`
  }

  return tooltip
})
</script>
