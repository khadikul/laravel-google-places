@props([
    'placeId',
    'limit' => 6,
    'title' => 'What our customers say',

    // Pass these when the caller already has a Place; it saves the lookup and
    // keeps the header populated even before anything has been stored locally.
    'rating' => null,
    'count' => null,
    'mapsUrl' => null,
])

@php
    use Khadikul\GooglePlaces\Facades\GooglePlaces;

    /*
     | The public-facing widget. Reads from YOUR database first, which costs
     | nothing and is fast; only falls back to the API when nothing has been
     | synced yet.
     */
    $reviews = GooglePlaces::reviews($placeId, limit: (int) $limit);
    $place = null;

    if ($reviews->isEmpty()) {
        try {
            $place = GooglePlaces::place($placeId);
            $reviews = $place->reviews()->take((int) $limit);
        } catch (\Khadikul\GooglePlaces\Exceptions\GooglePlacesException) {
            // Never let a Google outage take a marketing page down.
            $reviews = collect();
        }
    }

    $summary = \Khadikul\GooglePlaces\Models\GooglePlace::query()
        ->where('place_id', $placeId)
        ->first();

    /*
     | Explicit props win, then the stored snapshot, then whatever the fallback
     | lookup happened to return. Without the first of those, a site that syncs
     | reviews but never calls GooglePlaces::store() would show the reviews with
     | no rating and no link back to Google.
     */
    $average = $rating ?? $summary?->rating ?? $place?->rating();
    $total = $count ?? $summary?->review_count ?? $place?->reviewCount();
    $mapsUrl = $mapsUrl ?? $summary?->google_maps_uri ?? $place?->mapsUrl();
@endphp

<section {{ $attributes->merge(['class' => 'py-10']) }}>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $title }}</h2>

            @if ($average !== null)
                <div class="mt-2 flex items-center gap-2">
                    <x-google-places.rating :rating="$average" :count="$total" size="md" />
                </div>
            @endif
        </div>

        @if (filled($mapsUrl))
            {{-- Google requires a link back to the source of the data. --}}
            <a href="{{ $mapsUrl }}" target="_blank" rel="noopener noreferrer"
               class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400">
                Read them on Google
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor">
                    <path d="M11 3a1 1 0 100 2h2.59l-6.3 6.29a1 1 0 101.42 1.42L15 6.41V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z"/>
                    <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z"/>
                </svg>
            </a>
        @endif
    </div>

    @if ($reviews->isEmpty())
        <p class="text-gray-500 dark:text-gray-400">No reviews to show yet.</p>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($reviews as $review)
                <x-google-places.review-card :review="$review" />
            @endforeach
        </div>
    @endif

    {{-- Attribution is required wherever Google data is displayed. --}}
    <p class="mt-6 text-xs text-gray-400 dark:text-gray-500">Reviews powered by Google</p>
</section>
