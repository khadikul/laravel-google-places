import { Link } from '@inertiajs/react'
import Rating from './Rating'

/**
 * Published by google-places:scaffold. Yours to edit.
 *
 * Every field except the ID can be null: the Places API returns only what the
 * field mask asked for.
 */
export default function PlaceCard({ place, href = null, children, className = '' }) {
  const name = place.name ?? 'Unnamed place'

  return (
    <article
      className={`rounded-xl border border-gray-200 bg-white p-5 transition hover:border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:hover:border-gray-600 ${className}`}
    >
      <h3 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
        {href ? (
          <Link href={href} className="hover:underline">
            {name}
          </Link>
        ) : (
          name
        )}
      </h3>

      {place.address && <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">{place.address}</p>}

      <Rating rating={place.rating} count={place.review_count} className="mt-2.5" />

      <div className="mt-3 flex flex-wrap items-center gap-2 text-xs">
        {place.primary_type && (
          <span className="rounded-full bg-gray-100 px-2 py-0.5 text-gray-600 dark:bg-gray-800 dark:text-gray-300">
            {place.primary_type}
          </span>
        )}

        {place.business_status && place.business_status !== 'OPERATIONAL' && (
          <span className="rounded-full bg-red-50 px-2 py-0.5 text-red-700 dark:bg-red-900/40 dark:text-red-300">
            {place.business_status.replaceAll('_', ' ').toLowerCase()}
          </span>
        )}
      </div>

      {children}
    </article>
  )
}
