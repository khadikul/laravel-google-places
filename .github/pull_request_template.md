## What this changes

<!-- One or two sentences. What behaviour is different after this PR? -->

## Why

<!-- What problem does it solve? Link an issue if there is one. -->

## How it was verified

<!-- Not "it works" — say what you ran and what you saw. -->

- [ ] `composer test` passes
- [ ] `composer lint` passes
- [ ] New behaviour has a test that fails without the change

---

## Required checks

These are the promises the package makes. A PR that breaks one will be closed,
so please confirm each:

- [ ] **No credentials.** No API key, OAuth client, token or secret is
  hard-coded, committed, or supplied by the package.
- [ ] **No new hosts.** Nothing contacts any server other than Google's
  documented API endpoints. `ZeroVendorInfrastructureTest` enforces this — if
  you changed that test, say why here.
- [ ] **No scraping.** Only official, documented Google APIs. No Maps or Search
  scraping, no undocumented endpoints.
- [ ] **Nothing leaks.** No credential appears in a log line, an exception
  message, a serialised model or an HTTP response.
- [ ] **Official APIs only.** If this touches a Google endpoint, link the
  current documentation for it.

## Breaking changes

<!-- Config keys renamed? Method signatures changed? Migrations altered?
     Write "None" if there are none. -->

None.
