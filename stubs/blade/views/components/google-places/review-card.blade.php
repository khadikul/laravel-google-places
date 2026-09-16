@props(['review'])

@php
    /*
     | Accepts either a Review DTO (from the API) or a GoogleReview model (from
     | your database): the two are used in different places and name these
     | fields differently.
     */
    $isModel = $review instanceof \Khadikul\GooglePlaces\Models\GoogleReview;

    $author = $isModel ? $review->author_name : $review->authorName;
    $photo = $isModel ? $review->author_photo_uri : $review->authorPhotoUri;
    $profile = $isModel ? $review->author_uri : $review->authorUri;
    $rating = $review->rating;
    $text = $review->text;
    $reply = $isModel ? $review->reply_text : $review->replyText;
    $published = $isModel ? $review->publish_time : $review->publishedAt;
    $relative = $isModel ? $review->relative_publish_time : $review->relativePublishTime;

    $when = $relative ?? $published?->diffForHumans();

    // Deterministic avatar colour so the same reviewer always looks the same.
    $palette = ['bg-rose-700', 'bg-amber-700', 'bg-emerald-700', 'bg-sky-700', 'bg-violet-700', 'bg-stone-700'];
    $tint = $palette[crc32((string) ($author ?? 'anon')) % count($palette)];
@endphp

<article {{ $attributes->merge(['class' => 'flex flex-col rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900']) }}>

    <div class="flex items-start gap-3">
        @if (filled($photo))
            {{-- Google's terms require the reviewer's photo and name to be shown as given. --}}
            <img src="{{ $photo }}" alt="" referrerpolicy="no-referrer" loading="lazy"
                 class="size-10 shrink-0 rounded-full object-cover">
        @else
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $tint }} text-sm font-semibold text-white">
                {{ mb_strtoupper(mb_substr($author ?? 'G', 0, 1)) }}
            </div>
        @endif

        <div class="min-w-0 flex-1">
            @if (filled($profile))
                <a href="{{ $profile }}" target="_blank" rel="noopener noreferrer"
                   class="block truncate font-semibold text-gray-900 hover:underline dark:text-gray-100">
                    {{ $author ?? 'A Google user' }}
                </a>
            @else
                {{-- Anonymous reviewers have no name; never invent one. --}}
                <span class="block truncate font-semibold text-gray-900 dark:text-gray-100">
                    {{ $author ?? 'A Google user' }}
                </span>
            @endif

            <span class="text-xs text-gray-500 dark:text-gray-400">{{ $when }}</span>
        </div>

        {{-- Source mark, as Google's attribution rules expect. --}}
        <svg class="size-5 shrink-0" viewBox="0 0 24 24" aria-label="Posted on Google">
            <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.6v3h3.9c2.3-2.1 3.5-5.2 3.5-8.8z"/>
            <path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3a7.2 7.2 0 0 1-10.7-3.8h-4v3.1A12 12 0 0 0 12 24z"/>
            <path fill="#FBBC05" d="M5.3 14.3a7.1 7.1 0 0 1 0-4.6v-3h-4a12 12 0 0 0 0 10.7l4-3.1z"/>
            <path fill="#EA4335" d="M12 4.8c1.8 0 3.4.6 4.6 1.8l3.5-3.5A12 12 0 0 0 1.3 6.6l4 3.1A7.2 7.2 0 0 1 12 4.8z"/>
        </svg>
    </div>

    <x-google-places.rating :rating="$rating" :show-value="false" verified class="mt-3" />

    @if (filled($text))
        {{-- Review text must be shown unmodified, or not at all. --}}
        <p class="mt-2.5 line-clamp-4 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $text }}</p>

        <button type="button" data-gp-more
                class="mt-2 self-start text-xs font-medium text-gray-500 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-200">
            Read more
        </button>
    @else
        <p class="mt-2.5 text-sm italic text-gray-500 dark:text-gray-400">Rating only, no written review.</p>
    @endif

    @if (filled($reply))
        <div class="mt-3 rounded-lg bg-gray-50 p-3 text-xs dark:bg-gray-800">
            <span class="font-semibold text-gray-900 dark:text-gray-100">Response from the owner</span>
            <p class="mt-1 line-clamp-3 text-gray-700 dark:text-gray-300">{{ $reply }}</p>
        </div>
    @endif
</article>
