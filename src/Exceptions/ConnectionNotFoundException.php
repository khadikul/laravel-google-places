<?php

declare(strict_types=1);

namespace Khadikul\GooglePlaces\Exceptions;

/**
 * No connected Google Business Profile could be resolved for the operation.
 */
class ConnectionNotFoundException extends GooglePlacesException
{
    public static function none(): self
    {
        return new self(
            'No active Google Business Profile connection found. Complete the OAuth flow '
            .'first, for example by sending the user to GooglePlaces::oauth()->authorizationUrl().'
        );
    }

    public static function forLocation(string $locationName): self
    {
        return new self(sprintf(
            'No active Google Business Profile connection owns the location [%s].',
            $locationName
        ));
    }
}
