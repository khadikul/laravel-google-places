<div class="space-y-6">
    @if ($status)
        <div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
            {{ $status }}
        </div>
    @endif

    @if ($error)
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ $error }}
        </div>
    @endif

    <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Google connection</h2>

        @if ($connection)
            <dl class="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Account</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $connection->google_account_name ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500 dark:text-gray-400">Token expires</dt>
                    <dd class="font-medium text-gray-900 dark:text-gray-100">{{ $connection->expires_at?->diffForHumans() ?? '—' }}</dd>
                </div>
            </dl>
        @else
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">No Google Business Profile connected yet.</p>

            {{-- Plain anchor, no wire:navigate: this leaves the site for Google. --}}
            <x-google-places.connect-button class="mt-4" />
        @endif
    </section>

    @if ($locations->isNotEmpty())
        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-gray-100">Available locations</h2>

            <div class="mt-3 space-y-2">
                @foreach ($locations as $location)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
                         wire:key="loc-{{ $location->id() }}">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">{{ $location->title }}</div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">{{ $location->placeId ?? 'no place ID yet' }}</div>
                        </div>

                        <button type="button"
                                wire:click="connect('{{ $location->name }}', '{{ $location->accountName }}')"
                                wire:loading.attr="disabled"
                                class="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-60">
                            Connect &amp; sync
                        </button>
                    </div>
                @endforeach
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
            <div class="mt-3 space-y-2">
                @foreach ($connected as $location)
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
                         wire:key="synced-{{ $location->id }}">
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {{ $location->title ?? $location->location_name }}
                            </div>
                            <div class="text-xs text-gray-500 dark:text-gray-400">
                                {{ $location->total_review_count ?? 0 }} reviews ·
                                synced {{ $location->last_synced_at?->diffForHumans() ?? 'never' }}
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <button type="button" wire:click="sync('{{ $location->location_name }}')"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
                                Sync now
                            </button>
                            <button type="button" wire:click="disconnect('{{ $location->location_name }}')"
                                    class="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-500 hover:bg-gray-50 dark:border-gray-600 dark:hover:bg-gray-800">
                                Stop
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </section>
</div>
