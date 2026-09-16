<div>
    <form wire:submit="search" class="flex flex-wrap items-center gap-2">
        <label for="gp-q" class="sr-only">Business name</label>

        <input id="gp-q" type="search" wire:model="query"
               placeholder="Search for a business, e.g. Torlyx Security"
               class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3.5 py-2.5 text-gray-900 shadow-sm
                      focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none
                      dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">

        <button type="submit"
                class="rounded-lg bg-blue-600 px-4 py-2.5 font-medium text-white transition hover:bg-blue-700
                       disabled:opacity-60"
                wire:loading.attr="disabled">
            <span wire:loading.remove wire:target="search">Search</span>
            <span wire:loading wire:target="search">Searching…</span>
        </button>
    </form>

    @error('query')
        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
    @enderror

    @if ($error)
        <div class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ $error }}
        </div>
    @endif

    @if (filled($query) && $results->isEmpty() && ! $error)
        <p class="mt-5 text-gray-500 dark:text-gray-400">
            Google matched no places for &ldquo;{{ $query }}&rdquo;.
        </p>
    @endif

    <div class="mt-5 grid gap-4 sm:grid-cols-2" wire:loading.class="opacity-50">
        @foreach ($results as $place)
            {{-- Reuses the shared Blade components, so markup stays in one place. --}}
            <x-google-places.place-card :place="$place">
                <button type="button"
                        wire:click="$dispatch('google-place-selected', { placeId: '{{ $place->id }}' })"
                        class="mt-3 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                    Select this business
                </button>
            </x-google-places.place-card>
        @endforeach
    </div>
</div>
