<script setup>
/**
 * Published by google-places:scaffold. Yours to edit.
 *
 * Every field except the ID can be null: the Places API returns only what the
 * field mask asked for.
 */
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Rating from './Rating.vue'

const props = defineProps({
  place: { type: Object, required: true },
  href: { type: [String, null], default: null },
})

const name = computed(() => props.place.name ?? 'Unnamed place')
const closed = computed(
  () => props.place.business_status && props.place.business_status !== 'OPERATIONAL',
)
</script>

<template>
  <article
    class="rounded-xl border border-gray-200 bg-white p-5 transition hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600"
  >
    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
      <Link v-if="href" :href="href" class="hover:underline">{{ name }}</Link>
      <template v-else>{{ name }}</template>
    </h3>

    <p v-if="place.address" class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ place.address }}</p>

    <Rating :rating="place.rating" :count="place.review_count" class="mt-2.5" />

    <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
      <span
        v-if="place.primary_type"
        class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600 dark:bg-gray-800 dark:text-gray-300"
      >
        {{ place.primary_type }}
      </span>

      <span
        v-if="closed"
        class="rounded-full bg-red-50 px-2 py-0.5 text-red-700 dark:bg-red-900/40 dark:text-red-300"
      >
        {{ place.business_status.replaceAll('_', ' ').toLowerCase() }}
      </span>
    </div>

    <slot />
  </article>
</template>
