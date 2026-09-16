<script setup>
/**
 * Published by google-places:scaffold. Yours to edit.
 */
import { computed } from 'vue'

const props = defineProps({
  rating: { type: [Number, null], default: null },
  count: { type: [Number, null], default: null },
  size: { type: String, default: 'sm' },
})

const SIZES = { sm: 'text-sm', md: 'text-base', lg: 'text-xl' }

// Google returns no rating for a place nobody has reviewed. Rendering zero
// stars would read as "rated 0", so say nothing instead.
const value = computed(() => (typeof props.rating === 'number' ? props.rating : null))
const filled = computed(() => (value.value === null ? 0 : Math.round(value.value)))
const sizeClass = computed(() => SIZES[props.size] ?? SIZES.sm)
</script>

<template>
  <span v-if="value === null" :class="[sizeClass, 'text-gray-500 dark:text-gray-400']">No rating yet</span>

  <div v-else :class="['flex items-center gap-1.5', sizeClass]">
    <span class="flex" aria-hidden="true">
      <svg
        v-for="star in 5"
        :key="star"
        :class="['size-[1.1em]', star <= filled ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600']"
        viewBox="0 0 20 20"
        fill="currentColor"
      >
        <path d="M10 15.27 16.18 19l-1.64-7.03L20 7.24l-7.19-.61L10 0 7.19 6.63 0 7.24l5.46 4.73L3.82 19z" />
      </svg>
    </span>

    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ value.toFixed(1) }}</span>

    <span v-if="count !== null && count !== undefined" class="text-gray-500 dark:text-gray-400">
      ({{ count.toLocaleString() }})
    </span>

    <span class="sr-only">{{ value.toFixed(1) }} out of 5</span>
  </div>
</template>
