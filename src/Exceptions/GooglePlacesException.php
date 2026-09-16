<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Exceptions;

use RuntimeException;

/**
 * Base exception for everything this package throws.
 *
 * Messages are always built from Google's own error payload or from package
 * level context. Credentials are never interpolated into an exception message.
 */
class GooglePlacesException extends RuntimeException
{
    /** @var array<string, mixed> */
    protected array $context = [];

    /**
     * @param  array<string, mixed>  $context
     */
    public function withContext(array $context): static
    {
        $this->context = $context;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function context(): array
    {
        return $this->context;
    }
}
