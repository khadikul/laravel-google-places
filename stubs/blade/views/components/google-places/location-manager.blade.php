@props([
    'accounts' => null,
    'locations' => null,
    'connected' => null,
    'connection' => null,
])

@php
    $accounts = $accounts ?? collect();
    $locations = $locations ?? collect();
    $connected = $connected ?? \Khadikul\GooglePlaces\Facades\GooglePlaces::connectedLocations();
@endphp

<div {{ $attributes->merge(['class' => 'space-y-6']) }}>
    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Google connection</h2>

        @if ($connection)
            <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div class="flex justify-between sm:block">
                    <dt class="text-gray-500 dark:text-gray-400">Account</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $connection->google_account_name ?? '—' }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-gray-500 dark:text-gray-400">Token expires</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $connection->expires_at?->diffForHumans() ?? '—' }}</dd>
                </div>
                <div class="flex justify-between sm:block">
                    <dt class="text-gray-500 dark:text-gray-400">Notifications</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">
                        {{ $connection->notifications_subscribed_at ? 'Subscribed' : 'Not subscribed' }}
                    </dd>
                </div>
            </dl>
        @else
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                No Google Business Profile connected yet.
            </p>
            <x-google-places.connect-button class="mt-4" />
        @endif
    </section>

    @if ($locations->isNotEmpty())
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Available locations</h2>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="pb-2 pr-3 font-medium">Location</th>
                            <th class="pb-2 pr-3 font-medium">Place ID</th>
                            <th class="pb-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($locations as $location)
                            <tr>
                                <td class="py-2.5 pr-3">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">{{ $location->title }}</span>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $location->resourceName() }}</div>
                                </td>
                                <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-400">{{ $location->placeId ?? '—' }}</td>
                                <td class="py-2.5">
                                    <form method="post" action="{{ route('google-places.locations.connect') }}">
                                        @csrf
                                        <input type="hidden" name="location" value="{{ $location->name }}">
                                        <input type="hidden" name="account" value="{{ $location->accountName }}">
                                        <button type="submit"
                                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700">
                                            Connect &amp; sync
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
            Synced locations ({{ $connected->count() }})
        </h2>

        @if ($connected->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Nothing connected yet.</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="pb-2 pr-3 font-medium">Location</th>
                            <th class="pb-2 pr-3 font-medium">Reviews</th>
                            <th class="pb-2 pr-3 font-medium">Last synced</th>
                            <th class="pb-2 font-medium"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($connected as $location)
                            <tr>
                                <td class="py-2.5 pr-3">
                                    <span class="font-medium text-gray-900 dark:text-gray-100">
                                        {{ $location->title ?? $location->location_name }}
                                    </span>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $location->place_id ?? 'no place ID' }}</div>
                                </td>
                                <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-400">
                                    {{ $location->total_review_count ?? '—' }}
                                    @if ($location->average_rating)
                                        <span class="text-xs">(avg {{ $location->average_rating }})</span>
                                    @endif
                                </td>
                                <td class="py-2.5 pr-3 text-gray-600 dark:text-gray-400">
                                    {{ $location->last_synced_at?->diffForHumans() ?? 'never' }}
                                </td>
                                <td class="py-2.5">
                                    <form method="post" action="{{ route('google-places.locations.sync') }}">
                                        @csrf
                                        <input type="hidden" name="location" value="{{ $location->location_name }}">
                                        <button type="submit"
                                                class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                                            Sync now
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
