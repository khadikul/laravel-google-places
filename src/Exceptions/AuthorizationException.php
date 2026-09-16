<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Exceptions;

/**
 * HTTP 403 — the credential is valid but lacks permission, the API is not
 * enabled on the project, or the project has no approved Business Profile
 * quota.
 */
class AuthorizationException extends ApiException {}
