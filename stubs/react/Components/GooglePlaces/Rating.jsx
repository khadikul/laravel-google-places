/**
 * Published by google-places:scaffold. Yours to edit.
 */
const SIZES = { sm: 'text-sm', md: 'text-base', lg: 'text-xl' }

export default function Rating({ rating, count = null, size = 'sm', className = '' }) {
  // Google returns no rating for a place nobody has reviewed. Rendering zero
  // stars would read as "rated 0", so say nothing instead.
  const value = typeof rating === 'number' ? rating : null

  if (value === null) {
    return <span className={`text-gray-500 dark:text-gray-400 ${SIZES[size] ?? SIZES.sm} ${className}`}>No rating yet</span>
  }

  const filled = Math.round(value)

  return (
    <div className={`flex items-center gap-1.5 ${SIZES[size] ?? SIZES.sm} ${className}`}>
      <span className="flex" aria-hidden="true">
        {[1, 2, 3, 4, 5].map((star) => (
          <svg
            key={star}
            className={`size-[1.1em] ${star <= filled ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600'}`}
            viewBox="0 0 20 20"
            fill="currentColor"
          >
            <path d="M10 15.27 16.18 19l-1.64-7.03L20 7.24l-7.19-.61L10 0 7.19 6.63 0 7.24l5.46 4.73L3.82 19z" />
          </svg>
        ))}
      </span>

      <span className="font-semibold text-gray-900 dark:text-gray-100">{value.toFixed(1)}</span>

      {count !== null && count !== undefined && (
        <span className="text-gray-500 dark:text-gray-400">({count.toLocaleString()})</span>
      )}

      <span className="sr-only">{value.toFixed(1)} out of 5</span>
    </div>
  )
}
