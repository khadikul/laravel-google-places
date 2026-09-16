<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Google Business</title>
    {{-- Renders unstyled rather than failing when assets have not been built yet. --}}
    @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
        @vite(['resources/css/app.css'])
    @endif
</head>
<body class="h-full bg-gray-50 text-gray-900 antialiased dark:bg-gray-950 dark:text-gray-100">
<div class="mx-auto max-w-4xl px-4 py-10">
    <h1 class="text-2xl font-bold">Google Business Profile</h1>

    @if (session('google_places_status') === 'connected')
        <div class="mt-4 rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200">
            Google account connected.
        </div>
    @endif

    @if (session('google_places_error'))
        <div class="mt-4 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
            Could not connect: {{ session('google_places_error') }}
        </div>
    @endif

    @if (session('status'))
        <div class="mt-4 rounded-lg bg-blue-50 p-4 text-sm text-blue-800 dark:bg-blue-900/30 dark:text-blue-200">
            {{ session('status') }}
        </div>
    @endif

    <x-google-places.location-manager
        class="mt-6"
        :connection="$connection"
        :accounts="$accounts"
        :locations="$locations"
        :connected="$connected" />
</div>
</body>
</html>
