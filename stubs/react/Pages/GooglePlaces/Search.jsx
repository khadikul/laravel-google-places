import { useState } from 'react'
import { Head, router } from '@inertiajs/react'
import PlaceCard from '@/Components/GooglePlaces/PlaceCard'

/**
 * Published by google-places:scaffold. Yours to edit.
 *
 * Wrap this in your starter kit's layout once you have looked it over.
 */
export default function Search({ query = '', results = [], error = null }) {
  const [term, setTerm] = useState(query ?? '')

  const submit = (event) => {
    event.preventDefault()
    // A GET so the search is shareable and back-button friendly.
    router.get(route('google-places.page.search'), { q: term }, { preserveState: true })
  }

  return (
    <>
      <Head title="Find a business" />

      <div className="mx-auto max-w-4xl px-4 py-10">
        <h1 className="text-2xl font-bold text-gray-900 dark:text-gray-100">Find a business on Google</h1>

        <form onSubmit={submit} className="mt-5 flex flex-wrap items-center gap-2">
          <label htmlFor="gp-q" className="sr-only">
            Business name
          </label>
          <input
            id="gp-q"
            type="search"
            value={term}
            onChange={(event) => setTerm(event.target.value)}
            placeholder="Search for a business, e.g. Torlyx Security"
            className="min-w-0 flex-1 rounded-lg border border-gray-300 px-3.5 py-2.5 text-gray-900 shadow-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-500/30 focus:outline-none dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100"
          />
          <button
            type="submit"
            className="rounded-lg bg-blue-600 px-4 py-2.5 font-medium text-white transition hover:bg-blue-700"
          >
            Search
          </button>
        </form>

        {error && (
          <div className="mt-5 rounded-lg bg-red-50 p-4 text-sm text-red-800 dark:bg-red-900/30 dark:text-red-200">
            {error}
          </div>
        )}

        {query && results.length === 0 && !error && (
          <p className="mt-6 text-gray-500 dark:text-gray-400">Google matched no places for “{query}”.</p>
        )}

        <div className="mt-6 grid gap-4 sm:grid-cols-2">
          {results.map((place) => (
            <PlaceCard key={place.id} place={place} href={route('google-places.page.show', place.id)} />
          ))}
        </div>
      </div>
    </>
  )
}
