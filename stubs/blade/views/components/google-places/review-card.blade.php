@props(['review'])

@php
    /*
     | Accepts either a Review DTO (from the API) or a GoogleReview model (from
     | your database), because the two are used in different places and share
     | these fields under different names.
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
    $source = $isModel ? $review->source : $review->source->value;
@endphp

<article {{ $attributes->merge(['class' => 'rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900']) }}>
    <div class="flex items-start gap-3">
        @if (filled($photo))
            {{-- Google's terms require the reviewer's photo and name to be shown as given. --}}
            <img src="{{ $photo }}" alt="" referrerpolicy="no-referrer"
                 class="size-10 shrink-0 rounded-full object-cover">
        @else
            <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                {{ mb_strtoupper(mb_substr($author ?? 'G', 0, 1)) }}
            </div>
        @endif

        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                @if (filled($profile))
                    <a href="{{ $profile }}" target="_blank" rel="noopener noreferrer"
                       class="font-semibold text-gray-900 hover:underline dark:text-gray-100">
                        {{ $author ?? 'A Google user' }}
                    </a>
                @else
                    {{-- Anonymous reviewers have no name; never invent one. --}}
                    <span class="font-semibold text-gray-900 dark:text-gray-100">{{ $author ?? 'A Google user' }}</span>
                @endif

                @if ($source === 'business_profile')
                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                        Owner feed
                    </span>
                @endif
            </div>

            <x-google-places.rating :rating="$rating" class="mt-1" />

            @if (filled($text))
                {{-- Review text must be shown unmodified, or not at all. --}}
                <p class="mt-3 whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $text }}</p>
            @else
                <p class="mt-3 text-sm italic text-gray-500 dark:text-gray-400">Rating only, no written review.</p>
            @endif

            @if (filled($reply))
                <div class="mt-3 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800">
                    <span class="font-semibold text-gray-900 dark:text-gray-100">Response from the owner</span>
                    <p class="mt-1 whitespace-pre-line text-gray-700 dark:text-gray-300">{{ $reply }}</p>
                </div>
            @endif

            <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                {{ $relative ?? $published?->format('j M Y') }}
            </p>
        </div>
    </div>
</article>
