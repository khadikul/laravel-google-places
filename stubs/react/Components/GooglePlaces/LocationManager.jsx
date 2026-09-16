import { router, useForm } from '@inertiajs/react'
import ConnectGoogleButton from './ConnectGoogleButton'

/**
 * Published by google-places:scaffold. Yours to edit.
 *
 * Render it from a page that sits behind your own auth middleware.
 */
export default function LocationManager({
  connection = null,
  locations = [],
  connected = [],
  connectUrl,
  connectRoute,
  syncRoute,
  className = '',
}) {
  const { post, processing } = useForm({})

  const connect = (location) =>
    router.post(connectRoute, { location: location.name, account: location.account_name }, { preserveScroll: true })

  const sync = (locationName) =>
    router.post(syncRoute, { location: locationName }, { preserveScroll: true })

  return (
    <div className={`space-y-6 ${className}`}>
      <section className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Google connection</h2>

        {connection ? (
          <dl className="mt-3 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
            <div>
              <dt className="text-gray-500 dark:text-gray-400">Account</dt>
              <dd className="font-medium text-gray-900 dark:text-gray-100">
                {connection.google_account_name ?? '—'}
              </dd>
            </div>
            <div>
              <dt className="text-gray-500 dark:text-gray-400">Notifications</dt>
              <dd className="font-medium text-gray-900 dark:text-gray-100">
                {connection.notifications_subscribed_at ? 'Subscribed' : 'Not subscribed'}
              </dd>
            </div>
          </dl>
        ) : (
          <>
            <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
              No Google Business Profile connected yet.
            </p>
            <ConnectGoogleButton href={connectUrl} className="mt-4" />
          </>
        )}
      </section>

      {locations.length > 0 && (
        <section className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
          <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">Available locations</h2>

          <div className="mt-3 space-y-2">
            {locations.map((location) => (
              <div
                key={location.resource_name ?? location.name}
                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
              >
                <div>
                  <div className="font-medium text-gray-900 dark:text-gray-100">{location.title}</div>
                  <div className="text-xs text-gray-500 dark:text-gray-400">
                    {location.place_id ?? 'no place ID yet'}
                  </div>
                </div>

                <button
                  type="button"
                  disabled={processing}
                  onClick={() => connect(location)}
                  className="rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-medium text-white hover:bg-blue-700 disabled:opacity-60"
                >
                  Connect &amp; sync
                </button>
              </div>
            ))}
          </div>
        </section>
      )}

      <section className="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900">
        <h2 className="text-lg font-semibold text-gray-900 dark:text-gray-100">
          Synced locations ({connected.length})
        </h2>

        {connected.length === 0 ? (
          <p className="mt-2 text-sm text-gray-500 dark:text-gray-400">Nothing connected yet.</p>
        ) : (
          <div className="mt-3 space-y-2">
            {connected.map((location) => (
              <div
                key={location.location_name}
                className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-gray-100 p-3 dark:border-gray-800"
              >
                <div>
                  <div className="font-medium text-gray-900 dark:text-gray-100">
                    {location.title ?? location.location_name}
                  </div>
                  <div className="text-xs text-gray-500 dark:text-gray-400">
                    {location.total_review_count ?? 0} reviews
                  </div>
                </div>

                <button
                  type="button"
                  onClick={() => sync(location.location_name)}
                  className="rounded-lg border border-gray-300 px-3 py-1.5 text-xs font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                  Sync now
                </button>
              </div>
            ))}
          </div>
        )}
      </section>
    </div>
  )
}
