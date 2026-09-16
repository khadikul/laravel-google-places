<script setup>
/**
 * Published by google-places:scaffold. Yours to edit.
 *
 * Wrap this in your starter kit's layout once you have looked it over.
 */
import { ref } from 'vue'
import { Head, router } from '@inertiajs/vue3'
import PlaceCard from '@/Components/GooglePlaces/PlaceCard.vue'

const props = defineProps({
  query: { type: [String, null], default: '' },
  results: { type: Array, default: () => [] },
  error: { type: [String, null], default: null },
})

const term = ref(props.query ?? '')

// A GET so the search is shareable and back-button friendly.
const submit = () =>
  router.get(route('google-places.page.search'), { q: term.value }, { preserveState: true })
</script>

<template>
  <Head title="Find a business" />

  <div class="mx-auto max-w-4xl px-4 py-10">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Find a business on Google</h1>

    <form class="mt-5 flex flex-wrap items-center gap-2" @submit.prevent="submit">
      <label for="gp-q" class="sr-only">Business name</label>
      <input
        id="gp-q"
        v-model="term"
        type="search"
        placeholder="Search for a business, e.g. Torlyx Security"
        class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3.5 py-2.5 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
      />
      <button
        type="submit"
        class="rounded-lg bg-blue-600 px-4 py-2.5 font-medium text-white transition hover:bg-blue-700"
      >
        Search
      </button>
    </form>

    <div
      v-if="error"
      class="mt-5 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200"
    >
      {{ error }}
    </div>

    <p v-if="query && results.length === 0 && !error" class="mt-6 text-gray-500 dark:text-gray-400">
      Google matched no places for “{{ query }}”.
    </p>

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
      <PlaceCard
        v-for="place in results"
        :key="place.id"
        :place="place"
        :href="route('google-places.page.show', place.id)"
      />
    </div>
  </div>
</template>
