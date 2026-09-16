<script setup>
/**
 * Published by google-places:scaffold. Yours to edit.
 */
import { Head, Link } from '@inertiajs/vue3'
import Rating from '@/Components/GooglePlaces/Rating.vue'
import ReviewsWidget from '@/Components/GooglePlaces/ReviewsWidget.vue'

defineProps({
  place: { type: [Object, null], default: null },
  photoUrl: { type: [String, null], default: null },
  reviews: { type: Array, default: () => [] },
  error: { type: [String, null], default: null },
})
</script>

<template>
  <Head :title="place?.name ?? 'Business'" />

  <div class="mx-auto max-w-4xl px-4 py-10">
    <div
      v-if="error || !place"
      class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200"
    >
      {{ error ?? 'This place could not be loaded.' }}
    </div>

    <template v-else>
      <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ place.name ?? 'Unnamed place' }}</h1>
      <p class="mt-1 text-gray-600 dark:text-gray-400">{{ place.address }}</p>

      <Rating :rating="place.rating" :count="place.review_count" size="lg" class="mt-4" />

      <figure v-if="photoUrl" class="mt-6">
        <img :src="photoUrl" alt="" class="w-full rounded-xl object-cover" />
        <!-- Google requires the photo's author attribution to be displayed. -->
        <figcaption class="mt-2 text-xs text-gray-500 dark:text-gray-400">
          Photo by {{ (place.photo_attribution ?? []).join(', ') || 'a Google user' }}
        </figcaption>
      </figure>

      <ReviewsWidget
        title="Reviews"
        :reviews="reviews"
        :rating="place.rating"
        :review-count="place.review_count"
        :maps-url="place.google_maps_uri"
      />

      <Link
        :href="route('google-places.page.search')"
        class="text-blue-600 hover:underline dark:text-blue-400"
      >
        ← Back to search
      </Link>
    </template>
  </div>
</template>
