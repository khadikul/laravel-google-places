@props([
    'rating' => null,
    'count' => null,
    'size' => 'sm',
])

@php
    // Google returns no rating for a place nobody has reviewed, so guard
    // rather than rendering zero stars as if it were a real score.
    $value = is_numeric($rating) ? (float) $rating : null;
    $filled = $value === null ? 0 : (int) round($value);
    $text = ['sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-xl'][$size] ?? 'text-sm';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5 '.$text]) }}>
    @if ($value === null)
        <span class="text-gray-500 dark:text-gray-400">No rating yet</span>
    @else
        <span class="flex" aria-hidden="true">
            @for ($i = 1; $i <= 5; $i++)
                <svg class="size-[1.1em] {{ $i <= $filled ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600' }}"
                     viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 15.27 16.18 19l-1.64-7.03L20 7.24l-7.19-.61L10 0 7.19 6.63 0 7.24l5.46 4.73L3.82 19z"/>
                </svg>
            @endfor
        </span>

        <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($value, 1) }}</span>

        @if ($count !== null)
            <span class="text-gray-500 dark:text-gray-400">({{ number_format((int) $count) }})</span>
        @endif

        <span class="sr-only">{{ number_format($value, 1) }} out of 5</span>
    @endif
</div>
