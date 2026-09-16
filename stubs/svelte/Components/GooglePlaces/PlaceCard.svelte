<script>
  /**
   * Published by google-places:scaffold. Yours to edit.
   *
   * Every field except the ID can be null: the Places API returns only what the
   * field mask asked for.
   */
  import { inertia } from '@inertiajs/svelte'
  import Rating from './Rating.svelte'

  export let place
  export let href = null

  $: name = place.name ?? 'Unnamed place'
  $: closed = place.business_status && place.business_status !== 'OPERATIONAL'
</script>

<article
  class="rounded-xl border border-gray-200 bg-white p-5 transition hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600"
>
  <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
    {#if href}
      <a use:inertia {href} class="hover:underline">{name}</a>
    {:else}
      {name}
    {/if}
  </h3>

  {#if place.address}
    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{place.address}</p>
  {/if}

  <Rating rating={place.rating} count={place.review_count} />

  <div class="mt-3 flex flex-wrap items-center gap-2 text-xs">
    {#if place.primary_type}
      <span class="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
        {place.primary_type}
      </span>
    {/if}

    {#if closed}
      <span class="rounded-full bg-red-50 px-2 py-0.5 text-red-700 dark:bg-red-900/40 dark:text-red-300">
        {place.business_status.replaceAll('_', ' ').toLowerCase()}
      </span>
    {/if}
  </div>

  <slot />
</article>
