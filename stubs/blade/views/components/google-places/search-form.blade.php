@props([
    'action' => null,
    'query' => '',
    'placeholder' => 'Search for a business, e.g. Torlyx Security',
])

<form method="get" action="{{ $action ?? url()->current() }}"
      {{ $attributes->merge(['class' => 'flex flex-wrap items-center gap-2']) }}>
    <label for="gp-q" class="sr-only">Business name</label>

    <input id="gp-q" type="search" name="q" value="{{ $query }}" placeholder="{{ $placeholder }}"
           class="min-w-0 flex-1 rounded-lg border border-gray-300 px-3.5 py-2.5 text-gray-900 shadow-sm
                  focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none
                  dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">

    <button type="submit"
            class="rounded-lg bg-blue-600 px-4 py-2.5 font-medium text-white transition hover:bg-blue-700
                   focus:ring-2 focus:ring-blue-500/40 focus:outline-none">
        Search
    </button>

    {{ $slot }}
</form>
