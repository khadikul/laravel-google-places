<script>
  /**
   * Published by google-places:scaffold. Yours to edit.
   *
   * The public-facing block. Pass reviews the controller already loaded from
   * your own database: no API call, no billing, no latency.
   */
  import Rating from './Rating.svelte'
  import ReviewCard from './ReviewCard.svelte'

  export let reviews = []
  export let rating = null
  export let reviewCount = null
  export let mapsUrl = null
  export let title = 'What our customers say'
</script>

<section class="py-10">
  <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
      <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{title}</h2>
      {#if rating !== null}
        <Rating {rating} count={reviewCount} size="md" />
      {/if}
    </div>

    <!-- Google requires a link back to the source of the data. -->
    {#if mapsUrl}
      <a
        href={mapsUrl}
        target="_blank"
        rel="noopener noreferrer"
        class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
      >
        Read them on Google
        <svg class="size-4" viewBox="0 0 20 20" fill="currentColor">
          <path d="M11 3a1 1 0 100 2h2.59l-6.3 6.29a1 1 0 101.42 1.42L15 6.41V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z" />
          <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z" />
        </svg>
      </a>
    {/if}
  </div>

  {#if reviews.length === 0}
    <p class="text-gray-500 dark:text-gray-400">No reviews to show yet.</p>
  {:else}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
      {#each reviews as review (review.review_name)}
        <ReviewCard {review} />
      {/each}
    </div>
  {/if}

  <!-- Attribution is required wherever Google data is displayed. -->
  <p class="mt-6 text-xs text-gray-400 dark:text-gray-500">Reviews powered by Google</p>
</section>
