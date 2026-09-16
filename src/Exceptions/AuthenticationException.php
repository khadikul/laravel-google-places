<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Exceptions;

/**
 * HTTP 401 — the API key or OAuth access token was rejected by Google.
 */
class AuthenticationException extends ApiException {}
