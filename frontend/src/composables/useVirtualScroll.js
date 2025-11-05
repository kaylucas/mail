import { ref, computed, onMounted, onUnmounted } from 'vue'

/**
 * Virtual scroll composable for efficient rendering of large lists
 * Only renders visible items plus a buffer
 *
 * @param {Object} options - Configuration options
 * @param {Ref<Array>} options.items - Array of items to render
 * @param {number} options.itemHeight - Height of each item in pixels
 * @param {number} options.buffer - Number of items to render outside viewport (default: 5)
 * @param {number} options.containerHeight - Height of scroll container (default: window height)
 * @returns {Object} Virtual scroll state and methods
 */
export function useVirtualScroll({ items, itemHeight = 80, buffer = 5, containerHeight = null }) {
  const scrollTop = ref(0)
  const containerRef = ref(null)
  const viewportHeight = ref(containerHeight || (typeof window !== 'undefined' ? window.innerHeight : 600))

  // Calculate visible range
  const visibleRange = computed(() => {
    const itemsCount = items.value?.length || 0

    if (itemsCount === 0) {
      return { start: 0, end: 0, visibleItems: [] }
    }

    const scrollPosition = scrollTop.value
    const viewportSize = viewportHeight.value

    // Calculate start and end indices with buffer
    let start = Math.floor(scrollPosition / itemHeight) - buffer
    let end = Math.ceil((scrollPosition + viewportSize) / itemHeight) + buffer

    // Clamp to valid range
    start = Math.max(0, start)
    end = Math.min(itemsCount, end)

    return {
      start,
      end,
      visibleItems: items.value.slice(start, end)
    }
  })

  // Total height of all items (for scroll container)
  const totalHeight = computed(() => {
    return (items.value?.length || 0) * itemHeight
  })

  // Offset for visible items (padding-top to position items correctly)
  const offsetY = computed(() => {
    return visibleRange.value.start * itemHeight
  })

  // Handle scroll events
  const handleScroll = (event) => {
    const target = event.target
    scrollTop.value = target.scrollTop
  }

  // Check if near bottom (for infinite scroll)
  const isNearBottom = computed(() => {
    const threshold = itemHeight * 10 // 10 items from bottom
    const scrollBottom = scrollTop.value + viewportHeight.value
    return scrollBottom >= totalHeight.value - threshold
  })

  // Scroll to top
  const scrollToTop = () => {
    if (containerRef.value) {
      containerRef.value.scrollTop = 0
      scrollTop.value = 0
    }
  }

  // Scroll to specific item
  const scrollToItem = (index) => {
    if (containerRef.value) {
      const position = index * itemHeight
      containerRef.value.scrollTop = position
      scrollTop.value = position
    }
  }

  // Update viewport height on window resize
  const updateViewportHeight = () => {
    if (!containerHeight && containerRef.value) {
      viewportHeight.value = containerRef.value.clientHeight
    }
  }

  onMounted(() => {
    if (typeof window !== 'undefined') {
      window.addEventListener('resize', updateViewportHeight)
      updateViewportHeight()
    }
  })

  onUnmounted(() => {
    if (typeof window !== 'undefined') {
      window.removeEventListener('resize', updateViewportHeight)
    }
  })

  return {
    // Refs
    containerRef,
    scrollTop,

    // Computed
    visibleRange,
    totalHeight,
    offsetY,
    isNearBottom,

    // Methods
    handleScroll,
    scrollToTop,
    scrollToItem,
    updateViewportHeight
  }
}
