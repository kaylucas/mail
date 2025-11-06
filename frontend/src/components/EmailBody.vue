<template>
  <div class="email-body-container">
    <!-- HTML Content -->
    <div
      v-if="contentType === 'html' && sanitizedHtml"
      class="email-body-html prose prose-lg max-w-none"
      v-html="sanitizedHtml"
    ></div>

    <!-- Plain Text Content -->
    <div
      v-else-if="contentType === 'text' && htmlContent"
      class="email-body-text whitespace-pre-wrap text-gray-800 bg-gray-50 rounded-lg p-6 border border-gray-200"
    >{{ htmlContent }}</div>

    <!-- No Content -->
    <div v-else class="text-center py-16">
      <svg class="h-16 w-16 text-gray-300 mx-auto mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
      </svg>
      <p class="text-gray-500 text-sm font-medium">No content available</p>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import DOMPurify from 'dompurify'

/**
 * Component props
 * @typedef {Object} Props
 * @property {string} htmlContent - Email content (HTML or plain text)
 * @property {string} contentType - Content type ('html' or 'text')
 */
const props = defineProps({
  htmlContent: {
    type: String,
    default: ''
  },
  contentType: {
    type: String,
    default: 'html',
    validator: (value) => ['html', 'text'].includes(value)
  }
})

/**
 * Sanitized HTML content
 * Uses DOMPurify to prevent XSS attacks
 */
const sanitizedHtml = computed(() => {
  if (!props.htmlContent || props.contentType !== 'html') {
    return ''
  }

  // Configure DOMPurify with secure defaults
  const config = {
    // Allowed tags - comprehensive list for email content
    ALLOWED_TAGS: [
      'a', 'b', 'i', 'u', 'strong', 'em', 'span', 'div', 'p', 'br',
      'ul', 'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
      'table', 'thead', 'tbody', 'tr', 'td', 'th',
      'img', 'blockquote', 'pre', 'code',
      'hr', 'sub', 'sup', 'small', 'mark', 'del', 'ins'
    ],
    // Allowed attributes - CRITICAL: Removed 'style' for security
    ALLOWED_ATTR: [
      'href', 'src', 'alt', 'title', 'class',
      'width', 'height', 'colspan', 'rowspan',
      'target', 'rel'
    ],
    // Block data attributes
    ALLOW_DATA_ATTR: false,
    // Only allow safe URI schemes - CRITICAL: Prevents javascript: and data: URIs
    ALLOWED_URI_REGEXP: /^(?:(?:(?:f|ht)tps?|mailto|tel|cid):|[^a-z]|[a-z+.\-]+(?:[^a-z+.\-:]|$))/i,
    // Force external links to open in new tab
    ADD_ATTR: ['target', 'rel'],
    // Disable unknown protocols
    ALLOW_UNKNOWN_PROTOCOLS: false,
    // Return string (not DOM)
    RETURN_DOM_FRAGMENT: false,
    RETURN_DOM: false,
    FORCE_BODY: false,
    // Security hooks
    HOOKS: {
      // CRITICAL: Validate and sanitize URIs before processing
      uponSanitizeElement: (node, data) => {
        // Block malicious data URIs (only allow safe image data URIs)
        if (node.hasAttribute && (node.hasAttribute('src') || node.hasAttribute('href'))) {
          const srcAttr = node.getAttribute('src')
          const hrefAttr = node.getAttribute('href')
          const attr = srcAttr || hrefAttr
          
          if (attr && attr.toLowerCase().startsWith('data:')) {
            // Only allow safe data URIs (images only, base64 encoded)
            if (!attr.match(/^data:image\/(png|jpg|jpeg|gif|webp|svg\+xml);base64,/i)) {
              if (srcAttr) node.removeAttribute('src')
              if (hrefAttr) node.removeAttribute('href')
            }
          }
        }
      },
      afterSanitizeAttributes: (node) => {
        // Force external links to open in new tab with security attributes
        if (node.tagName === 'A' && node.hasAttribute('href')) {
          node.setAttribute('target', '_blank')
          node.setAttribute('rel', 'noopener noreferrer')
        }

        // Limit image dimensions for safety (using inline style since we blocked style attr)
        if (node.tagName === 'IMG') {
          // Use CSS classes instead of inline styles where possible
          node.style.maxWidth = '100%'
          node.style.height = 'auto'
        }
      }
    }
  }

  return DOMPurify.sanitize(props.htmlContent, config)
})
</script>

