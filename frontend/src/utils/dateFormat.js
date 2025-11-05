/**
 * Date formatting utilities for email timestamps
 * Provides relative time formatting (e.g., "2 minutes ago", "Yesterday")
 */

/**
 * Format a date string to relative time
 * @param {string} dateString - ISO 8601 date string
 * @returns {string} Formatted relative time
 */
export function formatRelativeTime(dateString) {
  if (!dateString) return ''

  const date = new Date(dateString)
  const now = new Date()
  const diffMs = now - date
  const diffSec = Math.floor(diffMs / 1000)
  const diffMin = Math.floor(diffSec / 60)
  const diffHours = Math.floor(diffMin / 60)
  const diffDays = Math.floor(diffHours / 24)

  // Less than 1 minute
  if (diffSec < 60) {
    return 'Just now'
  }

  // Less than 1 hour
  if (diffMin < 60) {
    return diffMin === 1 ? '1 minute ago' : `${diffMin} minutes ago`
  }

  // Less than 24 hours
  if (diffHours < 24) {
    return diffHours === 1 ? '1 hour ago' : `${diffHours} hours ago`
  }

  // Yesterday
  if (diffDays === 1) {
    return 'Yesterday'
  }

  // Less than 7 days
  if (diffDays < 7) {
    return `${diffDays} days ago`
  }

  // This year
  const currentYear = now.getFullYear()
  const dateYear = date.getFullYear()

  if (currentYear === dateYear) {
    return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }

  // Previous years
  return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })
}

/**
 * Format full date time for tooltips
 * @param {string} dateString - ISO 8601 date string
 * @returns {string} Formatted full date time
 */
export function formatFullDateTime(dateString) {
  if (!dateString) return ''

  const date = new Date(dateString)
  return date.toLocaleString('en-US', {
    weekday: 'long',
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  })
}

/**
 * Get initials from name
 * @param {string} name - Full name
 * @returns {string} Initials (max 2 characters)
 */
export function getInitials(name) {
  if (!name) return '?'

  const parts = name.trim().split(' ').filter(Boolean)

  if (parts.length === 0) return '?'
  if (parts.length === 1) return parts[0].charAt(0).toUpperCase()

  return (parts[0].charAt(0) + parts[parts.length - 1].charAt(0)).toUpperCase()
}

/**
 * Format file size in human-readable format
 * @param {number} bytes - File size in bytes
 * @returns {string} Formatted file size
 */
export function formatFileSize(bytes) {
  if (!bytes || bytes === 0) return '0 B'

  const k = 1024
  const sizes = ['B', 'KB', 'MB', 'GB']
  const i = Math.floor(Math.log(bytes) / Math.log(k))

  return `${parseFloat((bytes / Math.pow(k, i)).toFixed(1))} ${sizes[i]}`
}

/**
 * Truncate text to specified length
 * @param {string} text - Text to truncate
 * @param {number} maxLength - Maximum length
 * @returns {string} Truncated text with ellipsis
 */
export function truncateText(text, maxLength = 100) {
  if (!text) return ''
  if (text.length <= maxLength) return text

  return text.substring(0, maxLength).trim() + '...'
}
