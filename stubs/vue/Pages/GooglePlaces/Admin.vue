<script setup>
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
import { computed } from 'vue'
import { Head, usePage } from '@inertiajs/vue3'
import LocationManager from '@/Components/GooglePlaces/LocationManager.vue'

defineProps({
  connection: { type: [Object, null], default: null },
  locations: { type: Array, default: () => [] },
  connected: { type: Array, default: () => [] },
  connectUrl: { type: String, required: true },
  error: { type: [String, null], default: null },
})

const flash = computed(() => usePage().props.flash ?? {})
</script>

<template>
  <Head title="Google Business" />

  <div class="mx-auto max-w-4xl px-4 py-10">
    <h1 class="text-2xl font-bold text-gray-900 dark:text-gray-100">Google Business Profile</h1>

    <div
      v-if="flash.googleStatus === 'connected'"
      class="mt-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200"
    >
      Google account connected.
    </div>

    <div
      v-if="flash.googleError"
      class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200"
    >
      Could not connect: {{ flash.googleError }}
    </div>

    <div
      v-if="flash.status"
      class="mt-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-200"
    >
      {{ flash.status }}
    </div>

    <div
      v-if="error"
      class="mt-4 rounded-lg bg-amber-50 p-4 text-sm text-amber-800 dark:bg-amber-900/30 dark:text-amber-200"
    >
      {{ error }}
    </div>

    <LocationManager
      class="mt-6"
      :connection="connection"
      :locations="locations"
      :connected="connected"
      :connect-url="connectUrl"
      :connect-route="route('google-places.locations.connect')"
      :sync-route="route('google-places.locations.sync')"
    />
  </div>
</template>
