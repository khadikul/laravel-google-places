<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Exceptions;

use Throwable;

/**
 * Thrown for any Google API response that is not mapped to a more specific
 * exception, and for transport level failures (timeouts, DNS, TLS).
 */
class ApiException extends GooglePlacesException
{
    public function __construct(
        string $message,
        protected int $status = 0,
        protected ?string $reason = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * Google's machine readable status, e.g. INVALID_ARGUMENT or UNAVAILABLE.
     */
    public function reason(): ?string
    {
        return $this->reason;
    }

    /**
     * Whether retrying the same request could plausibly succeed.
     */
    public function isRetryable(): bool
    {
        return in_array($this->status, [0, 408, 429, 500, 502, 503, 504], true);
    }
}
