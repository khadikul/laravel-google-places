<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $place?->name() ?? 'Business' }}</title>
    {{-- Renders unstyled rather than failing when assets have not been built yet. --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
<div class="mx-auto max-w-4xl px-4 py-10">
    @if ($error)
        <div class="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ $error }}
        </div>
    @else
        <h1 class="text-2xl font-bold">{{ $place->name() ?? 'Unnamed place' }}</h1>
        <p class="mt-1 text-gray-600 dark:text-gray-400">{{ $place->address }}</p>

        <x-google-places.rating :rating="$place->rating()" :count="$place->reviewCount()" size="lg" class="mt-4" />

        @if ($photoUrl)
            <figure class="mt-6">
                <img src="{{ $photoUrl }}" alt="" class="w-full rounded-xl object-cover">
                {{-- Google requires the photo's author attribution to be displayed. --}}
                <figcaption class="mt-2 text-xs text-gray-500 dark:text-gray-400">
                    Photo by {{ implode(', ', $place->photos()->first()?->attributionNames() ?? []) ?: 'a Google user' }}
                </figcaption>
            </figure>
        @endif

        {{-- The page already has the Place, so hand its summary to the widget. --}}
        <x-google-places.reviews-widget
            :place-id="$place->id"
            :rating="$place->rating()"
            :count="$place->reviewCount()"
            :maps-url="$place->mapsUrl()"
            title="Reviews" />
    @endif

    <p class="mt-8">
        <a href="{{ route('google-places.page.search') }}" class="text-blue-600 hover:underline dark:text-blue-400">
            &larr; Back to search
        </a>
    </p>
</div>
</body>
</html>
