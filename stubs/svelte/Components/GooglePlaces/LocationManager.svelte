<script>
  /**
   * Published by google-places:scaffold. Yours to edit.
   *
   * Render it from a page that sits behind your own auth middleware.
   */
  import { router } from '@inertiajs/svelte'
  import ConnectGoogleButton from './ConnectGoogleButton.svelte'

  export let connection = null
  export let locations = []
  export let connected = []
  export let connectUrl
  export let connectRoute
  export let syncRoute

  const connect = (location) =>
    router.post(
      connectRoute,
      { location: location.name, account: location.account_name },
      { preserveScroll: true },
    )

  const sync = (locationName) => router.post(syncRoute, { location: locationName }, { preserveScroll: true })
</script>

<div class="space-y-6">
  <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Google connection</h2>

    {#if connection}
      <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
        <div>
          <dt class="text-gray-500 dark:text-gray-400">Account</dt>
          <dd class="font-medium text-gray-900 dark:text-gray-100">
            {connection.google_account_name ?? '—'}
          </dd>
        </div>
        <div>
          <dt class="text-gray-500 dark:text-gray-400">Notifications</dt>
          <dd class="font-medium text-gray-900 dark:text-gray-100">
            {connection.notifications_subscribed_at ? 'Subscribed' : 'Not subscribed'}
          </dd>
        </div>
      </dl>
    {:else}
      <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No Google Business Profile connected yet.</p>
      <ConnectGoogleButton href={connectUrl} />
    {/if}
  </section>

  {#if locations.length}
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
      <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Available locations</h2>

      <div class="mt-3 space-y-2">
        {#each locations as location (location.resource_name ?? location.name)}
          <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
          >
            <div>
              <div class="font-medium text-gray-900 dark:text-gray-100">{location.title}</div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {location.place_id ?? 'no place ID yet'}
              </div>
            </div>

            <button
              type="button"
              class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700"
              on:click={() => connect(location)}
            >
              Connect &amp; sync
            </button>
          </div>
        {/each}
      </div>
    </section>
  {/if}

  <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
    <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
      Synced locations ({connected.length})
    </h2>

    {#if connected.length === 0}
      <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nothing connected yet.</p>
    {:else}
      <div class="mt-3 space-y-2">
        {#each connected as location (location.location_name)}
          <div
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
          >
            <div>
              <div class="font-medium text-gray-900 dark:text-gray-100">
                {location.title ?? location.location_name}
              </div>
              <div class="text-xs text-gray-500 dark:text-gray-400">
                {location.total_review_count ?? 0} reviews
              </div>
            </div>

            <button
              type="button"
              class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
              on:click={() => sync(location.location_name)}
            >
              Sync now
            </button>
          </div>
        {/each}
      </div>
    {/if}
  </section>
</div>
