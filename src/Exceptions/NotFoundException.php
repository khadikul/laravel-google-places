<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Exceptions;

/**
 * HTTP 404 — the place, location or review does not exist (or is no longer
 * visible to the authenticated user).
 */
class NotFoundException extends ApiException {}
