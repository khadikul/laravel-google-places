import Rating from './Rating'

/**
 * Published by google-places:scaffold. Yours to edit.
 *
 * Expects the snake_case shape that Review::toArray() produces.
 */
export default function ReviewCard({ review, className = '' }) {
  const author = review.author_name
  const isOwnerFeed = review.source === 'business_profile'

  return (
    <article className={`rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-gray-900 ${className}`}>
      <div className="flex items-start gap-3">
        {review.author_photo_uri ? (
          // Google's terms require the reviewer's photo and name to be shown as given.
          <img
            src={review.author_photo_uri}
            alt=""
            referrerPolicy="no-referrer"
            className="size-10 shrink-0 rounded-full object-cover"
          />
        ) : (
          <div className="flex size-10 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-semibold text-gray-500 dark:bg-gray-800 dark:text-gray-400">
            {(author ?? 'G').charAt(0).toUpperCase()}
          </div>
        )}

        <div className="min-w-0 flex-1">
          <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
            {review.author_uri ? (
              <a
                href={review.author_uri}
                target="_blank"
                rel="noopener noreferrer"
                className="font-semibold text-gray-900 hover:underline dark:text-gray-100"
              >
                {author ?? 'A Google user'}
              </a>
            ) : (
              // Anonymous reviewers have no name; never invent one.
              <span className="font-semibold text-gray-900 dark:text-gray-100">{author ?? 'A Google user'}</span>
            )}

            {isOwnerFeed && (
              <span className="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-300">
                Owner feed
              </span>
            )}
          </div>

          <Rating rating={review.rating} className="mt-1" />

          {review.text ? (
            // Review text must be shown unmodified, or not at all.
            <p className="mt-3 whitespace-pre-line text-gray-700 dark:text-gray-300">{review.text}</p>
          ) : (
            <p className="mt-3 text-sm italic text-gray-500 dark:text-gray-400">Rating only, no written review.</p>
          )}

          {review.reply_text && (
            <div className="mt-3 rounded-lg bg-gray-50 p-3 text-sm dark:bg-gray-800">
              <span className="font-semibold text-gray-900 dark:text-gray-100">Response from the owner</span>
              <p className="mt-1 whitespace-pre-line text-gray-700 dark:text-gray-300">{review.reply_text}</p>
            </div>
          )}

          <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
            {review.relative_publish_time ??
              (review.publish_time ? new Date(review.publish_time).toLocaleDateString() : '')}
          </p>
        </div>
      </div>
    </article>
  )
}
