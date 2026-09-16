<script>
  /**
   * Published by google-places:scaffold. Yours to edit.
   *
   * Wrap this in your starter kit's layout once you have looked it over.
   */
  import { router } from '@inertiajs/svelte'
  import PlaceCard from '../../Components/GooglePlaces/PlaceCard.svelte'

  export let query = ''
  export let results = []
  export let error = null

  let term = query ?? ''

  // A GET so the search is shareable and back-button friendly.
  const submit = () =>
    router.get(route('google-places.page.search'), { q: term }, { preserveState: true })
</script>

<svelte:head>
  <title>Find a business</title>
</svelte:head>

<div class="mx-auto max-w-4xl px-4 py-10">
  <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Find a business on Google</h1>

  <form class="mt-5 flex flex-wrap items-center gap-2" on:submit|preventDefault={submit}>
    <label for="gp-q" class="sr-only">Business name</label>
    <input
      id="gp-q"
      type="search"
      bind:value={term}
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

  {#if error}
    <div class="mt-5 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
      {error}
    </div>
  {/if}

  {#if query && results.length === 0 && !error}
    <p class="mt-6 text-gray-500 dark:text-gray-400">Google matched no places for “{query}”.</p>
  {/if}

  <div class="mt-6 grid gap-4 sm:grid-cols-2">
    {#each results as place (place.id)}
      <PlaceCard {place} href={route('google-places.page.show', place.id)} />
    {/each}
  </div>
</div>
