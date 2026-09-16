<script>
  /**
   * Published by google-places:scaffold. Yours to edit.
   *
   * Reading the OAuth result needs the flash data shared in
   * HandleInertiaRequests::share():
   *
   *   'flash' => [
   *       'googleStatus' => fn () => $request->session()->get('google_places_status'),
   *       'googleError' => fn () => $request->session()->get('google_places_error'),
   *       'status' => fn () => $request->session()->get('status'),
   *   ],
   */
  import { page } from '@inertiajs/svelte'
  import LocationManager from '../../Components/GooglePlaces/LocationManager.svelte'

  export let connection = null
  export let locations = []
  export let connected = []
  export let connectUrl
  export let error = null

  $: flash = $page.props.flash ?? {}
</script>

<svelte:head>
  <title>Google Business</title>
</svelte:head>

<div class="mx-auto max-w-4xl px-4 py-10">
  <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Google Business Profile</h1>

  {#if flash.googleStatus === 'connected'}
    <div
      class="mt-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200"
    >
      Google account connected.
    </div>
  {/if}

  {#if flash.googleError}
    <div class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
      Could not connect: {flash.googleError}
    </div>
  {/if}

  {#if flash.status}
    <div class="mt-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">
      {flash.status}
    </div>
  {/if}

  {#if error}
    <div class="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200">
      {error}
    </div>
  {/if}

  <LocationManager
    {connection}
    {locations}
    {connected}
    {connectUrl}
    connectRoute={route('google-places.locations.connect')}
    syncRoute={route('google-places.locations.sync')}
  />
</div>
