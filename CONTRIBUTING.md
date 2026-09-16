# Contributing

Thanks for wanting to help. This package handles other people's Google
credentials and exposes a public webhook, so a few things are stricter than in a
typical package. They are listed plainly below rather than hidden in a review
comment after you have done the work.

## The rules that will not bend

A pull request that breaks any of these is closed, regardless of how useful the
feature is.

**1. The package ships no credentials.**
No API key, OAuth client ID or secret, access token or refresh token may be
hard-coded, committed, or supplied by the package. Every credential comes from
the installing application's own environment. Config values for credentials must
have no default:

```php
'api_key' => env('GOOGLE_PLACES_API_KEY'),          // correct
'api_key' => env('GOOGLE_PLACES_API_KEY', 'AIza…'), // rejected
```

**2. The package contacts nobody but Google.**
No proxy, no analytics, no telemetry, no vendor API, no "just this one" callback
home. `tests/Unit/ZeroVendorInfrastructureTest.php` scans the shipped source and
fails the build when any other host appears. Do not weaken that test to make a
change pass.

**3. Official Google APIs only.**
No scraping of Google Maps or Google Search. No undocumented or unofficial
endpoints. If you add or change an API call, link the current Google
documentation for it in the PR — the endpoints in this package were verified
against the live docs and drift over time.

**4. Nothing leaks.**
A credential must never reach a log line, an exception message, a serialised
model or an HTTP response. If you touch `OAuthService`,
`GoogleBusinessConnection`, the exception classes or any logging call, say in
the PR how you checked.

**5. The webhook fails closed.**
`VerifyPubSubToken` and `OpenIdTokenVerifier` guard a public endpoint. Changes
there need a test proving the new path rejects a forged request, not just that
it accepts a valid one.

## Before you open a PR

```bash
composer install
composer test      # 180 tests, none of which contact Google
composer lint      # code style
composer format    # fix style automatically
```

Tests must never make a real network call. Use `Http::fake()` and `Queue::fake()`
like the existing suite does.

New behaviour needs a test that fails without your change. "It works locally" is
not verification, and neither is a test that would pass with the feature removed.

## Things worth knowing before you change them

**Reviews come from the legacy v4 host.** Google split the Business Profile APIs
across four hosts and never migrated reviews off `mybusiness.googleapis.com/v4`.
That is not a mistake in the code.

**Idempotency has two independent guards.** A unique index on the Pub/Sub message
ID, and an upsert on the globally unique Google review resource name. Pub/Sub
delivers at least once, so removing either one will produce duplicate reviews in
production and not in your tests.

**Field masks are a billing decision.** The Places API bills by the most
expensive field requested. Widening a default mask costs every user of the
package money. Say so explicitly in the PR if you do.

**The package registers no views.** `google-places:scaffold` copies stubs into
the application. Adding `loadViewsFrom` would make the package own markup it has
no business owning.

## Reporting a security issue

Do not open a public issue. See [SECURITY.md](SECURITY.md).

## Commit messages

Explain why, not just what. A reader six months from now should understand the
reasoning without opening the diff.
