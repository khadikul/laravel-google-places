<script setup>
/**
 * Published by google-places:scaffold. Yours to edit.
 *
 * Expects the snake_case shape that Review::toArray() produces.
 */
import { computed } from 'vue'
import Rating from './Rating.vue'

const props = defineProps({
  review: { type: Object, required: true },
})

const author = computed(() => props.review.author_name)
const isOwnerFeed = computed(() => props.review.source === 'business_profile')
const initial = computed(() => (author.value ?? 'G').charAt(0).toUpperCase())
const published = computed(
  () =>
    props.review.relative_publish_time ??
    (props.review.publish_time ? new Date(props.review.publish_time).toLocaleDateString() : ''),
)
</script>

<template>
  <article class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
    <div class="flex items-start gap-3">
      <!-- Google's terms require the reviewer's photo and name to be shown as given. -->
      <img
        v-if="review.author_photo_uri"
        :src="review.author_photo_uri"
        alt=""
        referrerpolicy="no-referrer"
        class="size-10 shrink-0 rounded-full object-cover"
      />
      <div
        v-else
        class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400"
      >
        {{ initial }}
      </div>

      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
          <a
            v-if="review.author_uri"
            :href="review.author_uri"
            target="_blank"
            rel="noopener noreferrer"
            class="font-semibold text-gray-900 hover:underline dark:text-gray-100"
          >
            {{ author ?? 'A Google user' }}
          </a>
          <!-- Anonymous reviewers have no name; never invent one. -->
          <span v-else class="font-semibold text-gray-900 dark:text-gray-100">
            {{ author ?? 'A Google user' }}
          </span>

          <span
            v-if="isOwnerFeed"
            class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300"
          >
            Owner feed
          </span>
        </div>

        <Rating :rating="review.rating" class="mt-1" />

        <!-- Review text must be shown unmodified, or not at all. -->
        <p v-if="review.text" class="mt-3 whitespace-pre-line text-gray-700 dark:text-gray-300">
          {{ review.text }}
        </p>
        <p v-else class="mt-3 text-sm italic text-gray-500 dark:text-gray-400">
          Rating only, no written review.
        </p>

        <div v-if="review.reply_text" class="mt-3 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800">
          <span class="font-semibold text-gray-900 dark:text-gray-100">Response from the owner</span>
          <p class="mt-1 whitespace-pre-line text-gray-700 dark:text-gray-300">{{ review.reply_text }}</p>
        </div>

        <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ published }}</p>
      </div>
    </div>
  </article>
</template>
