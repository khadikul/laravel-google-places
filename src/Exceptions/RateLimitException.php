<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Exceptions;

use Throwable;

/**
 * HTTP 429 — quota exhausted or requests sent too quickly.
 */
class RateLimitException extends ApiException
{
    public function __construct(
        string $message,
        int $status = 429,
        ?string $reason = null,
        protected ?int $retryAfter = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, $status, $reason, $previous);
    }

    /**
     * Seconds to wait before retrying, when Google supplied a Retry-After header.
     */
    public function retryAfter(): ?int
    {
        return $this->retryAfter;
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
