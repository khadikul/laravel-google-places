import Rating from './Rating'
import ReviewCard from './ReviewCard'

/**
 * Published by google-places:scaffold. Yours to edit.
 *
 * The public-facing block. Pass reviews the controller already loaded from your
 * own database: no API call, no billing, no latency.
 */
export default function ReviewsWidget({
  reviews = [],
  rating = null,
  reviewCount = null,
  mapsUrl = null,
  title = 'What our customers say',
  className = '',
}) {
  return (
    <section className={`py-10 ${className}`}>
      <div className="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
          <h2 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{title}</h2>
          {rating !== null && <Rating rating={rating} count={reviewCount} size="md" className="mt-2" />}
        </div>

        {mapsUrl && (
          // Google requires a link back to the source of the data.
          <a
            href={mapsUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
          >
            Read them on Google
            <svg className="size-4" viewBox="0 0 20 20" fill="currentColor">
              <path d="M11 3a1 1 0 100 2h2.59l-6.3 6.29a1 1 0 101.42 1.42L15 6.41V9a1 1 0 102 0V4a1 1 0 00-1-1h-5z" />
              <path d="M5 5a2 2 0 00-2 2v8a2 2 0 002 2h8a2 2 0 002-2v-3a1 1 0 10-2 0v3H5V7h3a1 1 0 000-2H5z" />
            </svg>
          </a>
        )}
      </div>

      {reviews.length === 0 ? (
        <p className="text-gray-500 dark:text-gray-400">No reviews to show yet.</p>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {reviews.map((review) => (
            <ReviewCard key={review.review_name} review={review} />
          ))}
        </div>
      )}

      {/* Attribution is required wherever Google data is displayed. */}
      <p className="mt-6 text-xs text-gray-400 dark:text-gray-500">Reviews powered by Google</p>
    </section>
  )
}
