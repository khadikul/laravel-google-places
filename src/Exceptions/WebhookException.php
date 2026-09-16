<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Exceptions;

/**
 * An inbound Pub/Sub push request could not be trusted or understood.
 */
class WebhookException extends GooglePlacesException
{
    public static function unauthenticated(string $detail): self
    {
        return new self('Pub/Sub push request rejected: '.$detail);
    }

    public static function invalidPayload(string $detail): self
    {
        return new self('Pub/Sub push payload is invalid: '.$detail);
    }
}
