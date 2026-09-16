@props([
    'placeId',
    'limit' => 12,
    'title' => null,

    // Pass these when the caller already has a Place; it saves the lookup and
    // keeps the summary populated before anything has been stored locally.
    'rating' => null,
    'count' => null,
    'mapsUrl' => null,
])

@php
    use Khadikul\GooglePlaces\Facades\GooglePlaces;

    /*
     | Reads from YOUR database first, which costs nothing and adds no latency.
     | Only falls back to the API when nothing has been synced yet.
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

    $average = $rating ?? $summary?->rating ?? $place?->rating();
    $total = $count ?? $summary?->review_count ?? $place?->reviewCount();
    $mapsUrl = $mapsUrl ?? $summary?->google_maps_uri ?? $place?->mapsUrl();

    // Google's own wording for a star average.
    $verdict = match (true) {
        $average === null => null,
        $average >= 4.5 => 'Excellent',
        $average >= 3.5 => 'Great',
        $average >= 2.5 => 'Average',
        $average >= 1.5 => 'Poor',
        default => 'Bad',
    };

    $id = 'gp-'.substr(sha1($placeId), 0, 8);
@endphp

<section {{ $attributes->merge(['class' => 'gp-reviews']) }}>
    @if ($title)
        <h2 class="mb-6 text-2xl font-bold text-gray-900 dark:text-gray-100">{{ $title }}</h2>
    @endif

    <div class="flex flex-col items-stretch gap-6 lg:flex-row lg:items-center">

        {{-- Summary panel --}}
        <div class="shrink-0 text-center lg:w-52">
            @if ($verdict)
                <p class="text-xl font-bold uppercase tracking-wide text-gray-900 dark:text-gray-100">{{ $verdict }}</p>
            @endif

            <div class="mt-1.5 flex justify-center">
                <x-google-places.rating :rating="$average" size="lg" :show-value="false" />
            </div>

            @if ($total !== null)
                <p class="mt-1.5 text-sm text-gray-600 dark:text-gray-400">
                    Based on <strong class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format((int) $total) }} reviews</strong>
                </p>
            @endif

            {{-- Google requires its data to be credited and linked back. --}}
            <a href="{{ $mapsUrl ?? 'https://www.google.com/maps' }}" target="_blank" rel="noopener noreferrer"
               class="mt-2.5 inline-flex items-center justify-center" aria-label="Read these reviews on Google">
                <svg class="h-7" viewBox="0 0 272 92" aria-hidden="true">
                    <path fill="#EA4335" d="M115.75 47.18c0 12.77-9.99 22.18-22.25 22.18s-22.25-9.41-22.25-22.18C71.25 34.32 81.24 25 93.5 25s22.25 9.32 22.25 22.18zm-9.74 0c0-7.98-5.79-13.44-12.51-13.44S80.99 39.2 80.99 47.18c0 7.9 5.79 13.44 12.51 13.44s12.51-5.55 12.51-13.44z"/>
                    <path fill="#FBBC05" d="M163.75 47.18c0 12.77-9.99 22.18-22.25 22.18s-22.25-9.41-22.25-22.18c0-12.85 9.99-22.18 22.25-22.18s22.25 9.32 22.25 22.18zm-9.74 0c0-7.98-5.79-13.44-12.51-13.44s-12.51 5.46-12.51 13.44c0 7.9 5.79 13.44 12.51 13.44s12.51-5.55 12.51-13.44z"/>
                    <path fill="#4285F4" d="M209.75 26.34v39.82c0 16.38-9.66 23.07-21.08 23.07-10.75 0-17.22-7.19-19.66-13.07l8.48-3.53c1.51 3.61 5.21 7.87 11.17 7.87 7.31 0 11.84-4.51 11.84-13v-3.19h-.34c-2.18 2.69-6.38 5.04-11.68 5.04-11.09 0-21.25-9.66-21.25-22.09 0-12.52 10.16-22.26 21.25-22.26 5.29 0 9.49 2.35 11.68 4.96h.34v-3.61h9.25zm-8.56 20.92c0-7.81-5.21-13.52-11.84-13.52-6.72 0-12.35 5.71-12.35 13.52 0 7.73 5.63 13.36 12.35 13.36 6.63 0 11.84-5.63 11.84-13.36z"/>
                    <path fill="#34A853" d="M225 3v65h-9.5V3h9.5z"/>
                    <path fill="#EA4335" d="M262.02 54.48l7.56 5.04c-2.44 3.61-8.32 9.83-18.48 9.83-12.6 0-22.01-9.74-22.01-22.18 0-13.19 9.49-22.18 20.92-22.18 11.51 0 17.14 9.16 18.98 14.11l1.01 2.52-29.65 12.28c2.27 4.45 5.8 6.72 10.75 6.72 4.96 0 8.4-2.44 10.92-6.14zm-23.27-7.98l19.82-8.23c-1.09-2.77-4.37-4.7-8.23-4.7-4.95 0-11.84 4.37-11.59 12.93z"/>
                    <path fill="#4285F4" d="M35.29 41.41V32H67c.31 1.64.47 3.58.47 5.68 0 7.06-1.93 15.79-8.15 22.01-6.05 6.3-13.78 9.66-24.02 9.66C16.32 69.35.36 53.89.36 34.91.36 15.93 16.32.47 35.3.47c10.5 0 17.98 4.12 23.6 9.49l-6.64 6.64c-4.03-3.78-9.49-6.72-16.97-6.72-13.86 0-24.7 11.17-24.7 25.03 0 13.86 10.84 25.03 24.7 25.03 8.99 0 14.11-3.61 17.39-6.89 2.66-2.66 4.41-6.46 5.1-11.65l-22.49.01z"/>
                </svg>
            </a>
        </div>

        {{-- Carousel --}}
        <div class="relative min-w-0 flex-1" data-gp-carousel>
            <button type="button" data-gp-prev aria-label="Previous reviews"
                    class="absolute -left-3 top-1/2 z-10 hidden size-8 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50 disabled:opacity-0 sm:flex dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M12.79 5.23a.75.75 0 0 1 0 1.06L9.06 10l3.73 3.71a.75.75 0 1 1-1.06 1.06l-4.25-4.24a.75.75 0 0 1 0-1.06l4.25-4.24a.75.75 0 0 1 1.06 0z" clip-rule="evenodd"/></svg>
            </button>

            <div data-gp-track
                 class="flex snap-x snap-mandatory gap-4 overflow-x-auto scroll-smooth pb-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">

                @forelse ($reviews as $review)
                    <x-google-places.review-card :review="$review" class="w-64 shrink-0 snap-start" />
                @empty
                    <p class="py-8 text-gray-500 dark:text-gray-400">No reviews to show yet.</p>
                @endforelse
            </div>

            <button type="button" data-gp-next aria-label="More reviews"
                    class="absolute -right-3 top-1/2 z-10 hidden size-8 -translate-y-1/2 items-center justify-center rounded-full border border-gray-200 bg-white text-gray-600 shadow-sm transition hover:bg-gray-50 disabled:opacity-0 sm:flex dark:border-gray-700 dark:bg-gray-800 dark:text-gray-300">
                <svg class="size-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 0-1.06L10.94 10 7.21 6.29a.75.75 0 1 1 1.06-1.06l4.25 4.24a.75.75 0 0 1 0 1.06l-4.25 4.24a.75.75 0 0 1-1.06 0z" clip-rule="evenodd"/></svg>
            </button>
        </div>
    </div>
</section>

@once
    {{--
        Vanilla JS on purpose: no Alpine, no jQuery, nothing to install. It only
        wires up the arrows and the "Read more" toggle, and the widget is fully
        readable without it.
    --}}
    @push('scripts')
    @endpush

    <script>
        document.addEventListener('click', (event) => {
            const more = event.target.closest('[data-gp-more]')
            if (more) {
                const body = more.previousElementSibling
                const expanded = body.classList.toggle('line-clamp-none')
                body.classList.toggle('line-clamp-4', !expanded)
                more.textContent = expanded ? 'Show less' : 'Read more'
                return
            }

            const arrow = event.target.closest('[data-gp-prev], [data-gp-next]')
            if (!arrow) return

            const track = arrow.closest('[data-gp-carousel]').querySelector('[data-gp-track]')
            const step = (track.querySelector('article')?.offsetWidth ?? 260) + 16
            track.scrollBy({ left: arrow.hasAttribute('data-gp-next') ? step : -step, behavior: 'smooth' })
        })

        // Hide an arrow when there is nothing further to scroll to.
        document.querySelectorAll('[data-gp-carousel]').forEach((carousel) => {
            const track = carousel.querySelector('[data-gp-track]')
            const prev = carousel.querySelector('[data-gp-prev]')
            const next = carousel.querySelector('[data-gp-next]')

            const sync = () => {
                prev.disabled = track.scrollLeft <= 1
                next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 1
            }

            track.addEventListener('scroll', sync, { passive: true })
            window.addEventListener('resize', sync)
            sync()
        })
    </script>
@endonce
