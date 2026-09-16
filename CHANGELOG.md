# Changelog

All notable changes to `khadikul/laravel-google-places` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and
this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-09-16

First release.

### Added

**Mode A — public places (API key only)**

- Text search against the Places API (New) `places:searchText`, with optional
  location bias.
- Place details via `places/{id}`, with per-call and configurable field masks.
- `Place`, `Review` and `Photo` DTOs, null-safe against sparse Google responses.
- Server-side photo URL resolution, so the API key is never exposed to browsers.
- Response caching with field-mask-aware keys and per-place invalidation.

**Mode B — connected business (OAuth)**

- Full OAuth 2.0 authorization-code flow with CSRF-protected state, executed
  entirely inside the host application.
- Encrypted token storage, automatic refresh, and revocation handling that marks
  a connection for re-authorisation on `invalid_grant`.
- Business account listing via the Account Management API.
- Location listing via the Business Information API, including the mandatory
  `readMask`, and the `metadata.placeId` bridge to the public Places entry.
- Review sync via the Business Profile v4 Reviews API, with pagination.
- Notification subscription via the Notifications API.
- Pub/Sub push webhook inside the host application, with OIDC token verification
  implemented against ext-openssl (RS256 only) and no JWT dependency.
- Idempotent notification handling: a unique index on the Pub/Sub message ID
  plus an upsert on the Google review resource name.
- `SyncGoogleReview` and `SyncLocationReviews` queue jobs with per-failure retry
  policies.

**Scaffolding**

- `google-places:scaffold` publishes Tailwind components for Blade, Livewire or
  Inertia with React, Vue or Svelte, detecting the stack from composer.json and
  package.json. These are stubs copied into the application, not package views,
  so the package still registers no view namespace.
- Published components carry the reviewer and photo attribution Google requires,
  and are null-safe against anonymous reviewers, star-only reviews and narrow
  field masks.

**Compatibility**

- Laravel 12 and Laravel 13, on PHP 8.2 or newer. Laravel 13 itself requires
  PHP 8.3, so the 8.2 floor applies to Laravel 12 installations.

**Infrastructure**

- Five optional migrations with configurable table names and connection.
- `GooglePlaces` facade over a thin manager; all behaviour in injectable services.
- `google-places:install`, `:setup`, `:test`, `:sync-reviews` and
  `:prune-notifications` Artisan commands.
- `GoogleBusinessConnected`, `GoogleConnectionRevoked` and `GoogleReviewSynced`
  events.
- Nine exception types mapped from Google's error envelope, none of which can
  contain a credential.
- 180 tests covering search, details, review mapping, OAuth, webhook
  authentication, idempotency, caching and error handling. No test contacts
  Google.
- CI on PHP 8.2, 8.3 and 8.4 across Laravel 12 and 13, Ubuntu and Windows,
  with `prefer-lowest` and `prefer-stable`.

### Security

- OAuth access and refresh tokens are encrypted with the application `APP_KEY`
  before storage, and stripped from model serialisation.
- The OAuth state token is compared with `hash_equals` and consumed on use.
- The Pub/Sub webhook rejects `none` and `HS256` tokens, verifying only RS256
  signatures against Google's published JWK set, and fails closed when an
  enabled scheme is misconfigured.
- The Places API key is sent as a header, never a query parameter.
- No credential is written to any log or exception message.

### Notes

- The package contains no credentials and depends on no infrastructure operated
  by its author. A test in the suite enforces this by scanning the shipped
  source for non-Google hosts.
- Reviews are read from Google's legacy v4 host because Google has not migrated
  them; that host is a single configuration value should it change.

[1.0.0]: https://github.com/khadikul/laravel-google-places/releases/tag/v1.0.0
