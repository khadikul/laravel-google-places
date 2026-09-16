# Laravel Google Places

[![Tests](https://github.com/khadikul/laravel-google-places/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/khadikul/laravel-google-places/actions/workflows/tests.yml)
[![PHP](https://img.shields.io/badge/php-8.2%2B-777bb4.svg)](https://www.php.net/)
[![Laravel](https://img.shields.io/badge/laravel-12.x%20%7C%2013.x-ff2d20.svg)](https://laravel.com/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE.md)

Search Google for a business, show its rating and reviews, and — if you own the
business — keep your Google reviews synchronised into your own database
automatically.

```php
$results = GooglePlaces::search('Torlyx Security');

$place = GooglePlaces::place($results->first()->id);

foreach ($place->reviews() as $review) {
    echo $review->authorName, $review->rating, $review->text;
}
```

---

## Two things you should know first

> **This package does not provide Google credentials. You must use your own
> Google Cloud project and credentials.**
>
> There is no shared API key, no hosted OAuth client, no sign-up and no account.
> You create a Google Cloud project, you enable the APIs, you generate the key.

> **This package does not send your Google data to any third-party server
> operated by the package author.**
>
> Your application talks directly to Google. OAuth tokens are encrypted and
> stored in your database. The notification webhook is a route inside your own
> application. The author distributes PHP code and operates nothing.

```text
Your Laravel app  ──►  Google APIs
        │
        └── your API key, your OAuth client, your Pub/Sub topic, your database
```

There is a test in the suite (`ZeroVendorInfrastructureTest`) that scans the
shipped source and fails the build if any host other than Google's ever appears
in it. That promise is enforced, not just stated.

---

## Contents

1. [Features](#features)
2. [Requirements](#requirements)
3. [Installation](#installation)
4. [Artisan commands](#artisan-commands)
5. [Mode A — public places](#mode-a--public-places)
   - [Google Cloud setup](#google-cloud-setup-mode-a)
   - [Search a business](#search-a-business)
   - [Select a business](#select-a-business)
   - [Display reviews](#display-reviews)
   - [Photos](#photos)
   - [Field masks](#field-masks)
6. [Mode B — connected business](#mode-b--connected-business)
   - [Google Cloud setup](#google-cloud-setup-mode-b)
   - [Connecting a Google Business Profile](#connecting-a-google-business-profile)
   - [Choosing locations](#choosing-locations)
   - [Manual sync](#manual-sync)
   - [Automatic sync with Pub/Sub](#automatic-sync-with-pubsub)
   - [Webhook setup](#webhook-setup)
   - [Queue setup](#queue-setup)
   - [Multi-location and multi-account](#multi-location-and-multi-account)
7. [Scaffolding components](#scaffolding-components)
8. [Reading reviews back](#reading-reviews-back)
9. [Database](#database)
10. [Caching](#caching)
11. [Events](#events)
12. [Error handling](#error-handling)
13. [Security](#security)
14. [Google's review limitations](#googles-review-limitations)
15. [Google attribution requirements](#google-attribution-requirements)
16. [Google billing](#google-billing)
17. [Troubleshooting](#troubleshooting)
18. [Testing](#testing)
19. [Contributing](#contributing)
20. [License](#license)

---

## Features

- **Places API (New)** — text search and place details, current endpoints only.
- **Two independent modes** — use the public API on its own, or connect a
  Google Business Profile. Mode A never asks you to configure OAuth.
- **Full OAuth 2.0 flow** — authorization URL, CSRF-protected callback,
  encrypted token storage, automatic refresh, revocation handling.
- **Real-time review sync** — Google Business Profile notifications arrive on
  your Pub/Sub topic, hit a route in your app, and queue a job.
- **Idempotent by construction** — Pub/Sub delivers at least once; duplicate
  notifications can never create a duplicate review row.
- **Authenticated webhook** — Pub/Sub OIDC tokens verified against Google's
  published signing keys, with no extra dependencies.
- **Typed DTOs** — `Place`, `Review`, `Photo`, `BusinessAccount`, `Location`,
  all null-safe against Google's sparse responses.
- **Field masks** — required by the Places API and directly tied to your bill;
  configurable and documented.
- **Caching** — with targeted invalidation when a review changes.
- **Artisan tooling** — install, scaffold, setup, diagnose, sync, prune.
- **Starter-kit agnostic** — no views, no assets, no frontend dependencies.
  Blade, Livewire and Inertia (React/Vue/Svelte) all work; the Inertia redirect
  protocol is handled for you.
- **180 tests**, no network access required to run them.

---

## Requirements

| | |
|---|---|
| PHP | 8.2 or newer |
| Laravel | 12.x or 13.x |
| Extensions | `json`, `openssl` |
| Mode B extras | A database, a queue worker, and a Google Cloud project with approved Business Profile API access |

Laravel 13 itself requires PHP 8.3, so the PHP 8.2 floor applies to Laravel 12
installations. Both combinations are covered by CI.

---

## Installation

```bash
composer require khadikul/laravel-google-places
```

The service provider and the `GooglePlaces` facade are auto-discovered; there is
nothing to register.

```bash
php artisan google-places:install
```

That publishes `config/google-places.php`, publishes and optionally runs the
migrations, and prints the setup checklist.

At any point, check your configuration:

```bash
php artisan google-places:test
php artisan google-places:test "Torlyx Security"   # makes one real API call
```

---

## Artisan commands

| Command | What it does |
|---|---|
| `google-places:install` | Publishes the config and migrations, and prints the credentials checklist. Run this first. |
| `google-places:test` | Checks your configuration and reports what is missing. Pass a search term to make one real API call and prove the key works. Exits non-zero when a required setting is absent, so it is usable in CI or a deploy script. |
| `google-places:scaffold` | Publishes Tailwind UI components for your frontend stack. See [Scaffolding components](#scaffolding-components). |
| `google-places:setup` | Prints the `gcloud` commands for your own Pub/Sub topic, and with `--subscribe` registers the notification setting with Google. |
| `google-places:sync-reviews` | Synchronises reviews for every connected location, or one named location. Queued by default; `--sync` runs it inline. |
| `google-places:prune-notifications` | Deletes Pub/Sub delivery receipts older than the retention window, so the deduplication ledger does not grow forever. |

Useful flags:

```bash
php artisan google-places:test "Torlyx Security"      # one live API call
php artisan google-places:test --no-api               # configuration only
php artisan google-places:scaffold --dry-run          # list files, write none
php artisan google-places:sync-reviews --sync         # inline, for debugging
php artisan google-places:prune-notifications --days=14
```

Two of these are worth scheduling in Mode B:

```php
// routes/console.php
Schedule::command('google-places:sync-reviews')->dailyAt('03:00');
Schedule::command('google-places:prune-notifications')->weekly();
```

The daily sync is a safety net. Pub/Sub delivery is reliable but not guaranteed
forever, and one reconciliation per location per day costs very little.

---

## Mode A — public places

Everything here needs one thing: your own API key.

### Google Cloud setup (Mode A)

1. Create a project at [console.cloud.google.com](https://console.cloud.google.com).
2. Enable billing on it. The Places API has a free tier but requires a billing
   account to be attached.
3. Enable **Places API (New)**. Note the "(New)" — the old Places API is a
   separate, deprecated product and this package does not use it.
4. Create an API key under *APIs & Services → Credentials*.
5. Restrict the key: *API restrictions* → Places API (New). For a key used only
   from your server, also add an IP restriction.

```env
GOOGLE_PLACES_API_KEY=your-own-key
```

The key is sent in the `X-Goog-Api-Key` header, never as a query parameter, so
it does not end up in access logs or referrer headers.

### Search a business

```php
use Khadikul\GooglePlaces\Facades\GooglePlaces;

$results = GooglePlaces::search('Torlyx Security');
```

Bias the results towards a point:

```php
$results = GooglePlaces::search(
    query: 'Torlyx Security',
    latitude: 23.8103,
    longitude: 90.4125,
);
```

Full signature:

```php
GooglePlaces::search(
    string $query,
    ?float $latitude = null,
    ?float $longitude = null,
    ?float $radius = null,        // metres, 0–50000, default 5000
    ?int $limit = null,           // 1–20, Google's cap
    ?array $fields = null,        // overrides the configured field mask
    ?string $languageCode = null,
    ?string $regionCode = null,
);
```

You get a `Collection` of `Place` objects.

### Select a business

```php
$place = GooglePlaces::place($placeId);

$place->id;
$place->name;
$place->address;
$place->rating;
$place->reviewCount;
$place->googleMapsUri;
$place->reviews;
```

Or the method form, if that reads better in Blade:

```php
$place->name();
$place->rating();
$place->reviewCount();
$place->reviews();
$place->mapsUrl();
```

Also available: `websiteUri`, `phoneNumber`, `internationalPhoneNumber`,
`latitude`, `longitude`, `businessStatus`, `primaryType`, `photos`, and `raw`
(the untouched Google payload).

Every property except `id` is nullable, because Google returns exactly the
fields you asked for in the field mask and nothing else.

### Display reviews

```php
foreach ($place->reviews() as $review) {
    echo $review->authorName;
    echo $review->rating;
    echo $review->text;
    echo $review->publishedAt?->format('j M Y');
    echo $review->relativePublishTime;   // "2 months ago"
}
```

A `Review` exposes: `reviewName`, `authorName`, `authorUri`, `authorPhotoUri`,
`rating`, `text`, `languageCode`, `publishedAt`, `updatedAt`,
`relativePublishTime`, `replyText`, `repliedAt`, `googleMapsUri`, `source`,
`placeId`, `locationName`, `raw`.

Anything the reviewer did not supply is `null` — anonymous reviewers have no
name or photo, and a star-only review has no text:

```blade
@foreach ($place->reviews() as $review)
    <article>
        <strong>{{ $review->authorName ?? 'A Google user' }}</strong>
        <span>{{ $review->rating }}/5</span>

        @if ($review->hasText())
            <p>{{ $review->text }}</p>
        @endif

        <time>{{ $review->relativePublishTime }}</time>
    </article>
@endforeach
```

### Photos

Google does not return image URLs with a place, only references. Resolve one
server-side so your API key never reaches the browser:

```php
$photo = $place->photos()->first();

$url = GooglePlaces::photoUrl($photo, maxWidthPx: 800);
```

Google's terms require the photo's author attributions to be shown:

```blade
<img src="{{ $url }}" alt="">
<small>Photo by {{ implode(', ', $photo->attributionNames()) }}</small>
```

### Field masks

The Places API **requires** a field mask and rejects any request without one.
It also prices each request by the most expensive field you asked for, so the
mask is a billing decision, not just a performance one.

Per call:

```php
GooglePlaces::place($placeId, fields: [
    'displayName',
    'rating',
    'userRatingCount',
    'reviews',
    'googleMapsUri',
]);
```

Or globally, in `config/google-places.php` under `fields.search` and
`fields.details`. The `places.` prefix required for search responses is added
for you.

See [Google billing](#google-billing) for what each field costs.

---

## Mode B — connected business

Mode A shows you what Google shows the public. Mode B gives the owner of a
business their complete review history, kept up to date automatically.

You need this mode only if you own or manage the business. If you just want to
display a rating and a few reviews, stop at Mode A — none of the setup below
applies to you.

### Google Cloud setup (Mode B)

1. **Request Business Profile API access.** Google gates these APIs. Submit the
   [access request form](https://developers.google.com/my-business/content/prereqs).
   Approval typically requires a verified profile that has been active for 60+
   days, and takes days to weeks. Your quota shows 0 QPM until it is approved.

2. **Enable four APIs** in your project. Google split this surface across
   several hosts, and reviews were never migrated off the legacy one:

   | API | Used for |
   |---|---|
   | My Business Account Management API | listing accounts |
   | My Business Business Information API | listing locations |
   | My Business Notifications API | subscribing to notifications |
   | Google My Business API (legacy v4) | **reviews** |

   The last one is not optional. As of this writing Google has not migrated
   reviews off v4, so a project without it enabled can list locations but cannot
   read a single review.

3. **Create an OAuth 2.0 client** (*Credentials → Create credentials → OAuth
   client ID → Web application*) and add your callback as an authorised redirect
   URI:

   ```text
   https://your-app.example.com/google-places/oauth/callback
   ```

4. **Configure the consent screen** with the
   `https://www.googleapis.com/auth/business.manage` scope.

```env
GOOGLE_PLACES_API_KEY=...
GOOGLE_PLACES_CLIENT_ID=...
GOOGLE_PLACES_CLIENT_SECRET=...
GOOGLE_PLACES_REDIRECT_URI=https://your-app.example.com/google-places/oauth/callback
```

The OAuth routes are only registered once `GOOGLE_PLACES_CLIENT_ID` is set, so a
Mode A application has no OAuth surface at all.

### Connecting a Google Business Profile

Two routes are registered for you:

| Method | URI | Name |
|---|---|---|
| GET | `/google-places/oauth/redirect` | `google-places.oauth.redirect` |
| GET | `/google-places/oauth/callback` | `google-places.oauth.callback` |

```blade
<a href="{{ route('google-places.oauth.redirect') }}">Connect Google Business</a>
```

#### Starter kits

The package ships no views, no assets and no frontend dependencies, so the API
is identical under Blade, Livewire, Inertia (React, Vue or Svelte) or a headless
backend. Only the connect link needs care, because it leaves your site for
accounts.google.com and an XHR cannot follow a cross-origin redirect.

If you would rather not build the UI yourself, publish a set of Tailwind
components for your stack:

```bash
php artisan google-places:scaffold
```

See [Scaffolding components](#scaffolding-components) for what it publishes.

**Blade** — a plain anchor, as above.

**Livewire** — a plain anchor too. Do **not** put `wire:navigate` on it: that
turns the click into a fetch, which cannot follow the redirect to Google.

```blade
<a href="{{ route('google-places.oauth.redirect') }}">Connect Google Business</a>
```

To start the flow from a component method, redirect away rather than rendering:

```php
public function connect()
{
    return redirect()->away(GooglePlaces::oauth()->authorizationUrl());
}
```

**Inertia** — handled for you. The redirect route answers an Inertia request
with `409` and `X-Inertia-Location`, which is Inertia's protocol for leaving the
app, so an ordinary `<Link>` works:

```jsx
import { Link } from '@inertiajs/react'

<Link href={route('google-places.oauth.redirect')}>Connect Google Business</Link>
```

A plain `<a href>` works in Inertia as well, and is the simplest option.

**Reading the result.** The callback flashes `google_places_status`
(`connected` or `failed`) and `google_places_error` to the session. Blade and
Livewire read these with `session('google_places_status')`. For Inertia, share
them in `HandleInertiaRequests`:

```php
public function share(Request $request): array
{
    return array_merge(parent::share($request), [
        'flash' => [
            'googleStatus' => fn () => $request->session()->get('google_places_status'),
            'googleError' => fn () => $request->session()->get('google_places_error'),
        ],
    ]);
}
```

**Headless / SPA on another domain.** The OAuth state is held in the session, so
the two OAuth routes need a session. Keep them on the `web` middleware group
(the default) and open them in a normal browser window rather than through your
API client. Everything else — search, place details, reviews, sync — is
stateless and works from any stack.

> **Protect these routes.** Authorising a business profile is an administrative
> action. Add your own auth middleware:
>
> ```php
> 'oauth' => [
>     'routes' => [
>         'middleware' => ['web', 'auth', 'can:manage-google-business'],
>     ],
> ],
> ```

After the callback the user lands on `oauth.routes.success_redirect` with
`google_places_status` flashed to the session (`connected` or `failed`).

Prefer to drive it yourself?

```php
$url = GooglePlaces::oauth()->authorizationUrl();
```

What happens behind that:

```text
User clicks Connect
   ↓
accounts.google.com  (with a random CSRF state stored in the session)
   ↓
User grants permission
   ↓
GET /google-places/oauth/callback?code=...&state=...
   ↓
State verified with hash_equals, then consumed so it cannot be replayed
   ↓
Code exchanged for tokens, directly with Google
   ↓
Tokens encrypted with your APP_KEY, written to your database
```

`access_type=offline` and `prompt=consent` are both sent, because Google only
returns a refresh token when both are present — without them unattended sync
stops working an hour after connecting.

### Choosing locations

```php
$accounts = GooglePlaces::businessAccounts();

foreach ($accounts as $account) {
    $locations = GooglePlaces::locations($account->id());
}

GooglePlaces::connectLocation('locations/222', accountId: 'accounts/111');
```

A word on Google's naming, because it causes real confusion: the Business
Information API calls a location `locations/222`, while the Reviews API insists
on `accounts/111/locations/222`. The `Location` DTO keeps both — `name` and
`resourceName()` — and the package stores the qualified form, which is what
every downstream call and every notification uses.

Connecting also reads `metadata.placeId` from the location, which is the bridge
between a Business Profile location and its public Places entry. That is what
lets `GooglePlaces::reviews($placeId)` return owner reviews later.

```php
GooglePlaces::connectedLocations();
GooglePlaces::disconnectLocation('accounts/111/locations/222'); // keeps the reviews
```

### Manual sync

```php
GooglePlaces::syncReviews('accounts/111/locations/222');              // queued
GooglePlaces::syncReviews('accounts/111/locations/222', queue: false); // inline
```

```bash
php artisan google-places:sync-reviews                              # every connected location
php artisan google-places:sync-reviews accounts/111/locations/222
php artisan google-places:sync-reviews --sync                       # inline, for debugging
```

Even with notifications running, schedule this as a safety net:

```php
// routes/console.php
Schedule::command('google-places:sync-reviews')->dailyAt('03:00');
Schedule::command('google-places:prune-notifications')->weekly();
```

### Automatic sync with Pub/Sub

```text
Google Business Profile
        │ NEW_REVIEW / UPDATED_REVIEW
        ▼
Your Pub/Sub topic
        │ push subscription
        ▼
POST https://your-site.com/google-places/webhook
        │ OIDC token verified
        │ message ID claimed (unique index)
        ▼
SyncGoogleReview queued ──► review fetched ──► row upserted ──► cache invalidated
```

This package cannot create Google Cloud resources for you — that would require
project-admin credentials it has no business holding. It prints the commands and
makes the one API call it legitimately can:

```bash
php artisan google-places:setup
```

Which walks you through:

```bash
# 1. Create the topic in YOUR project
gcloud pubsub topics create gbp-notifications

# 2. Let Google publish to it. Miss this and Google silently sends nothing.
gcloud pubsub topics add-iam-policy-binding gbp-notifications \
  --member="serviceAccount:mybusiness-api-pubsub@system.gserviceaccount.com" \
  --role="roles/pubsub.publisher"

# 3. Create the push subscription pointing at YOUR app
gcloud pubsub subscriptions create gbp-notifications-push \
  --topic=gbp-notifications \
  --push-endpoint="https://your-site.com/google-places/webhook" \
  --push-auth-service-account="pusher@your-project.iam.gserviceaccount.com" \
  --push-auth-token-audience="https://your-site.com/google-places/webhook"
```

Then:

```env
GOOGLE_PLACES_NOTIFICATIONS_ENABLED=true
GOOGLE_PLACES_PUBSUB_TOPIC=projects/your-project/topics/gbp-notifications
GOOGLE_PLACES_WEBHOOK_AUDIENCE=https://your-site.com/google-places/webhook
GOOGLE_PLACES_WEBHOOK_SERVICE_ACCOUNT=pusher@your-project.iam.gserviceaccount.com
```

Finally, tell Google which topic to publish to:

```php
GooglePlaces::notifications()->subscribe('accounts/111');
```

```bash
php artisan google-places:setup --subscribe --account=accounts/111
```

Check and undo:

```php
GooglePlaces::notifications()->setting('accounts/111');
GooglePlaces::notifications()->unsubscribe('accounts/111');
```

Supported types are `NEW_REVIEW` and `UPDATED_REVIEW` by default. The service
validates against Google's current list and rejects anything else rather than
silently sending an invalid request.

### Webhook setup

```php
'notifications' => [
    'enabled' => true,
    'route' => 'google-places/webhook',
    'middleware' => ['api'],
],
```

The route is only registered when `enabled` is true.

**Authentication.** Two schemes, both configurable, and at least one should be
on in production:

- **OIDC (recommended, on by default).** Pub/Sub signs every push with a Google
  token. It is verified for real: RS256 only, signature checked against Google's
  published JWK set, plus `iss`, `aud`, `exp`, `iat`, `email` and
  `email_verified`. Tokens signed `none` or `HS256` are rejected outright, which
  closes the algorithm-confusion attack by construction.
- **Shared token.** A secret in the query string
  (`?token=...`), compared with `hash_equals`. Simpler, weaker.

```env
GOOGLE_PLACES_WEBHOOK_OIDC_ENABLED=true
GOOGLE_PLACES_WEBHOOK_AUDIENCE=https://your-site.com/google-places/webhook
GOOGLE_PLACES_WEBHOOK_SERVICE_ACCOUNT=pusher@your-project.iam.gserviceaccount.com
```

**What the controller does, and deliberately does not do.** It validates, records
the message ID, dispatches a job and returns. No Google API call is ever made
synchronously inside the request.

Status codes matter here, because Pub/Sub retries anything that is not a 2xx:

| Situation | Response | Why |
|---|---|---|
| Review notification | `200 {"status":"queued"}` | job dispatched |
| Same message again | `200 {"status":"duplicate"}` | already claimed |
| Other notification type | `200 {"status":"ignored"}` | not ours to handle |
| Malformed payload | `200 {"status":"invalid_payload"}` | broken forever; retrying is pointless |
| Bad or missing auth | `401 Unauthorized` | body says nothing more |

**Idempotency.** Pub/Sub guarantees at-least-once delivery, so the same message
*will* arrive twice eventually. Two independent guards handle it:

1. `google_notification_receipts.message_id` carries a unique index. The insert
   itself arbitrates, so two workers racing on the same redelivery cannot both
   dispatch.
2. The review write upserts on the Google resource name, which is globally
   unique. Even if a job somehow ran twice, there is still one row.

On top of that `SyncGoogleReview` implements `ShouldBeUnique`.

### Queue setup

Real-time sync needs a worker running. Without one, notifications are received
and queued and nothing else happens.

```bash
php artisan queue:work
```

```env
GOOGLE_PLACES_QUEUE_CONNECTION=redis
GOOGLE_PLACES_QUEUE=google-places
GOOGLE_PLACES_QUEUE_TRIES=5
GOOGLE_PLACES_QUEUE_TIMEOUT=60
```

Retry behaviour is chosen per failure, not blanket: timeouts, 429s and 5xx are
retried with backoff `[10, 30, 120, 300]`; a deleted review, a disconnected
location or a revoked token fails immediately, because no number of retries
fixes any of those.

`ShouldBeUnique` needs a cache lock, so use `redis`, `memcached`, `database` or
`dynamodb` as your cache driver in production — not `array` or `file`.

### Multi-location and multi-account

All supported. `GoogleBusinessConnection` has a nullable `owner` morph so you
can attach a connection to a user, team or tenant:

```php
$connection->owner()->associate($team);
```

`GooglePlaces::oauth()->connection($id)` selects one explicitly; most methods
take a `$connection` argument. Without one, the most recent active connection is
used.

---

## Scaffolding components

Publishing a starter UI is optional. The package itself still registers no
views: `google-places:scaffold` copies **stubs** into your application, exactly
as Breeze does. Once published the files are yours, and the package never reads,
overrides or updates them again.

```bash
php artisan google-places:scaffold                 # detects your stack
php artisan google-places:scaffold --stack=vue     # or name it
php artisan google-places:scaffold --dry-run       # list without writing
php artisan google-places:scaffold --force         # overwrite existing files
```

### Detection

| Stack | Detected from |
|---|---|
| `react` / `vue` / `svelte` | `@inertiajs/react`, `@inertiajs/vue3` or `@inertiajs/svelte` in `package.json` |
| `livewire` | `livewire/livewire` in `composer.json` |
| `blade` | the fallback |

Inertia wins when an application has both installed, because the components then
have to be JavaScript. Detection is reported before anything is written, along
with whether Tailwind was found.

### What you get

| Component | Purpose |
|---|---|
| `Rating` | Star rating. Renders "No rating yet" rather than zero stars when Google returns none. |
| `ReviewCard` | One review, with the attribution Google requires. Handles anonymous reviewers and star-only reviews. |
| `ReviewsWidget` | The public-facing block for a marketing page. Reads your database first, falls back to the API, and keeps the page up if Google is down. |
| `PlaceCard` | A search result. Every field is null-safe against narrow field masks. |
| `ConnectGoogleButton` | The OAuth entry point, with the correct link handling for the stack. |
| `LocationManager` | The admin screen: accounts, locations, connect and sync. |

Plus ready-made Search, Show and Admin pages, and a `GooglePlacesPageController`
with the routes to register written in its docblock.

Livewire additionally gets `BusinessSearch`, `ReviewList` and `LocationManager`
components, and reuses the Blade presentational components rather than
duplicating the markup.

The markup is Tailwind, matching Laravel's own starter kits. Without Tailwind the
components render unstyled — the command warns you if it cannot find it.

### Attribution is built in

The published components already do what Google's terms require: they show the
reviewer's name and photo as given, never modify review text, credit photo
authors, and link back to Google. Keep those parts if you restyle.


---

## Reading reviews back

Once reviews are synchronised, read them from your own database. This is the
fast path for rendering a page — no API call, no latency, no billing.

```php
$reviews = GooglePlaces::reviews($placeId);

$reviews = GooglePlaces::reviews(placeId: $placeId, limit: 20);
```

Accepts a place ID or a location resource name, so it works in both modes:

```php
GooglePlaces::reviews('accounts/111/locations/222');
```

Filter and order:

```php
use Khadikul\GooglePlaces\Support\ReviewSource;

GooglePlaces::reviews($placeId, source: ReviewSource::BusinessProfile);
GooglePlaces::reviews($placeId, orderBy: 'rating', direction: 'desc');
```

You get `GoogleReview` Eloquent models, so query them however you like:

```php
use Khadikul\GooglePlaces\Models\GoogleReview;

GoogleReview::forPlace($placeId)->where('rating', '>=', 4)->paginate(10);
```

Persist a public place and its reviews:

```php
GooglePlaces::store($placeId);
```

---

## Database

Five optional tables. Mode A works with none of them.

| Table | Holds |
|---|---|
| `google_places` | cached place snapshots |
| `google_reviews` | reviews, from either source |
| `google_business_connections` | OAuth connections, tokens encrypted |
| `google_business_locations` | locations selected for sync |
| `google_notification_receipts` | delivered Pub/Sub message IDs |

Rename them, or move them to another connection:

```php
'database' => [
    'connection' => env('GOOGLE_PLACES_DB_CONNECTION'),
    'migrations' => true,
    'tables' => [
        'reviews' => 'my_google_reviews',
    ],
],
```

Set `migrations` to `false` to publish and edit them yourself.

**On the review unique key.** `google_reviews.review_name` holds the Google
resource name and carries a unique index:

```text
Places API        places/{place}/reviews/{review}
Business Profile  accounts/{account}/locations/{location}/reviews/{review}
```

Both forms are globally unique, which makes this a stricter guarantee than
`unique(place_id, review_name)` — and unlike that composite, it still holds when
`place_id` is unknown, as it is for a location Google has not matched to a place
ID. For the rare payload with no usable identifier, a deterministic hash of the
place, author and publish time is used instead, so repeated syncs still collapse
onto one row.

---

## Caching

```php
'cache' => [
    'enabled' => true,
    'ttl' => 3600,
    'store' => null,
    'prefix' => 'google-places',
],
```

Cache keys include a fingerprint of the field mask, so a narrow request never
serves a cached response to a wider one.

Invalidation is automatic. When a review is synchronised, the cached place is
dropped:

```text
NEW_REVIEW → database updated → cache invalidated → next request is fresh
```

Manually:

```php
GooglePlaces::forget($placeId);
```

This uses a per-place version counter rather than cache tags, so it works on
every driver including `file` and `array`, and it drops that place's photo URLs
too.

---

## Events

```php
use Khadikul\GooglePlaces\Events\GoogleBusinessConnected;
use Khadikul\GooglePlaces\Events\GoogleConnectionRevoked;
use Khadikul\GooglePlaces\Events\GoogleReviewSynced;
```

| Event | Fired when |
|---|---|
| `GoogleBusinessConnected` | OAuth completed and a connection stored |
| `GoogleConnectionRevoked` | Google rejected the refresh token, or you disconnected |
| `GoogleReviewSynced` | a review was written locally |

`GoogleReviewSynced` carries `$wasRecentlyCreated`, which distinguishes a
genuinely new review from an edit or a redelivery — useful for "notify me on new
reviews" without spamming on every resync:

```php
Event::listen(function (GoogleReviewSynced $event) {
    if ($event->wasRecentlyCreated && $event->review->rating <= 2) {
        // alert someone
    }
});
```

`GoogleConnectionRevoked` is your cue to prompt the user to reconnect.

---

## Error handling

```text
GooglePlacesException                 base
├── ApiException                      transport and unmapped HTTP
│   ├── AuthenticationException       401
│   ├── AuthorizationException        403
│   ├── NotFoundException             404
│   ├── ConflictException             409
│   └── RateLimitException            429
├── InvalidConfigurationException     missing or malformed config
├── TokenExpiredException             reconnection required
├── ConnectionNotFoundException       no usable connection
└── WebhookException                  untrusted or unreadable push
```

```php
try {
    $place = GooglePlaces::place($placeId);
} catch (RateLimitException $e) {
    $e->retryAfter();
} catch (AuthorizationException $e) {
    // API not enabled, or key restrictions block this request
} catch (ApiException $e) {
    $e->status();
    $e->reason();       // Google's status, e.g. PERMISSION_DENIED
    $e->isRetryable();
}
```

Handled: 400, 401, 403, 404, 409, 429, 500, 502, 503, 504, connection timeouts,
DNS and TLS failures, invalid JSON, empty bodies, missing API key, invalid and
expired OAuth tokens, revoked grants, invalid notifications and duplicates.

**No exception message ever contains a credential.** There is a test for it.

---

## Security

What the package does:

- **Encrypts tokens at rest** with Laravel's `encrypted` cast, using your
  `APP_KEY`. Verified by a test that reads the raw database row.
- **Hides tokens from serialisation** — `$hidden` plus an overridden `toArray()`,
  so a token cannot leak through a JSON response, a log line or an exception.
- **Protects OAuth state** with a random 40-character token, compared using
  `hash_equals` and consumed on use so it cannot be replayed.
- **Verifies webhook callers** cryptographically before parsing anything.
- **Sends the API key as a header**, never a query parameter.
- **Resolves photo URLs server-side** so your key never reaches a browser.
- **Never logs** keys, tokens, headers or request bodies. Webhook rejections log
  only a reason and the caller IP.
- **Fails closed** — a webhook auth scheme that is enabled but misconfigured
  rejects the request rather than waving it through.

What you should do:

- Restrict your API key to Places API (New) in the Google console.
- Put auth middleware on the OAuth routes.
- Keep OIDC verification on, and set the audience.
- Serve the webhook over HTTPS. Pub/Sub requires it.
- Rotate `GOOGLE_PLACES_CLIENT_SECRET` if it is ever exposed, and re-run the
  OAuth flow.

Found a vulnerability? Email the maintainer rather than opening a public issue.

---

## Google's review limitations

Be honest with your users about what Google actually provides. The two sources
are not equivalent:

| | Places API (public) | Business Profile (connected) |
|---|---|---|
| Reviews returned | **At most 5**, chosen by Google | All of them |
| Pagination | None | Yes, 50 per page |
| Requires OAuth | No | Yes |
| Requires API approval | No | Yes, and it takes time |
| Ownership required | No | Yes |
| Owner replies | No | Yes |
| Real-time updates | No | Yes, with Pub/Sub configured |

Specifically:

- **The public API returns at most five reviews.** That is a documented hard
  limit with no pagination parameter, and it has not moved in years. A business
  with 200 reviews still returns five. If you need all of them you need Mode B,
  which means you need to own or manage the business.
- **"Real-time" means real-time only once Pub/Sub is configured.** Without a
  topic, an IAM binding, a push subscription and a running queue worker, nothing
  is automatic. Use `google-places:sync-reviews` on a schedule instead.
- **Notification delivery is at-least-once, not exactly-once and not
  guaranteed-forever.** Keep the scheduled reconciliation even when
  notifications work.
- **Business Profile API access is gated and quota-limited.** Until Google
  approves your project the quota is 0 QPM and every call fails.
- **Reviews still live on the legacy v4 host.** If Google migrates them, that
  endpoint is a single config value in this package.

The `source` column and `ReviewSource` enum keep the two apart, so you can label
them correctly in your UI.

---

## Google attribution requirements

Displaying Google data comes with obligations under the
[Google Maps Platform Terms](https://cloud.google.com/maps-platform/terms).
At minimum:

- Attribute reviewers by name and, where present, link `authorUri` to their
  Google profile.
- Show `authorPhotoUri` where present.
- Show the Google attribution on photos via `attributionNames()`.
- Do not modify review text. Show it as written, or not at all.
- Link back to Google using `googleMapsUri` where you display a rating.
- Do not cache Places content longer than Google's terms allow. The 30-day cap
  on most content is why the default TTL is one hour, not one week — but read
  the terms and decide for your own case.

```blade
<a href="{{ $place->mapsUrl() }}" rel="noopener" target="_blank">
    {{ $place->rating() }} ★ from {{ $place->reviewCount() }} Google reviews
</a>
```

This package uses only official Google APIs. It does not scrape Google Maps or
Google Search, and it will not be extended to. Scraping violates Google's terms
and will get your project banned.

---

## Google billing

The Places API bills per request, by SKU, based on the most expensive field in
your mask. Check
[current pricing](https://developers.google.com/maps/documentation/places/web-service/usage-and-billing)
before you go live — the tiers below are the shape of it, not the price:

| Tier | Includes |
|---|---|
| Essentials | `id`, `formattedAddress`, `location`, `photos`, `googleMapsUri` |
| Pro | `displayName`, `primaryType`, `businessStatus` |
| Enterprise | `rating`, `userRatingCount`, `websiteUri`, phone numbers |
| Enterprise + Atmosphere | **`reviews`**, `reviewSummary` |

Practical consequences:

- **`reviews` is the expensive one.** The default detail mask includes it
  because reviews are the point of the package — but if you only need a rating
  badge, drop it and your bill falls a tier.
- **Keep caching on.** A cached response costs nothing. That is why
  `GOOGLE_PLACES_CACHE_ENABLED` defaults to true.
- **In Mode B, read from your database.** `GooglePlaces::reviews()` hits no API
  and costs nothing. Only the sync itself calls Google, and Business Profile
  calls are quota-limited rather than billed per request.
- **Set a budget alert** in Google Cloud. Please.

```php
// A cheap rating badge.
GooglePlaces::place($placeId, fields: ['id', 'displayName', 'rating', 'userRatingCount']);
```

---

## Troubleshooting

**`InvalidConfigurationException: No Google API key configured`**
`GOOGLE_PLACES_API_KEY` is unset. Run `php artisan config:clear` if you just
added it.

**403 `PERMISSION_DENIED` on a Places call**
The Places API (New) is not enabled on the project, or the key is restricted to
a different API, or an IP/referrer restriction excludes your server. The
"(New)" matters — enabling the old Places API does not enable this one.

**403 on Business Profile calls, quota shows 0 QPM**
Your Business Profile API access has not been approved yet. Nothing in code
fixes this; wait for Google.

**Locations list, but reviews 404 or 403**
The legacy *Google My Business API* is not enabled. Reviews live on `v4` and
that is a separate API to enable.

**`TokenExpiredException` / `GoogleConnectionRevoked`**
The refresh token is dead — the user revoked access, changed their password, or
it aged out. Only reconnecting fixes it. Listen for the event and prompt them.

**OAuth callback redirects with `google_places_error=invalid_state`**
The session was lost between redirect and callback. Check the routes are on the
`web` middleware group, that `SESSION_DOMAIN` is right, and that you are not
bouncing between `www` and apex.

**No refresh token stored**
Google only returns one with `access_type=offline` and `prompt=consent`, which
this package always sends. If you see this, the user likely authorised through a
different flow. Disconnect and reconnect.

**Webhook returns 401**
Compare `GOOGLE_PLACES_WEBHOOK_AUDIENCE` with the
`--push-auth-token-audience` on the subscription; they must match exactly.
Same for `GOOGLE_PLACES_WEBHOOK_SERVICE_ACCOUNT` and
`--push-auth-service-account`. To isolate the problem, set both to empty and the
token will be checked for signature and issuer only.

**Webhook returns 200 but nothing syncs**
Is a queue worker running? Check `failed_jobs`. Check
`google_notification_receipts` — a row with `processed_at` null means the job was
queued but never completed.

**Google sends no notifications at all**
Almost always the missing IAM binding. Confirm
`mybusiness-api-pubsub@system.gserviceaccount.com` has `roles/pubsub.publisher`
on the topic, then confirm with
`GooglePlaces::notifications()->setting('accounts/111')` that Google actually
stored your topic.

**Duplicate reviews in the database**
Should be impossible. If you see it, check the unique index on
`google_reviews.review_name` survived your migration.

**`ShouldBeUnique` jobs not de-duplicating**
Your cache driver is `array` or `file`. Use `redis`, `memcached`, `database` or
`dynamodb`.

---

## Testing

```bash
composer test
```

180 tests, 1000+ assertions. **No test ever contacts Google** — everything runs
through `Http::fake()` and `Queue::fake()` against an in-memory SQLite database.

Coverage includes: search success, empty results and every mapped error status;
place details, field masks and sparse responses; review mapping from both
sources, including anonymous reviewers and star-only reviews; the OAuth flow
including state forgery, replay, refresh and revocation; encryption at rest
verified against the raw database row; Pub/Sub OIDC verification against a real
signature, with `none` and `HS256` tokens rejected; duplicate notification
handling; cache hit, miss and invalidation; and a scan that fails the build if
any non-Google host appears in the shipped source.

```bash
composer lint     # check formatting
composer format   # fix it
```

CI runs on PHP 8.2, 8.3 and 8.4 across Laravel 12 and 13, on Ubuntu and
Windows, with both `prefer-lowest` and `prefer-stable`.

---

## Contributing

Pull requests are welcome. Please add tests, run `composer test` and
`composer lint` before opening one.

One rule is not negotiable: **no contribution may introduce a dependency on
infrastructure or credentials owned by the package author or anyone else.**
Every Google credential and every cloud resource belongs to the developer
installing this package.

---

## License

MIT. See [LICENSE.md](LICENSE.md).

Google, Google Maps, Google Places and Google Business Profile are trademarks of
Google LLC. This package is not affiliated with or endorsed by Google.
