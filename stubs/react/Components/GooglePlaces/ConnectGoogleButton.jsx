/**
 * Published by google-places:scaffold. Yours to edit.
 *
 * A plain anchor rather than an Inertia <Link>.
 *
 * The package's redirect route answers an Inertia request with 409 plus
 * X-Inertia-Location, so a <Link> would also work. An anchor is used anyway
 * because it is the simplest thing that cannot break: leaving the SPA for
 * accounts.google.com is a full navigation either way.
 */
export default function ConnectGoogleButton({
  href,
  label = 'Connect Google Business',
  className = '',
}) {
  return (
    <a
      href={href}
      className={`inline-flex items-center gap-2.5 rounded-lg border border-gray-300 bg-white px-4 py-2.5 font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 ${className}`}
    >
      <svg className="size-5" viewBox="0 0 24 24" aria-hidden="true">
        <path fill="#4285F4" d="M23.5 12.3c0-.8-.1-1.6-.2-2.3H12v4.5h6.5a5.6 5.6 0 0 1-2.4 3.6v3h3.9c2.3-2.1 3.5-5.2 3.5-8.8z" />
        <path fill="#34A853" d="M12 24c3.2 0 5.9-1.1 7.9-2.9l-3.9-3a7.2 7.2 0 0 1-10.7-3.8h-4v3.1A12 12 0 0 0 12 24z" />
        <path fill="#FBBC05" d="M5.3 14.3a7.1 7.1 0 0 1 0-4.6v-3h-4a12 12 0 0 0 0 10.7l4-3.1z" />
        <path fill="#EA4335" d="M12 4.8c1.8 0 3.4.6 4.6 1.8l3.5-3.5A12 12 0 0 0 1.3 6.6l4 3.1A7.2 7.2 0 0 1 12 4.8z" />
      </svg>
      {label}
    </a>
  )
}
