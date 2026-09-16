<div>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">Reviews</h2>

            @if ($place?->rating)
                <x-google-places.rating :rating="$place->rating" :count="$place->review_count" class="mt-1.5" />
            @endif
        </div>

        <div class="flex items-center gap-1.5">
            <button type="button" wire:click="filterBy(null)"
                    class="rounded-lg px-2.5 py-1.5 text-xs font-medium {{ $minRating === null ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300' }}">
                All
            </button>

            @foreach ([5, 4, 3] as $stars)
                <button type="button" wire:click="filterBy({{ $stars }})"
                        class="rounded-lg px-2.5 py-1.5 text-xs font-medium {{ $minRating === $stars ? 'bg-blue-600 text-white' : 'border border-gray-300 text-gray-700 dark:border-gray-600 dark:text-gray-300' }}">
                    {{ $stars }}★+
                </button>
            @endforeach
        </div>
    </div>

    @if ($reviews->isEmpty())
        <p class="mt-5 text-gray-500 dark:text-gray-400">
            No reviews match that filter yet.
        </p>
    @else
        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            @foreach ($reviews as $review)
                <x-google-places.review-card :review="$review" wire:key="review-{{ $review->id }}" />
            @endforeach
        </div>
    @endif

    @if ($hasMore)
        <button type="button" wire:click="loadMore"
                class="mt-5 rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700
                       hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800">
            <span wire:loading.remove wire:target="loadMore">Show more reviews</span>
            <span wire:loading wire:target="loadMore">Loading…</span>
        </button>
    @endif

    {{-- Required wherever Google data is displayed. --}}
    <p class="mt-6 text-xs text-gray-400 dark:text-gray-500">Reviews powered by Google</p>
</div>
