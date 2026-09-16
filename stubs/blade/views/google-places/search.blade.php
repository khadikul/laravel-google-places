{{--
    A standalone page so the scaffolding works in any application, including one
    with no layout of its own. Swap the wrapper for your own @extends once you
    have looked it over.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Find a business</title>
    {{-- Renders unstyled rather than failing when assets have not been built yet. --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
<div class="mx-auto max-w-4xl px-4 py-10">
    <h1 class="text-2xl font-bold">Find a business on Google</h1>

    <x-google-places.search-form :query="$query" class="mt-5" />

    @if ($error)
        <div class="mt-5 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
            {{ $error }}
        </div>
    @endif

    @if ($query && $results->isEmpty() && ! $error)
        <p class="mt-6 text-gray-500 dark:text-gray-400">
            Google matched no places for &ldquo;{{ $query }}&rdquo;.
        </p>
    @endif

    <div class="mt-6 grid gap-4 sm:grid-cols-2">
        @foreach ($results as $place)
            <x-google-places.place-card
                :place="$place"
                :href="route('google-places.page.show', $place->id)" />
        @endforeach
    </div>
</div>
</body>
</html>