<style scoped>
/* Email body container styling */
.email-body-container {
  min-height: 200px;
}

/* HTML email styling */
.email-body-html {
  color: #1f2937;
  line-height: 1.75;
  font-size: 1rem;
}

/* Plain text email styling */
.email-body-text {
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  font-size: 0.875rem;
  line-height: 1.5rem;
}

/* Prose styles for HTML content */
.email-body-html :deep(p) {
  margin-top: 1rem;
  margin-bottom: 1rem;
}

.email-body-html :deep(p:first-child) {
  margin-top: 0;
}

.email-body-html :deep(p:last-child) {
  margin-bottom: 0;
}

.email-body-html :deep(a) {
  color: #4f46e5;
  text-decoration: underline;
  font-weight: 500;
  transition: color 150ms ease-in-out;
}

.email-body-html :deep(a:hover) {
  color: #3730a3;
  text-decoration-thickness: 2px;
}

.email-body-html :deep(strong),
.email-body-html :deep(b) {
  font-weight: 600;
}

.email-body-html :deep(em),
.email-body-html :deep(i) {
  font-style: italic;
}

.email-body-html :deep(h1) {
  font-size: 1.5rem;
  line-height: 2rem;
  font-weight: 700;
  margin-bottom: 1rem;
  margin-top: 1.5rem;
}

.email-body-html :deep(h2) {
  font-size: 1.25rem;
  line-height: 1.75rem;
  font-weight: 700;
  margin-bottom: 0.75rem;
  margin-top: 1.25rem;
}

.email-body-html :deep(h3) {
  font-size: 1.125rem;
  line-height: 1.75rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  margin-top: 1rem;
}

.email-body-html :deep(h4),
.email-body-html :deep(h5),
.email-body-html :deep(h6) {
  font-size: 1rem;
  line-height: 1.5rem;
  font-weight: 700;
  margin-bottom: 0.5rem;
  margin-top: 0.75rem;
}

.email-body-html :deep(ul) {
  list-style-type: disc;
  list-style-position: inside;
  margin-top: 1rem;
  margin-bottom: 1rem;
}

.email-body-html :deep(ul > li) {
  margin-top: 0.25rem;
}

.email-body-html :deep(ol) {
  list-style-type: decimal;
  list-style-position: inside;
  margin-top: 1rem;
  margin-bottom: 1rem;
}

.email-body-html :deep(ol > li) {
  margin-top: 0.25rem;
}

.email-body-html :deep(li) {
  margin-left: 1rem;
}

.email-body-html :deep(blockquote) {
  border-left: 4px solid #d1d5db;
  padding-left: 1rem;
  margin-top: 1rem;
  margin-bottom: 1rem;
  font-style: italic;
  color: #374151;
}

.email-body-html :deep(pre) {
  background-color: #1f2937;
  color: #f9fafb;
  border-radius: 0.5rem;
  padding: 1.25rem;
  margin-top: 1.5rem;
  margin-bottom: 1.5rem;
  overflow-x: auto;
  border: 1px solid #374151;
  box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1);
}

.email-body-html :deep(code) {
  background-color: #f3f4f6;
  border-radius: 0.25rem;
  padding: 0.25rem 0.5rem;
  font-size: 0.875rem;
  font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
  color: #db2777;
  font-weight: 500;
}

.email-body-html :deep(pre code) {
  background-color: transparent;
  padding: 0;
}

.email-body-html :deep(table) {
  width: 100%;
  margin-top: 1rem;
  margin-bottom: 1rem;
  border-collapse: collapse;
}

.email-body-html :deep(th) {
  background-color: #f3f4f6;
  border: 1px solid #d1d5db;
  padding: 0.5rem 1rem;
  text-align: left;
  font-weight: 600;
}

.email-body-html :deep(td) {
  border: 1px solid #d1d5db;
  padding: 0.5rem 1rem;
}

.email-body-html :deep(img) {
  max-width: 100%;
  height: auto;
  margin-top: 1.5rem;
  margin-bottom: 1.5rem;
  border-radius: 0.5rem;
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
}

.email-body-html :deep(hr) {
  margin-top: 1.5rem;
  margin-bottom: 1.5rem;
  border-color: #d1d5db;
}

/* Prevent email styles from breaking out of container */
.email-body-html :deep(*) {
  max-width: 100%;
}
</style>
