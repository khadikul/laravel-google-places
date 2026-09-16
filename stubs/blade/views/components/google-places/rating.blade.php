@props([
    'rating' => null,
    'count' => null,
    'size' => 'sm',
    'showValue' => true,
    'verified' => false,
])

@php
    // Google returns no rating for a place nobody has reviewed, so guard rather
    // than rendering zero stars as if it were a real score.
    $value = is_numeric($rating) ? (float) $rating : null;
    $filled = $value === null ? 0 : (int) round($value);
    $text = ['sm' => 'text-sm', 'md' => 'text-base', 'lg' => 'text-2xl'][$size] ?? 'text-sm';
@endphp

<div {{ $attributes->merge(['class' => 'flex items-center gap-1.5 '.$text]) }}>
    @if ($value === null)
        <span class="text-gray-500 dark:text-gray-400">No rating yet</span>
    @else
        <span class="flex gap-0.5" aria-hidden="true">
            @for ($i = 1; $i <= 5; $i++)
                <svg class="size-[1.05em] {{ $i <= $filled ? 'text-[#fbbc04]' : 'text-gray-300 dark:text-gray-600' }}"
                     viewBox="0 0 20 20" fill="currentColor">
                    <path d="M10 15.27 16.18 19l-1.64-7.03L20 7.24l-7.19-.61L10 0 7.19 6.63 0 7.24l5.46 4.73L3.82 19z"/>
                </svg>
            @endfor
        </span>

        @if ($verified)
            {{-- Mirrors the "verified review" mark people expect on Google widgets. --}}
            <svg class="size-[1.05em] text-[#4285f4]" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M10 0l2.2 1.6 2.7-.2 1 2.5 2.3 1.4-.8 2.6.8 2.6-2.3 1.4-1 2.5-2.7-.2L10 16l-2.2-1.6-2.7.2-1-2.5L1.8 10.7l.8-2.6-.8-2.6 2.3-1.4 1-2.5 2.7.2L10 0zm-.7 11.6l4.3-4.3-1.1-1.1-3.2 3.2-1.5-1.5-1.1 1.1 2.6 2.6z" clip-rule="evenodd"/>
            </svg>
        @endif

        @if ($showValue)
            <span class="font-semibold text-gray-900 dark:text-gray-100">{{ number_format($value, 1) }}</span>
        @endif

        @if ($count !== null)
            <span class="text-gray-500 dark:text-gray-400">({{ number_format((int) $count) }})</span>
        @endif

        <span class="sr-only">{{ number_format($value, 1) }} out of 5</span>
    @endif
</div>
