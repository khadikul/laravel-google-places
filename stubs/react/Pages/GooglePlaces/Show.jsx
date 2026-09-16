import { Head, Link } from '@inertiajs/react'
import Rating from '@/Components/GooglePlaces/Rating'
import ReviewsWidget from '@/Components/GooglePlaces/ReviewsWidget'

/**
 * Published by google-places:scaffold. Yours to edit.
 */
export default function Show({ place = null, photoUrl = null, reviews = [], error = null }) {
  if (error || !place) {
    return (
      <>
        <Head title="Business" />
        <div className="mx-auto max-w-4xl px-4 py-10">
          <div className="rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
            {error ?? 'This place could not be loaded.'}
          </div>
        </div>
      </>
    )
  }

  return (
    <>
      <Head title={place.name ?? 'Business'} />

      <div className="mx-auto max-w-4xl px-4 py-10">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">{place.name ?? 'Unnamed place'}</h1>
        <p className="mt-1 text-gray-600 dark:text-gray-400">{place.address}</p>

        <Rating rating={place.rating} count={place.review_count} size="lg" className="mt-4" />

        {photoUrl && (
          <figure className="mt-6">
            <img src={photoUrl} alt="" className="w-full rounded-xl object-cover" />
            {/* Google requires the photo's author attribution to be displayed. */}
            <figcaption className="mt-2 text-xs text-gray-500 dark:text-gray-400">
              Photo by {(place.photo_attribution ?? []).join(', ') || 'a Google user'}
            </figcaption>
          </figure>
        )}

        <ReviewsWidget
          title="Reviews"
          reviews={reviews}
          rating={place.rating}
          reviewCount={place.review_count}
          mapsUrl={place.google_maps_uri}
        />

        <Link
          href={route('google-places.page.search')}
          className="text-blue-600 hover:underline dark:text-blue-400"
        >
          ← Back to search
        </Link>
      </div>
    </>
  )
}
