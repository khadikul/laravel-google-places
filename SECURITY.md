# Security Policy

## Supported versions

| Version | Supported |
|---------|-----------|
| 1.x     | Yes       |

## Reporting a vulnerability

Please report security issues privately rather than opening a public issue.

Email **khadikul@bitekservices.com** with:

- a description of the issue and its impact,
- the steps to reproduce it,
- the package and Laravel versions you tested against.

You can expect an acknowledgement within a few days and, for a confirmed issue,
a fix or a mitigation plan before any public disclosure.

## Scope

This package handles credentials and a public webhook endpoint, so the following
are always in scope:

- Anything that could expose a Google API key, OAuth client secret, access token
  or refresh token — in a log, an exception message, a serialised model, an HTTP
  response or a rendered page.
- Any way to bypass Pub/Sub webhook authentication, or to make the webhook act
  on an unauthenticated request.
- Any way to bypass or replay the OAuth state check.
- Any way to make the package contact a host other than Google's documented API
  endpoints.
- Any way to create duplicate or corrupted review records through replayed or
  crafted notifications.

## Out of scope

- Vulnerabilities in your own Google Cloud configuration, such as an unrestricted
  API key or an over-permissive IAM policy. The package documents the correct
  setup but cannot enforce it on your project.
- Missing authentication on the OAuth routes in an installing application. Those
  routes ship with the `web` middleware group only; adding your own authorisation
  middleware is documented in the README and is the installer's responsibility.

## A note on the threat model

The package author operates no infrastructure and holds no credentials belonging
to any user of this package. There is no server to compromise and no central
store of tokens. Every credential lives in the installing application's own
environment and database.
