<script>
  /**
   * Published by google-places:scaffold. Yours to edit.
   */
  import { inertia } from '@inertiajs/svelte'
  import Rating from '../../Components/GooglePlaces/Rating.svelte'
  import ReviewsWidget from '../../Components/GooglePlaces/ReviewsWidget.svelte'

  export let place = null
  export let photoUrl = null
  export let reviews = []
  export let error = null
</script>

<svelte:head>
  <title>{place?.name ?? 'Business'}</title>
</svelte:head>

<div class="mx-auto max-w-4xl px-4 py-10">
  {#if error || !place}
    <div class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
      {error ?? 'This place could not be loaded.'}
    </div>
  {:else}
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{place.name ?? 'Unnamed place'}</h1>
    <p class="mt-1 text-gray-600 dark:text-gray-400">{place.address}</p>

    <Rating rating={place.rating} count={place.review_count} size="lg" />

    {#if photoUrl}
      <figure class="mt-6">
        <img src={photoUrl} alt="" class="w-full rounded-xl object-cover" />
        <!-- Google requires the photo's author attribution to be displayed. -->
        <figcaption class="mt-2 text-xs text-gray-500 dark:text-gray-400">
          Photo by {(place.photo_attribution ?? []).join(', ') || 'a Google user'}
        </figcaption>
      </figure>
    {/if}

    <ReviewsWidget
      title="Reviews"
      {reviews}
      rating={place.rating}
      reviewCount={place.review_count}
      mapsUrl={place.google_maps_uri}
    />

    <a
      use:inertia
      href={route('google-places.page.search')}
      class="text-blue-600 hover:underline dark:text-blue-400"
    >
      ← Back to search
    </a>
  {/if}
</div>
